<?php
/**
 * File: WorkerController.class.php
 * User: huangyingjian
 * Date: 2017/11/1
 */

namespace Qiye\Controller;

use Qiye\Common\ErrorCode;
use Qiye\Model\BaseModel;
use Common\Common\Service\AuthService;
use Common\Common\Service\WorkerService;
use Common\Common\Service\OrderService;
use Common\Common\Service\WorkerQualityService;
use Library\Crypt\AuthCode;
use Qiye\Controller\BaseController;
use Qiye\Model\WorkerMoneyRecordModel;
use Qiye\Logic\WorkerLogic;
use Qiye\Logic\WorkerMoneyLogic;
use Qiye\Model\WorkerModel;
use Qiye\Model\WorkerQualityMoneyRecordModel;

class WorkerController extends BaseController
{
    const WORKER_ORDER_TABLE_NAME = 'worker_order';

    public function wxLogin()
    {
        try {
            (new WorkerLogic())->wxlogin();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function balanceExtracted()
    {
        $money = number_format(I('post.money'), 2, '.', '');
        $pay_password = I('post.pay_password', '');
        try {
            $logic = new WorkerLogic();
            
            $logic->setParam('money', $money);
            $logic->setParam('pay_password', $pay_password);

            $this->requireAuth(AuthService::ROLE_WORKER);
            $logic->extractedByWorker();
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function balanceQuality()
    {
        try {
            $this->requireAuth(AuthService::ROLE_WORKER);
            $data = AuthService::getAuthModel();
            $info = [
                'worker_id'             => $data['worker_id'],
                'quality_money'         => number_format($data['quality_money'], 2, '.', ''),
                'quality_money_need'    => number_format($data['quality_money_need'], 2, '.', ''),
            ];
            $this->response($info);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getBalanceLogsDetail()
    {
        try {
            $worker_id = $this->requireAuth(AuthService::ROLE_WORKER);
            $id     = I('get.id', 0, 'intval');
            $type   = I('get.type', 0, 'intval');
            $info = (new WorkerModel())->getBalanceLogsDetail($id, $type);
                $info['worker_id'] != $worker_id 
            &&  $this->fail(ErrorCode::SYS_DATA_NOT_EXISTS);
            unset($info['worker_id'], $info['admin_id']);
            $this->response($info);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getBalanceLogs()
    {
        try {
            $worker_id = $this->requireAuth(AuthService::ROLE_WORKER);
            $type = I('get.type', 0, 'intval');
            $return = [
                'page_no' => I('page_no', 1, 'intval'),
                'page_num' => I('page_size', 10, 'intval'),
                'count' => 0,
                'count_money' => '0.00',
                'data_list' => null,
            ];
            
            $model = new WorkerModel();
            switch ($type) {

                case WorkerService::WORKER_MONEY_RECORD_QUALITY_TYPE: // 4
                    $qu_model = new WorkerQualityMoneyRecordModel();
                    $where = ['worker_id' => $worker_id];
                    $a_where = [
                        'a.worker_id' => $worker_id,
                    ];
                    $field_arr = [
                        'a.id',
                        'IF(b.orno is null||b.orno="","后台操作调整",concat("工单号 ",b.orno)) as title',
                        '"'.WorkerService::WORKER_MONEY_RECORD_QUALITY.'" as type',
                        'a.quality_money as money',
                        '"" as other',
                        'IF(a.type='.WorkerQualityService::TYPE_SYSTEM.',"'.WorkerQualityService::TYPE_SYSTEM_REMARK.'",a.remark) as remarks',
                        'a.create_time',
                        '"" as other',
                    ];
                    $return['count']        = $qu_model->getNum($where);
                    $return['data_list']    = $qu_model->getList([
                            'alias' => 'a',
                            'join'  => 'left join '.self::WORKER_ORDER_TABLE_NAME.' b on a.worker_order_id = b.id',
//                            'field' => 'a.*,b.orno',
                            'field' => implode(',', $field_arr),
                            'where' => $a_where,
                            'order' => 'a.create_time desc',
                            'limit' => getPage(),
                        ]);
                    break;

                default:
                    list($return['data_list'], $return['count']) = (array)$model->allBalanceLogs($worker_id, $type);
                    $return['data_list']    = (new WorkerMoneyLogic())->ruleAllBalanceLogs($return['data_list']);
                    break;
            }            

            $count_money            = (new WorkerMoneyRecordModel())->getWorkerMoneyTotal($worker_id, $type);
            $return['count_money']  = number_format($count_money, 2, '.', '');
            $return['count']        = (int)$return['count'];

            $this->response($return);
            // $this->paginate($return);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getBalance()
    {
        try {
            $worker_id = $this->requireAuth(AuthService::ROLE_WORKER);
            // AuthService::getAuthModel()->getPrimaryValue();

            $field_arr = [
                'sum(IF(type=1,money,0)) as  incomed',
//                'sum(IF(type=3||type=4,-`money`,0)) as  extracting',
                'sum(IF(type=3,-`money`,0)) as  extracting',
            ];
            // incomed 总收入
            $where = [
                // 'type' => WorkerService::WORKER_MONEY_RECORD_REPAIR,
            ];
            $balance = reset((new WorkerMoneyRecordModel())->countAllMoneuRecoredById(
                    $worker_id, 
                    $where, 
                    implode(',', $field_arr))
                );

            $quality_money = BaseModel::getInstance('worker_quality_money_record')->getList([
                    'field' => 'sum(quality_money) as quality_money',
                    'where' => [
                        'worker_id' => $worker_id
                    ],
                ]);
        
            $order_money = BaseModel::getInstance('worker_order')->getList([
                    'alias' => 'wo',
                    'join'  => 'left join worker_order_fee wof on wo.id = wof.worker_order_id',
                    'field' => 'sum(worker_total_fee_modify) as order_money',
                    'where' => [
                        'wo.worker_id' => $worker_id,
                        'worker_order_status' => OrderService::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT,
                    ],
                ]);
        
            $balance = [    
                'money' =>              number_format(AuthService::getAuthModel()->money, 2, '.', ''),
                'incomed' =>            number_format($balance['incomed'], 2, '.', ''),
                'extracting' =>         number_format($balance['extracting'], 2, '.', ''),
                'order_money' =>        number_format(reset($order_money)['order_money'],     2, '.', ''),
                'quality_money' =>      number_format(reset($quality_money)['quality_money'], 2, '.', ''),
            ];

            $this->response($balance);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function verifyWorkerPayPassword()
    {
        try {
            $this->requireAuth(AuthService::ROLE_WORKER);
            D('Worker', 'Logic')->checkPayPassword(I('post.pay_password', ''), true, I('post.type', 0, 'intval'));
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function setWorkerPayPassword()
    {
        try {
            $this->requireAuth(AuthService::ROLE_WORKER);
            D('Worker', 'Logic')->setWorkerPayPassword(I('post.'));
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getBankCardInfo()
    {
        try {
            $this->requireAuth(AuthService::ROLE_WORKER);
            $data = AuthService::getAuthModel();
            
            $bank_city_id_arr   = array_filter(explode(',', $data['bank_city_ids']));
            $bank_city_name_arr = array_filter(explode('-', $data['bank_city']));
            
            $info = [
                'worker_id'         => $data['worker_id'],
                'nickname'          => $data['nickname'],
                'money'             => sprintf("%.2f", $data['money']),
                'bank_id'           => $data['bank_id'],
                'credit_card'       => $data['credit_card'],
                'bank_name'         => $data['bank_name']=='其它' ? $data['other_bank_name'] : $data['bank_name'],
                'other_bank_name'   => $data['other_bank_name'],
                'bank_cardtype'     => $data['bank_cardtype'],
                'province_id'       => $bank_city_id_arr[0]     ?? '0',
                'province_name'     => $bank_city_name_arr[0]   ?? '',
                'city_id'           => $bank_city_id_arr[1]     ?? '0',
                'city_name'         => $bank_city_name_arr[1]   ?? '',
                'is_set_pay_password'   => strlen($data['pay_password']) == 32 ? '1' : '0',
            ];
            $this->response($info);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function addBankCard($value='')
    {
        try {
            $this->requireAuth(AuthService::ROLE_WORKER);
            $data = [
                'pay_password'      => I('post.pay_password', ''),
                'bank_id'           => I('post.bank_id', 0, 'intval'),
                'credit_card'       => I('post.credit_card', ''),
                'other_bank_name'   => I('post.other_bank_name', ''),
                'province_id'       => I('post.province_id', ''),
                'city_id'           => I('post.city_id', ''),
            ];
            D('Worker', 'Logic')->updateWorkerBankCard($data);
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function upadateBankCard()
    {
        try {
            $this->requireAuth(AuthService::ROLE_WORKER);
            D('Worker', 'Logic')->updateWorkerBankCard(I('put.'));
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function deleteBankCard()
    {
        try {
            $this->requireAuth(AuthService::ROLE_WORKER);
            D('Worker', 'Logic')->deleteWorkerBankCard(I('get.'));
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }
    
    public function login()
    {
        try {

            $worker_logic = D('Worker', 'Logic');

            $worker_logic->setParam('phone', I('phone'));
            $worker_logic->setParam('password', I('password'));
            $worker_logic->setParam('device', I('device'));
            $worker_logic->setParam('app_version', I('app_version'));

            $login_data = $worker_logic->doLogin();

            $this->response($login_data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function verifyCode()
    {
        try {

            $type = I('type', 0, 'intval');

            if (1 == $type) {
                $worker_logic = D('VerifyCode', 'Logic');

                $phone = I('phone');
                $verify_code = I('verify_code');

                $worker_logic->checkForget($phone, $verify_code);
            } elseif (3 == $type) {
                $worker_id = $this->requireAuth(AuthService::ROLE_WORKER);

                $code_logic = new \Qiye\Logic\VerifyCodeLogic();
                $phone = I('phone');
                $verify_code = I('verify_code');
                $code_logic->checkForgetPayPassword($phone, $verify_code);
            } else {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '类型错误');
            }

            $this->response(null);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function forget()
    {
        try {

            $worker_logic = D('Worker', 'Logic');

            $worker_logic->setParam('phone', I('phone'));
            $worker_logic->setParam('verify_code', I('verify_code'));
            $worker_logic->setParam('password', I('password'));

            $worker_logic->forget();

            $this->response(null);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function register()
    {
        try {

            $worker_logic = D('Worker', 'Logic');

            $worker_logic->setParam('phone', I('phone'));
            $worker_logic->setParam('verify_code', I('verify_code'));
            $worker_logic->setParam('device', I('device'));
            $worker_logic->setParam('password', I('password'));

            $login_data = $worker_logic->register();

            $this->response($login_data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function editPassword()
    {
        try {

            $user_id = $this->requireAuth();

            $worker_logic = D('Worker', 'Logic');

            $worker_logic->setParam('user_id', $user_id);
            $worker_logic->setParam('phone', I('phone'));
            $worker_logic->setParam('password', I('password'));

            $login_data = $worker_logic->editPassword();

            $this->response($login_data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function updatePassword()
    {
        try {
            $login_data = D('Worker', 'Logic')->updatePassword(I('put.'), $this->requireAuth());
            $this->response($login_data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function edit()
    {
        try {

            $user_id = $this->requireAuth();

            $worker_logic = D('Worker', 'Logic');

            $worker_logic->setParam('user_id', $user_id);
            $worker_logic->setParam('nickname', I('nickname'));
            $worker_logic->setParam('province_id', I('province_id'));
            $worker_logic->setParam('city_id', I('city_id'));
            $worker_logic->setParam('district_id', I('district_id'));
            $worker_logic->setParam('address', I('address'));
            $worker_logic->setParam('card_no', I('card_no'));
            $worker_logic->setParam('card_front', I('card_front'));
            $worker_logic->setParam('card_back', I('card_back'));

            $return_data = $worker_logic->edit();

            $this->response($return_data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function info()
    {
        try {
            $user_id = $this->requireAuth();

            $worker_logic = D('Worker', 'Logic');

            $user_info = $worker_logic->info($user_id);

            $this->response($user_info);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 技工地址列表
     */
    public function addressList()
    {
        try {
            $data = D('Worker', 'Logic')->addressList($this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 技工默认地址
     */
    public function address()
    {
        try {
            $data = D('Worker', 'Logic')->address($this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 技工默认地址修改
     */
    public function addressEdit()
    {
        try {
            $data = D('Worker', 'Logic')->addressEdit(I('get.id'), I('put.'), $this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 技工pc端登陆
     */
    public function pcLogin()
    {
        try {
            $data = D('Worker', 'Logic')->pcLogin(I('post.'));
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 企业号完善（添加）技工信息
     */
    public function fillInfo()
    {
        try {
            $data = D('Worker', 'Logic')->fillInfo(I('post.'));
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 企业号接收消息
     */
    public function callback()
    {
        try {
            D('QiYeWechat', 'Logic')->callback();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 检查手机号
     */
    public function checkPhone()
    {
        try {
            $data = D('Worker', 'Logic')->checkPhone(I('get.'));
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}