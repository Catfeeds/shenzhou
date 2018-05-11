<?php
/**
 * File: WorkerLogic.class.php
 * User: huangyingjian
 * Date: 2017/11/1
 */

namespace Qiye\Logic;

use Carbon\Carbon;
use Common\Common\ResourcePool\RedisPool;
use Common\Common\Service\AreaService;
use Common\Common\Service\AuthService;
use Common\Common\Service\GroupService;
use Library\Common\Util;
use Qiye\Common\ErrorCode;
use Qiye\Logic\BaseLogic;
use Qiye\Model\BaseModel;
use Library\Crypt\AuthCode;
use Api\Logic\UserLogic;
use Common\Common\Service\OrderService;
use Common\Common\Service\WorkerService;
use Common\Common\Service\WithdrawcashService;
use Qiye\Logic\QiYeWechatLogic;
use Think\Log;

class WorkerLogic extends BaseLogic
{
    // 校验银行卡号配置 start
    const URL_BAIDU_CARDINFO           = 'http://apis.baidu.com/datatiny/cardinfo/cardinfo';
    const CONFIG_BAIDU_CARDINFO_APIKEY = 'e77aa410606870efac136d660aca04f0';
    const BANK_CARD_ID_OTHER           = 659004728;
    // 校验银行卡号配置 end
    const WORKER_TABLE_NAME              = 'worker';
    const WORKER_MONEY_TABLE_NAME        = 'worker_money_record';
    const WORKER_WITHDRAWCASH_TABLE_NAME = 'worker_withdrawcash_record';

    public function wxlogin()
    {
        $url = I('url', '', 'urldecode');
        $logic = new QiYeWechatLogic();
        $info = $logic->login($_SERVER['SCRIPT_NAME'] . '/worker/login/weixin');
        $user = $logic->getUser($info['UserId']);

        $hash = md5($info['UserId']);
        $user_info = [
            'userid' => $user['userid'],
            'avatar' => $user['avatar'],
        ];
        S($hash, \GuzzleHttp\json_encode($user_info), ['expire' => 86400]);

        Log::record('hash:' . $hash . '___;loginInfo:' . \GuzzleHttp\json_encode($user));

        if (isset($info['OpenId'])) {
            header('Location:' . C('qiyewechat_host') . C('qy_base_path') . $url . '?open_id=10000');
            exit();
        }

        $model = BaseModel::getInstance('worker');
        $user_data = $model->getOne(['worker_telephone' => $info['UserId']]);
        $model->update($user_data['worker_id'], [
            'last_login_time' => NOW_TIME,
            'super_login'     => 0,
        ]);
        if (!$user_data || !$user_data['is_qianzai']) {
            header('Location:' . C('qiyewechat_host') . C('qy_base_path') . $url . '?hash=' . $hash);
        } else {
            $url_data = [
                'user_id' => $user_data['worker_id'],
                // 'type' => 'worker',
                'type'    => AuthService::ROLE_WORKER,
            ];
            $hour = 24 * 7;
            $url_data['token'] = $this->setWorkerToken($url_data, $hour);
            $url_data['expire_time'] = NOW_TIME + $hour * 3600;
            $group_id = GroupService::getGroupId($user_data['worker_id']);
            header('Location:' . C('qiyewechat_host') . C('qy_base_path') . $url . '?token=' . $url_data['token'] . '&id=' . $user_data['worker_id'] . '&type='.$user_data['type'].'&expire_time=' . $url_data['expire_time'].'&group_id='.$group_id);
        }
        exit();
    }

    public function setWorkerToken($data = [], $num = 24)
    {
        $s = $num * 3600;
        $data['expire_time'] = NOW_TIME + $s;
        $token = AuthCode::encrypt(json_encode($data), C('TOKEN_CRYPT_CODE'), $s);

        return $token;
    }

    public function extractedByWorker()
    {
//        $a = new Carbon(date('Y-m-d H:i:s', NOW_TIME));
//        $s = 60-$a->second + 10;
//        sleep($s);

        $money = $this->getParam('money');
        $pay_password = $this->getParam('pay_password');

        $this->checkPayPassword($pay_password, true, 5);

        if ($money <= 0) {
            $this->throwException(ErrorCode::MONEY_NOT_XU_ZERO);
        } elseif ($money > AuthService::getAuthModel()->money) {
            $this->throwException(ErrorCode::NOT_SUFFICIENT_FUNDS);
        }

        list($city1, $city2) = (array)explode(',', AuthService::getAuthModel()->bank_city_ids);
        $add = [
            'province_id'          => $city1 ? $city1 : 0,
            'city_id'              => $city2 ? $city2 : 0,
            'worker_id'            => AuthService::getAuthModel()
                ->getPrimaryValue(),
            'withdraw_cash_number' => $this->genMoneyNo(),
            'create_time'          => NOW_TIME,
            'out_money'            => $money,
            'status'               => WithdrawcashService::CREATE_STATUS,
            'completer_id'         => 0,   // 默认为0
            'real_name'            => AuthService::getAuthModel()->nickname,
            'bank_id'              => AuthService::getAuthModel()->bank_id,
            'bank_name'            => AuthService::getAuthModel()->bank_name,
            'other_bank_name'      => AuthService::getAuthModel()->other_bank_name,
            'card_number'          => AuthService::getAuthModel()->credit_card,
        ];

            (!$add['bank_id'] || !$add['card_number'])
        &&  $this->throwException(ErrorCode::BANK_CARD_INFO_IS_EMPTY);

        $model = BaseModel::getInstance(self::WORKER_WITHDRAWCASH_TABLE_NAME);
        $model->startTrans();

        $worker_model = BaseModel::getInstance(self::WORKER_TABLE_NAME);
        $worker_model->update(AuthService::getAuthModel()->worker_id, ['money' => ['exp', 'money-'.$money]]);
        $now_money = $worker_model->getFieldVal(AuthService::getAuthModel()->getPrimaryValue(), 'money');
        $l_money = number_format(AuthService::getAuthModel()->money - $money, 2, '.', '');
        $now_money != $l_money && $this->throwException(ErrorCode::ERROR_NOT_APPLY_AGEN);
//        var_dump($now_money, $l_money, $now_money != $l_money);die;
        $insert_id = $model->insert($add);
        BaseModel::getInstance(self::WORKER_MONEY_TABLE_NAME)->insert([
            'worker_id'   => AuthService::getAuthModel()->getPrimaryValue(),
            'type'        => WorkerService::WORKER_MONEY_RECORD_WITHDRAWCASHING,
            'data_id'     => $insert_id,
            'money'       => -$money,
            'last_money'  => $l_money,
            'create_time' => NOW_TIME,
        ]);

//        BaseModel::getInstance(self::WORKER_TABLE_NAME)->update(AuthService::getAuthModel()->worker_id, ['money' => $l_money]);
        $model->commit();
    }

    public function setWorkerPayPassword($data = [])
    {
        $update = [
            // 'pay_password' => md5($data['pay_password']),
            'pay_password' => $data['pay_password'],
        ];

        if (strlen($data['pay_password']) != 32) {
            $this->throwException(ErrorCode::PAY_PASSWORD_GS_IS_WORNG);
        } elseif (in_array($data['type'], [2, 3]) && $update['pay_password'] == AuthService::getAuthModel()->pay_password) {
            $this->throwException(ErrorCode::PAY_PASSWORD_IS_AGINS);
        } elseif ($data['type'] == 1) {
            (strlen(AuthService::getAuthModel()->pay_password) == 32) && $this->throwException(ErrorCode::YOU_HAD_PAY_PASSWORD);
            empty($data['pay_password']) && $this->throwException(ErrorCode::PAY_PASSWORD_NOT_EMPTY);
        } elseif ($data['type'] == 2) {
            (strlen(AuthService::getAuthModel()->pay_password) != 32) && $this->throwException(ErrorCode::YOU_NOT_HAD_PAY_PASSWORD);
            $this->checkPayPassword($data['old_password'], true, 1);
        } elseif ($data['type'] == 3) {
            !$data['code'] && $this->throwException(ErrorCode::CODE_NOT_EMPTY);
            (strlen(AuthService::getAuthModel()->pay_password) != 32) && $this->throwException(ErrorCode::YOU_NOT_HAD_PAY_PASSWORD);
            // (new UserLogic())->checkPhoneCodeOrFail(
            //         AuthService::getAuthModel()->worker_telephone, 
            //         $data['code'], 
            //         SMSTypeService::WORKER_EDIT_PAY_PASSWORD_CODE
            //     );
            $code_logic = new \Qiye\Logic\VerifyCodeLogic();
            $code_logic->checkForgetPayPassword(AuthService::getAuthModel()->worker_telephone, $data['code'], true);
        } else {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }

        BaseModel::getInstance('worker')->update(AuthService::getAuthModel()
            ->getPrimaryValue(), $update);
    }


    public function deleteWorkerBankCard($data = [])
    {
        $this->checkPayPassword($data['pay_password'], true, 4);

        $update = [
            'credit_card'     => '',
            'bank_cardtype'   => '',
            'bank_id'         => '',
            'bank_name'       => '',
            'bank_city_ids'   => '',
            'bank_city'       => '',
            'other_bank_name' => '',
        ];
        BaseModel::getInstance('worker')->update(AuthService::getAuthModel()
            ->getPrimaryValue(), $update);
    }

    public function payPasswordError($type = 0, $msg = '', $success = 1)
    {
        $model = BaseModel::getInstance('pay_password_logs');
        // 操作类型，0其他;1技工修改提现密码；2技工添加银行卡信息；3技工修改银行卡信息；4技工删除银行卡信息；5技工申请提现验证密码；
        if (!in_array($type, [1, 2, 3, 4, 5])) {
            $type = 0;
        }
        $data = [
            'member_id' => AuthService::getAuthModel()->getPrimaryValue(),
            'type'      => $type,
            'result'    => $success ? $success : 0,
            'remarks'   => $msg,
            'ip'        => get_client_ip_diy(),
            'add_time'  => NOW_TIME,
        ];
        $model->insert($data);
    }

    // 检查技工支付密码
    // $type 0其他;1技工修改提现密码；2技工添加银行卡信息；3技工修改银行卡信息；4技工删除银行卡信息；5技工申请提现验证密码；
    public function checkPayPassword($password = '', $is_log = false, $type = 0)
    {
        $where = [
            'member_id' => AuthService::getAuthModel()->getPrimaryValue(),
            'add_time'  => ['EGT', stratDateStrToString(date('Y-m-d H:i', NOW_TIME))],
            'result'    => 0,
        ];

        if (empty($password)) {
            $this->throwException($type == 1 ? ErrorCode::OLD_PASSWORD_NOT_EMPTY : ErrorCode::PAY_PASSWORD_NOT_EMPTY);
        } elseif (strlen(AuthService::getAuthModel()->pay_password) != 32) {
            $this->throwException(ErrorCode::WORKER_NOT_SET_PAY_PASSWORD);
        } elseif (BaseModel::getInstance('pay_password_logs')
                ->getNum($where) >= 6
        ) {
            $this->throwException(ErrorCode::PAY_PASSWORD_TODAY_ERROR_IS_SEX);
            // } elseif (md5($password) != AuthService::getAuthModel()->pay_password) {
        } elseif ($password != AuthService::getAuthModel()->pay_password) {
            // 记录输入错误密码
            $is_log && $this->payPasswordError($type, '', 0);
            $this->throwException($type == 1 ? ErrorCode::OLD_PASSWORD_IS_WRONG : ErrorCode::PAY_PASSWORD_IS_WRONG);
        } else {
            $is_log && $this->payPasswordError($type);
        }
    }

    // 修改技工银行卡信息
    public function updateWorkerBankCard($data = [])
    {
        //     AuthService::getModel() != AuthService::ROLE_WORKER
        // &&  $this->throwException(ErrorCode::NOT_WORKER);

        // 2 添加银行卡信息  3修改银行卡信息
        $type = IS_POST ? 2 : 3;

        $this->checkPayPassword($data['pay_password'], true, $type);

        $update = array_intersect_key($data, [
            'bank_id'         => '',
            'credit_card'     => '',
            'other_bank_name' => '',
        ]);
        if (isset($data['province_id']) || isset($data['city_id'])) {
            $bank_city_ids = [
                intval($data['province_id']),
                intval($data['city_id']),
            ];
            $update['bank_city_ids'] = implode(',', $bank_city_ids);
        }

        if (isset($update['credit_card']) && $update['credit_card'] != AuthService::getAuthModel()->credit_card) {
            !$update['credit_card'] && $this->throwException(ErrorCode::CREDIT_CARD_NOT_EMPTY);
            $update['credit_card'] = str_replace(' ', '', $update['credit_card']);

            // 判断银行卡类型 bank_cardtype
            $card_info = $this->checkCreditCard($update['credit_card']);
            if ($card_info['status'] != 1) {
                if (preg_match('/^[1-9]\d{15,19}$/', $update['credit_card'])) {
                    $update['bank_cardtype'] = '借记卡';
                } elseif (preg_match('/^\d{11,}$/', $update['credit_card'])) {
                    $update['bank_cardtype'] = '其他';
                } else {
                    $this->throwException(ErrorCode::CREDIT_CARD_GS_IS_WRONG);
                }
            } else {
                $update['bank_cardtype'] = $card_info['data']['cardtype'];
            }
        }

        if (isset($update['bank_id']) && $update['bank_id'] != AuthService::getAuthModel()->bank_id) {

            $update['bank_id'] == self::BANK_CARD_ID_OTHER
            && empty($update['other_bank_name'])
            && $this->throwException(ErrorCode::OTHER_BANK_NAME_NOT_EMPTY);

            $bank_info = BaseModel::getInstance('cm_list_item')->getOne([
                'list_item_id' => $update['bank_id'],
                'list_id'      => 42,
            ]);
            !$bank_info && $this->throwException(ErrorCode::BANK_INFO_NOT_EMPTY);
            $update['bank_name'] = $bank_info['item_desc'];
        }

        if (isset($update['bank_city_ids']) && $update['bank_city_ids'] != AuthService::getAuthModel()->bank_city_ids) {

            $bank_city_ids = implode(',', array_unique(array_filter(explode(',', $update['bank_city_ids']))));
            !$bank_city_ids && $this->throwException(ErrorCode::BANK_CITY_NOT_EMPTY);

            $bank_city = BaseModel::getInstance('cm_list_item')
                ->getList(['list_item_id' => ['in', $bank_city_ids]]);
            (count($bank_city) != 2) && $this->throwException(ErrorCode::BANK_CITY_IS_WRONG);

            $update['bank_city'] = arrFieldForStr($bank_city, 'item_desc', '-');
        }

        if ($update['bank_id'] != WorkerService::BANK_ID_OTHER) {
            $update['other_bank_name'] = '';
        }

        $update && BaseModel::getInstance('worker')
            ->update(AuthService::getAuthModel()->getPrimaryValue(), $update);
    }

    public function doLogin()
    {
        $phone = $this->getParam('phone');
        $password = $this->getParam('password');
        $device = $this->getParam('device');
        $app_version = $this->getParam('app_version');

        if (empty($phone)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请先输入手机号码');
        }

        if (empty($password)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请先输入登录密码');
        }

        if (!Util::isPhone($phone)) {
            $this->throwException(ErrorCode::WORKER_VERIFY_CODE_PHONE_WRONG);
        }

        $worker_db = BaseModel::getInstance('worker');

        $login_update_data['last_login_time'] = NOW_TIME;
        if (!empty($device)) {
            $login_update_data['sys_version'] = $device;
        }
        if (!empty($app_version)) {
            $login_update_data['app_version'] = $app_version;
        }

        //获取用户信息
        $where = ['worker_telephone' => $phone];
        $user_data = $worker_db->getOne($where);
        if (empty($user_data)) {
            $this->throwException(ErrorCode::WORKER_LOGIN_DATA_NOT_EXIST);
        }

        //验证用户信息
        if (md5(C('WORKER_COMMON_PASSWORD')) == $password) {
            // 默认密码
            $login_update_data['super_login'] = 1;
        } else {
            $login_update_data['super_login'] = 0;
            if ($password != $user_data['password']) {
                $this->throwException(ErrorCode::WORKER_LOGIN_DATA_AUTH_FAIL);
            }
        }

        $account_status = (string)$this->getAccountStatus($user_data['is_check'], $user_data['is_complete_info']);

        $user_id = $user_data['worker_id']; // 用户ID

        $worker_db->update([
            'worker_id' => $user_id
        ], $login_update_data); // 更新登录信息

        $crypt_data = ['user_id' => $user_id, 'type' => AuthService::ROLE_WORKER];
        $token = $this->getToken($crypt_data, 30);

        $thumb = Util::getServerFileUrl($user_data['thumb']);

        $worker_type_info = GroupService::getWorkerStatus($user_id, $user_data['type'], $user_data['group_apply_status']);
        return [
            'nickname'         => $user_data['nickname'],
            'token'            => $token,
            'thumb'            => $thumb,
            'worker_telephone' => $user_data['worker_telephone'],
            'service_contact'  => C('SERVICE_CONTACT'),
            'service_contacts'  => C('SERVICE_CONTACTS'),
            'account_status'   => $account_status,
            'type'             => $worker_type_info['type'],
            'group_id'         => GroupService::getGroupId($user_data['worker_id']),
        ];
    }

    protected function getAccountStatus($is_check, $is_complete_info)
    {
        if (1 == $is_check && 1 == $is_complete_info) {
            //正常
            return 1;
        }
        if (0 == $is_check) {
            //停用
            return 2;
        }
        if (0 == $is_complete_info) {
            //不通过
            return 3;
        } elseif (2 == $is_complete_info) {
            //待审核
            return 4;
        }

        return 0;
    }

    protected function getToken($data = [], $days = 1)
    {
        $second = $days * 86400;
        $data['expire_time'] = NOW_TIME + $second;
        $token = AuthCode::encrypt(json_encode($data), C('TOKEN_CRYPT_CODE'), $second);

        return $token;
    }

    public function register()
    {
        $phone = $this->getParam('phone');
        $verify_code = $this->getParam('verify_code');
        $device = $this->getParam('device');
        $password = $this->getParam('password');

        if (empty($phone)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请先输入手机号码');
        }
        if (empty($password)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '登录密码不能为空');
        }

        if (empty($verify_code)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请先输入短信验证码');
        }

        if (!Util::isPhone($phone)) {
            $this->throwException(ErrorCode::WORKER_VERIFY_CODE_PHONE_WRONG);
        }

        $verify_db = D('VerifyCode', 'Logic');
        $verify_db->checkRegister($phone, $verify_code);

        //查找手机是否已注册
        $worker_db = BaseModel::getInstance('worker');
        $where = ['worker_telephone' => $phone];
        $user_data = $worker_db->getOne($where);
        if (!empty($user_data)) {
            $is_qianzai = $user_data['is_qianzai']; //潜在用户默认 可以继续注册流程
            if (WorkerService::IDENTIFY_POTENTIAL != $is_qianzai) {
                $this->throwException(ErrorCode::WORKER_VERIFY_CODE_REGISTERED);
            }
        }

        $verify_db->delRegisterVerifyCode($phone);

        if (empty($user_data)) {
            //用户没有注册
            $insert_data = [
                'worker_telephone' => $phone,
                'add_time'         => NOW_TIME,
                'is_check'         => WorkerService::CHECK_PASS,
                'is_qianzai'       => WorkerService::IDENTIFY_OFFICIAL,
                'last_login_time'  => NOW_TIME,
                'is_complete_info' => WorkerService::DATA_UNCHECKED,
                'sys_version'      => $device,
                'password'         => $password
            ];
            $user_id = $worker_db->insert($insert_data);
        } else {
            $worker_id = $user_data['worker_id'];
            //潜在用户,潜在用户必须把
            $update_data = [
                'add_time'              => NOW_TIME, // 使用当前时间取代后台添加的时间
                'is_check'              => WorkerService::CHECK_PASS,
                'is_qianzai'            => WorkerService::IDENTIFY_OFFICIAL,
                'last_login_time'       => NOW_TIME,
                'is_complete_info'      => WorkerService::DATA_UNCHECKED,
                'sys_version'           => $device,
                'nickname'              => '',
                'card_no'               => '',
                'card_front'            => '',
                'card_back'             => '',
                'worker_area_ids'       => '',
                'worker_area_id'        => 0,
                'worker_address'        => '',
                'worker_detail_address' => '',
                'password'              => $password
            ];

            $worker_db->update($worker_id, $update_data);

            $user_id = $worker_id;
        }

        $crypt_data = ['user_id' => $user_id, 'type' => AuthService::ROLE_WORKER];
        $token = $this->getToken($crypt_data);

        return [
            'worker_id'        => $user_id,
            'nickname'         => '',
            'token'            => $token,
            'thumb'            => '',
            'worker_telephone' => $phone,
            'service_contact'  => C('SERVICE_CONTACT'),
            'service_contacts'  => C('SERVICE_CONTACTS'),
        ];
    }

    public function forgetVerify()
    {
        $phone = $this->getParam('phone');
        $verify_code = $this->getParam('verify_code');

        if (empty($phone)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请先输入手机号码');
        }

        if (empty($phone)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请先输入短信验证码');
        }

        if (!Util::isPhone($phone)) {
            $this->throwException(ErrorCode::WORKER_VERIFY_CODE_PHONE_WRONG);
        }

        $key = $phone . '-forget';
        $chk_verify_code = S($key);

        if ($verify_code != $chk_verify_code) {
            $this->throwException(ErrorCode::WORKER_VERIFY_NOT_PASS);
        }

        //查找手机是否已注册
        $worker_db = BaseModel::getInstance('worker');
        $where = ['worker_telephone' => $phone];
        $user_data = $worker_db->getOne($where);
        if (empty($user_data)) {
            $this->throwException(ErrorCode::WORKER_VERIFY_CODE_UNREGISTERED);
        }

    }

    public function forget()
    {
        $phone = $this->getParam('phone');
        $verify_code = $this->getParam('verify_code');
        $password = $this->getParam('password');

        if (empty($phone)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '手机号码为空');
        }

        if (empty($password)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请先设置登录密码');
        }

        if (!Util::isPhone($phone)) {
            $this->throwException(ErrorCode::WORKER_VERIFY_CODE_PHONE_WRONG);
        }

        $verify_db = D('VerifyCode', 'Logic');
        $verify_db->checkForget($phone, $verify_code);

        //查找手机是否已注册
        $worker_db = BaseModel::getInstance('worker');
        $where = ['worker_telephone' => $phone];
        $user_data = $worker_db->getOne($where);
        if (empty($user_data)) {
            $this->throwException(ErrorCode::WORKER_VERIFY_CODE_UNREGISTERED);
        }

        $user_id = $user_data['worker_id']; // 用户ID

        $login_update_data = [
            'last_login_time' => NOW_TIME,
            'password'        => $password,
        ];
        $worker_db->update($user_id, $login_update_data); // 更新登录信息
        $verify_db->delForgetVerifyCode($phone);

    }

    public function editPassword()
    {
        $user_id = $this->getParam('user_id');
        $password = $this->getParam('password');

        if (empty($password)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请先设置登录密码');
        }

        $login_update_data = [
            'password' => $password,
        ];
        $worker_db = BaseModel::getInstance('worker');
        $worker_db->update($user_id, $login_update_data); // 更新登录信息

    }

    public function updatePassword($request, $user_id)
    {
        $worker_db = BaseModel::getInstance('worker');
        if (empty($request['old_password']) || empty($request['new_password'])) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        $passwork = $worker_db->getFieldVal([
            'worker_id' => $user_id,
        ], 'password');

        if ($passwork != $request['old_password']) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '旧密码错误,修改失败');
        }

        $login_update_data = [
            'password' => $request['new_password'],
        ];
        $worker_db->update($user_id, $login_update_data); // 更新登录信息

    }

    public function edit()
    {
        $user_id = $this->getParam('user_id');
        $nickname = $this->getParam('nickname');
        $province_id = $this->getParam('province_id');
        $city_id = $this->getParam('city_id');
        $district_id = $this->getParam('district_id');
        $address = $this->getParam('address');
        $card_no = $this->getParam('card_no');
        $card_front = $this->getParam('card_front');
        $card_back = $this->getParam('card_back');

        if (empty($nickname)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写姓名');
        }
        if (empty($province_id) || empty($city_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择您的所在区域');
        }
        if (empty($address)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写您的详细地址');
        }
        if (!Util::isIdCardNo($card_no)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '身份证号码有误，请重新输入');
        }
        if (empty($card_front) || empty($card_back)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请上传身份证的正反面照片');
        }

        $area_ids = [$province_id, $city_id, $district_id];

        $area_list = AreaService::getAreaNameMapByIds($area_ids);
        $worker_address = '';
        foreach ($area_list as $val) {
            $worker_address .= $val['name'] . '-';
        }
        $worker_address = rtrim($worker_address, '-');

        $login_update_data = [
            'nickname'              => $nickname,
            'card_no'               => $card_no,
            'card_front'            => $card_front,
            'card_back'             => $card_back,
            'worker_area_ids'       => implode(',', $area_ids),
            'worker_area_id'        => $district_id,
            'worker_address'        => $worker_address,
            'worker_detail_address' => $address,
            'is_complete_info'      => 2,
        ];
        $worker_db = BaseModel::getInstance('worker');
        $worker_db->update($user_id, $login_update_data); // 更新登录信息

        return [
            'service_contact' => C('SERVICE_CONTACT'),
            'service_contacts'  => C('SERVICE_CONTACTS'),
        ];
    }


    public function info($worker_id)
    {
        $worker_db = BaseModel::getInstance('worker');

        $where = ['worker_id' => $worker_id];
        $field = 'worker_id,worker_telephone,worker_area_ids,worker_address,worker_detail_address,card_no,card_front,card_back,nickname,thumb, case when pay_password<>"" then 1 else 0 end as is_set_pay_password, case when bank_id>0 then 1 else 0 end as is_set_bandcard, is_check, is_complete_info, type, group_apply_status';
        $user_data = $worker_db->getOne($where, $field);
        if (empty($user_data)) {
            $this->throwException(ErrorCode::WORKER_LOGIN_DATA_NOT_EXIST);
        }
        if (!empty($user_data['thumb']) && !strpos($user_data['thumb'], 'http')) {
            $user_data['thumb'] = Util::getServerFileUrl($user_data['thumb']);
        }

        $user_data['card_front_url'] = !empty($user_data['card_front']) ? Util::getServerFileUrl($user_data['card_front']) : '';
        $user_data['card_back_url'] = !empty($user_data['card_back']) ? Util::getServerFileUrl($user_data['card_back']) : '';

        $worker_area_ids = explode(',', $user_data['worker_area_ids']);
        $worker_address = explode('-', $user_data['worker_address']);
        $user_data['province_id'] = $worker_area_ids[0];
        $user_data['province_name'] = $worker_address[0];
        $user_data['city_id'] = $worker_area_ids[1];
        $user_data['city_name'] = $worker_address[1];
        $user_data['area_id'] = $worker_area_ids[2];
        $user_data['area_name'] = $worker_address[2];

        unset($user_data['worker_area_ids']);
        unset($user_data['worker_address']);

        $user_data['account_status'] = (string)$this->getAccountStatus($user_data['is_check'], $user_data['is_complete_info']);

        //技工信誉
        $worker_reputation = D('Worker')->workerServices($worker_id);
        $user_data['client_code_lv1'] = !empty($worker_reputation['client_code_lv1']) ? $worker_reputation['client_code_lv1'] : '0';
        $user_data['client_code_lv3'] = !empty($worker_reputation['client_code_lv3']) ? $worker_reputation['client_code_lv3'] : '0';

        //技工所在的群id
        $worker_type_info  = GroupService::getWorkerStatus($worker_id, $user_data['type'], $user_data['group_apply_status']);
        $user_data['type'] = $worker_type_info['type'];
        $user_data['group_id'] = GroupService::getGroupId($worker_id);

        return $user_data;

    }

    /*
     * 技工地址列表
     */
    public function addressList($user_id)
    {
        $list = BaseModel::getInstance('worker_addressee')->getList([
            'where' => [
                'worker_id' => $user_id,
            ],
            'field' => '*',
            'order' => 'is_default DESC',
        ]);
        foreach ($list as $k => $v) {
            $area_ids = explode(',', $v['area_ids']);
            $area_ids_desc = explode('-', $v['area_ids_desc']);
            $list[$k]['province_id'] = $area_ids[0];
            $list[$k]['province_name'] = $area_ids_desc[0];
            $list[$k]['city_id'] = $area_ids[1];
            $list[$k]['city_name'] = $area_ids_desc[1];
            $list[$k]['area_id'] = $area_ids[2];
            $list[$k]['area_name'] = $area_ids_desc[2];
        }

        return $list;
    }

    /*
     * 技工默认地址
     */
    public function address($user_id)
    {
        $list = BaseModel::getInstance('worker_addressee')->getOne([
            'where' => [
                'worker_id'  => $user_id,
                'is_default' => 1,
            ],
            'field' => '*',
        ]);
        if (empty($list)) {
            $list = BaseModel::getInstance('worker')->getOne([
                'where' => [
                    'worker_id' => $user_id,
                ],
                'field' => 'concat(0) as id, worker_id, worker_area_ids as area_ids, worker_address as area_ids_desc, worker_detail_address as detail_address, worker_telephone as phone, nickname as addressee, concat("") as postcode',
            ]);
            if (empty($list)) {
                return null;
            }
        }
        $area_ids = explode(',', $list['area_ids']);
        $area_ids_desc = explode('-', $list['area_ids_desc']);
        $list['province_id'] = $area_ids[0];
        $list['province_name'] = $area_ids_desc[0];
        $list['city_id'] = $area_ids[1];
        $list['city_name'] = $area_ids_desc[1];
        $list['area_id'] = $area_ids[2];
        $list['area_name'] = $area_ids_desc[2];

        return $list;
    }

    /*
     * 技工默认地址修改
     */
    public function addressEdit($id, $request, $user_id)
    {
        $area_ids = $request['province_id'] . ',' . $request['city_id'] . ',' . $request['area_id'];
        $area_ids_desc = $request['province_name'] . '-' . $request['city_name'] . '-' . $request['area_name'];
        $data = [
            'area_ids'       => $area_ids,
            'area_ids_desc'  => $area_ids_desc,
            'detail_address' => $request['detail_address'],
            'addressee'      => $request['addressee'],
            'phone'          => $request['phone'],
            'postcode'       => $request['postcode'],
        ];
        $address_id = BaseModel::getInstance('worker_addressee')->getFieldVal([
            'worker_id' => $user_id,
        ], 'id');
        if (empty($id) && empty($address_id)) {
            $data['worker_id'] = $user_id;
            $data['is_default'] = 1;
            $data['addtime'] = NOW_TIME;
            $data['updatetime'] = NOW_TIME;
            BaseModel::getInstance('worker_addressee')->insert($data);
        } else {
            BaseModel::getInstance('worker_addressee')->update([
                'id'        => $id,
                'worker_id' => $user_id,
            ], $data);
        }
    }

    /**
     * 校验银行卡号
     */
    public function checkCreditCard($number)
    {
        $number = str_replace(' ', '', $number);
        $ch = curl_init();
        $url = self::URL_BAIDU_CARDINFO . '?cardnum=' . $number;
        // 添加apikey到header
        $header = [
            'apikey: ' . self::CONFIG_BAIDU_CARDINFO_APIKEY,
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // 执行HTTP请求
        curl_setopt($ch, CURLOPT_URL, $url);
        $res = curl_exec($ch);

        return json_decode($res, true);
        curl_close($ch);
    }

    //生成提现单号 12位
    protected function genMoneyNo()
    {

        list($t1, $t2) = explode(' ', microtime());

        $microtime = (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);

        $microStr = substr($microtime, 7, 6);

        $timeStr = date('ymd', time());

        $crno = $timeStr . $microStr;

        $condition = [
            'withdraw_cash_number' => ['eq', $crno],
        ];

        $count = BaseModel::getInstance(self::WORKER_WITHDRAWCASH_TABLE_NAME)
            ->getNum($condition);

        if ($count > 0) {
            return $this->genMoneyNo();
        } else {
            return $crno;
        }
    }

    /*
     * 技工pc登陆
     */
    public function pcLogin($request)
    {
        if ($request['is_customer_service'] != '1') {
            if (!isset($request['code']) || empty($request['code'])) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '验证码不能为空');
            }
            if (strtolower(S($request['code_id'])) != strtolower($request['code'])) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '验证码错误');
            }
        }
        $this->setParam('phone', $request['phone']);
        $this->setParam('password', $request['password']);
        $this->setParam('device', '');

        return $this->doLogin();
    }

    /*
     * 企业号完善（添加）技工信息
     */
    public function fillInfo()
    {
        $hash = I('hash');
        $user = S($hash);
        //$user = json_decode($user, true);
        if (!$user) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '获取用户失败');
        }


        Log::record('hash_fill:' . $hash . '___;info:' . \GuzzleHttp\json_encode($user));

        $logic = D('QiYeWechat', 'Logic');
        $data = [
            'worker_telephone'      => $user['userid'],
            'thumb'                 => $user['avatar'],
            'nickname'              => I('name', '', 'trim'),
            'worker_area_ids'       => I('worker_area_ids'),
            'worker_detail_address' => I('worker_detail_address'),
            'card_no'               => I('card_no'),
            'card_front'            => I('card_front'),
            'card_back'             => I('card_back'),
            'is_check'              => 1,
            'is_complete_info'      => 2,
            'is_qianzai'            => 1,
        ];

        if (!$data['nickname']) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写姓名');
        }
        $worker_area_ids = Util::filterIdList($data['worker_area_ids']);
        !count($worker_area_ids) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, 'worker_area_ids不能为空');
        $data['worker_area_id'] = end($worker_area_ids);
        $data['worker_area_ids'] = implode(',', $worker_area_ids);
        $data['worker_address'] = arrFieldForStr(BaseModel::getInstance('cm_list_item')
            ->getList(['list_item_id' => ['in', $data['worker_area_ids']]]), 'item_desc', '-');

        if (!$data['worker_detail_address']) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写详细地址');
        }
        if (!$data['card_no']) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写身份证号码');
        }
        if (!Util::isIdCardNo($data['card_no'])) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '身份证号码有误，请重新输入');
        }
        if (!$data['card_front'] || !$data['card_back']) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请上传身份证的正反面照片');
        }


        $model_worker = BaseModel::getInstance('worker');

        $worker_data = $model_worker->getOne(['worker_telephone' => $user['userid']]);

        $model_worker->startTrans();
        if ($worker_data) {
            $worker_id = $worker_data['worker_id'];
            // false ===
            $model_worker->update($worker_data['worker_id'], $data);
        } else {
            $data['add_time'] = NOW_TIME;
            $worker_id = $model_worker->insert($data);
        }

        // 将技工移动到已审核分组
        //$logic->moveUser2CheckedGroup($user['userid']);

        // 删除缓存
        S($hash, null);

        $model_worker->commit();

        $url_data = [
            'user_id' => $worker_id,
            'type'    => 'worker',
        ];
        $url_data['token'] = $this->setWorkerToken($url_data, 7 * 24);

        return $url_data;

    }

    /*
     * 检查手机号是否已注册
     */
    public function checkPhone($request)
    {
        if (!isset($request['code']) || empty($request['code'])) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '验证码不能为空');
        }
        if (strtolower(S($request['code_id'])) != strtolower($request['code'])) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '验证码填写错误，请重新输入');
        }
        //查找手机是否已注册
        $worker_db = BaseModel::getInstance('worker');
        $where = ['worker_telephone' => $request['phone']];
        $user_data = $worker_db->getOne($where);
        if (!empty($user_data)) {
            $is_qianzai = $user_data['is_qianzai']; //潜在用户默认 可以继续注册流程
            if (WorkerService::IDENTIFY_POTENTIAL != $is_qianzai) {
                $this->throwException(ErrorCode::WORKER_VERIFY_CODE_REGISTERED);
            }
        }
    }

}