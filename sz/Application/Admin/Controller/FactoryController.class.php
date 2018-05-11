<?php
/**
 * File: FactoryController.class.php
 * User: xieguoqiu
 * Date: 2017/4/7 9:34
 */

namespace Admin\Controller;

use Admin\Logic\FactoryLogic;
use Carbon\Carbon;
use Common\Common\Service\AuthService;
use Admin\Common\ErrorCode;
use Common\Common\Service\FactoryMoneyFrozenRecordService;
use Common\Common\Service\FactoryService;
use Common\Common\Service\OrderService;
use Common\Common\Service\WorkerService;
use Illuminate\Support\Arr;
use Library\Common\Util;
use Admin\Model\BaseModel;
use Library\Crypt\AuthCode;
use Common\Common\Repositories\Events\CreateFactoryExcdeApplyEvent;
use Common\Common\Service\PayService;
use Common\Common\Service\YiLianService\KeyConfigService;
use Common\Common\Service\FactoryMoneyRecordService;
use Admin\Model\FactoryModel;
use Common\Common\Service\PayPlatformRecordService;

class FactoryController extends BaseController
{
    const FACTORY_MONEY_CHANGE_TABLE_NAME = 'factory_money_change_record';
    const PAY_RECORD_TABLE_NAME           = 'pay_platform_record';
    const FACTORY_TABLE_NAME              = 'factory';
    const FACTORY_ADMIN_TABLE_NAME        = 'factory_admin';
    const ADMIN_TABLE_NAME                = 'admin';
    const ORDER_TABLE_NAME                = 'worker_order';
    const ORDER_PRODUCT_TABLE_NAME        = 'worker_order_product';
    const FACTORY_FROZEN_TABLE_NAME       = 'factory_money_frozen';

    public function workerOrderMoneies()
    {
        $type = I('get.type', 0, 'intval');
        try {
            $factory = $this->requireAuthSearchFactoryGetFactory();

            $list = null;
            $count = 0;

            $is_export = I('is_export', 0, 'intval');


            $logic = new FactoryLogic();
            // 1冻结（进行中）；2待结算；3已结算
            // $factory['factory_id'] = 1739;
            $logic->workerOrdersIngMoneyPaginate($type, $factory['factory_id'], $list, $count, $total_money);
            if (1 != $is_export) {
                $total_money = number_format($total_money, 2, '.', '');
                $this->paginate($list, $count, ['total_money' => $total_money]);
            }

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function recharges()
    {
        $fid = I('get.id', 0, 'intval');

        try {
            $factory_id = $this->requireAuthFactoryGetFid();
            $factory_id != $fid && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR);

            $is_export = I('is_export', 0, 'intval');

            $list = null;
            $count = 0;
            $logic = new FactoryLogic();
            $logic->getRechargesPaginate($factory_id, $list, $count, $total_money);
            if (1 != $is_export) {
                $total_money = number_format($total_money, 2, '.', '');
                $this->paginate($list, $count, ['total_money' => $total_money]);
            }

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function moneyTotal()
    {
        try {
            $factory = $this->requireAuthSearchFactoryGetFactory();

            $total = [
                "ing_nums"       => "0",
                "frozen_money"   => "0.00",
                "wait_end_nums"  => "0",
                "wait_end_money" => "0.00",
                "end_nums"       => "0",
                "end_money"      => "0.00",
            ];
            $cp = (array)(new FactoryModel())->getWorkerOrderFeeTotalForFactory($factory['factory_id']);
            $total = $cp + $total;

            $total['frozen_money'] = number_format($total['frozen_money'], 2, '.', '');

            $total['cancel_nums'] = BaseModel::getInstance(self::ORDER_TABLE_NAME)
                ->getNum([
                    'factory_id'    => $factory['factory_id'],
                    'cancel_status' => ['not in', OrderService::CANCEL_TYPE_NULL.','.OrderService::CANCEL_TYPE_CS_STOP.','.OrderService::CANCEL_TYPE_FACTORY_ADMIN],
                ]);

            $str = implode(',', FactoryMoneyRecordService::CHANGE_TYPE_FOR_RECHARGE_ARR);
            [$counts, $count_price] = $str ? reset(BaseModel::getInstance(self::FACTORY_MONEY_CHANGE_TABLE_NAME)
                ->getList([
                    'field' => 'count(id) as "0",SUM(change_money) as "1"',
                    'where' => [
                        'change_type' => ['in', $str],
                        'factory_id'  => $factory['factory_id'],
                        'status'      => FactoryMoneyRecordService::STATUS_VALUE_SUCCESS,
                    ],
                ])) : ['0', '0.00'];

            // $available_money = $factory['money'] - $factory['frozen_money'];
            $available_money = $factory['money'] - $total['frozen_money'] - $total['wait_end_money'];
            $response = [
                'money'              => $factory['money'],
                'available_money'    => number_format($available_money, 2, '.', ''),
                'recharge_money'     => number_format($count_price, 2, '.', ''),
                'recharge_times'     => $counts,
                'worker_order_total' => $total,
            ];

            $this->response($response);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    // 技工资金充值 易联易联支付平台
    public function yilianMoneyRecharge()
    {
        try {
            $id = $this->requireAuth([
                AuthService::ROLE_FACTORY,
                AuthService::ROLE_FACTORY_ADMIN,
                // AuthService::ROLE_ADMIN
            ]);

            $factory = [];

            $amount = number_format(I('get.money'), 2, '.', '');
            $remark = htmlEntityDecode(I('get.remark', ''));
            $url = urldecode(htmlEntityDecode(I('get.url', '')));

            $amount <= 0 && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '金额不能必须小于 0.00');

            switch (AuthService::getModel()) {
                case AuthService::ROLE_FACTORY:
                    $factory = AuthService::getAuthModel();
                    $user_type = PayPlatformRecordService::USER_TYPE_FACTORY;
                    break;

                case AuthService::ROLE_FACTORY_ADMIN:
                    $factory = BaseModel::getInstance(self::FACTORY_TABLE_NAME)
                        ->getOneOrFail(AuthService::getAuthModel()->factory_id);
                    $user_type = PayPlatformRecordService::USER_TYPE_FACTORY_ADMIN;
                    break;

                // case AuthService::ROLE_ADMIN
                //     $operator_type = 3;
                //     break;
            }
            PayService::createOutOrderNo($no);

            if ($no) {
                M()->startTrans();
                $pay_type = PayService::PAY_TYPE_FACTORY_MONEY_RECHARGE;

                BaseModel::getInstance(self::PAY_RECORD_TABLE_NAME)->insert([
                    'platform_type' => PayService::PLATFORM_TYPE_YILIAN_VALUE,
                    'out_order_no'  => $no,
                    'money'         => $amount,
                    'pay_type'      => $pay_type,
                    'data_id'       => 0,
                    'user_id'       => $id,
                    'user_type'     => $user_type,
                    'status'        => KeyConfigService::ORDER_STATUS_NOT_PAY,
                    'remark'        => $remark,
                    // 'url'           => urlencode($url);
                    'syn_url'       => $url,
                    'create_time'   => NOW_TIME,
                ]);

                $data = [
                    'amount'            => $amount,
                    'description'       => '厂家资金充值',
                    'remark'            => $remark,
                    'yilian_pay_number' => $no,
                    'syn_address'       => Util::getServerUrl() . __APP__ . '/ylsyn/' . $pay_type, // 同步通知接口
                    'asyn_address'      => Util::getServerUrl() . __APP__ . '/ylasyn/' . $pay_type, // 异步通知接口
                ];
                M()->commit();
                PayService::initDiy(PayService::PLATFORM_TYPE_YILIAN_VALUE)
                    ->createOrder($data);
            }
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function all()
    {
        try {
            $this->requireAuth('admin');

            $where = [];
            if ($name = I('name')) {
                $where['factory_short_name'] = ['LIKE', "%{$name}%"];
            }
            $factories = BaseModel::getInstance('factory')->getList([
                'field' => 'factory_id,factory_short_name',
                'where' => $where,
                'order' => 'factory_id ASC',
            ]);

            $this->responseList($factories);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getList()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);

            $name = I('name');

            $where = [];
            if (strlen($name) > 0) {
                $where['factory_full_name'] = ['LIKE', "%{$name}%"];
            }

            $model = BaseModel::getInstance('factory');
            $factories = $model->getList([
                'field' => 'factory_id,factory_short_name,factory_full_name',
                'where' => $where,
                'order' => 'factory_id',
                'limit' => $this->page(),
            ]);

            $cnt = $model->getNum($where);

            $this->paginate($factories, $cnt);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getFactoryServiceTypes()
    {
        try {
            $factory = $this->requireAuthFactoryGetFactory();

            $service_types = OrderService::getFactoryServiceByTypes($factory['factory_service_model']);

            $this->responseList($service_types);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function factoryGroup()
    {

        $this->requireAuth('admin');

        $list = (new FactoryLogic())->getFactoryGroup();

        return $this->responseList($list);

    }

    public function getFactoryTags()
    {
        try {
            $factory = $this->requireAuthFactoryGetFactory();
            $factory_tags = (new FactoryLogic())->getFactoryTags($factory);

            $this->responseList($factory_tags);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function info()
    {
        try {
//            $this->requireAuth('factory');
//            $f = AuthService::getAuthModel();
            $f = $this->requireAuthFactoryGetFactory();
            $info = [
                'factory_id'         => $f['factory_id'],
                'factory_type'       => $f['factory_type'],
                'code'               => $f['code'],
                'factory_full_name'  => $f['factory_full_name'],
                'factory_short_name' => $f['factory_short_name'],
                //                'factory_id'        => $f['factory_id'],
            ];

            $this->response($info);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getYimaAppliesBindProduct()
    {
        try {
            $token_id = $this->requireAuthFactoryGetFid();
            $model = new \Admin\Model\FactoryProductQrcodeModel();
            $where = [];

            // $count = $model->getYimaAppliesBindProductByFid($token_id, $where, 'count');
            // !$count && $this->paginate($list, $count);

            $field = 'FP.product_id,FP.factory_id,FP.product_xinghao,FP.product_category,FP.product_guige,FP.product_brand';
            $list = $model->getYimaAppliesBindProductByFid($token_id, $where, $field);

            $categories = $guiges = $brands = [];
            foreach ($list as $k => $v) {
                $categories[$v['product_category']] = $v['product_category'];
                $guiges[$v['product_guige']] = $v['product_guige'];
                $brands[$v['product_brand']] = $v['product_brand'];
            }

            $cm_list = implode(',', array_filter($categories)) ? BaseModel::getInstance('cm_list_item')
                ->getList([
                    'where' => [
                        'list_item_id' => ['in', implode(',', array_filter($categories))],
                    ],
                    'index' => 'list_item_id',
                ]) : [];

            $guige_list = implode(',', array_filter($guiges)) ? BaseModel::getInstance('product_standard')
                ->getList([
                    'where' => [
                        'standard_id' => ['in', implode(',', array_filter($guiges))],
                    ],
                    'index' => 'standard_id',
                ]) : [];
            $brand_list = implode(',', array_filter($brands)) ? BaseModel::getInstance('factory_product_brand')
                ->getList([
                    'where' => [
                        'id' => ['in', implode(',', array_filter($brands))],
                    ],
                    'index' => 'id',
                ]) : [];

            foreach ($list as $k => $v) {
                $v['product_category_desc'] = $cm_list[$v['product_category']]['item_desc'];
                $v['product_guige_desc'] = $guige_list[$v['product_guige']]['standard_name'];
                $v['product_brand_desc'] = $brand_list[$v['product_brand']]['product_brand'];
                $list[$k] = $v;
            }

            // $this->paginate($list, $count);
            $this->responseList($list);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getYimaAppliesBindGuiges()
    {
        $cate_id = I('get.cate_id', 0);
        try {
            $token_id = $this->requireAuthFactoryGetFid();
            $model = new \Admin\Model\FactoryProductQrcodeModel();
            $where = [];

            if ($cate_id) {
                $where['FP.product_category'] = $cate_id;
            }

            // $count = $model->getYimaAppliesBindProductByFid($token_id, $where, 'count', 2);
            // !$count && $this->paginate($list, $count);

            $field = 'FP.product_guige,FP.product_category';
            $list = $model->getYimaAppliesBindProductByFid($token_id, $where, $field, 2);

            $guige_id = arrFieldForStr($list, 'product_guige');

            $guiges = $guige_id ? BaseModel::getInstance('product_standard')
                ->getList([
                    'where' => [
                        'standard_id' => ['in', $guige_id],
                    ],
                    'field' => 'standard_id,standard_name',
                    'index' => 'standard_id',
                ]) : [];

            foreach ($list as $k => $v) {
                $list[$k]['product_guige_desc'] = $guiges[$v['product_guige']]['standard_name'];
            }

            // $this->paginate($list, $count);
            $this->responseList($list);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getYimaAppliesBindCategory()
    {
        try {
            $token_id = $this->requireAuthFactoryGetFid();

            $datas = (new \Admin\Model\FactoryModel())->factoryYimaApplyCategory($token_id, 'FP.product_category as cate_id,FPQ.datetime bing_time');
            $cate_ids = arrFieldForStr($datas, 'cate_id');
            $cates = $cate_ids ? BaseModel::getInstance('cm_list_item')
                ->getList([
                    'where' => [
                        'list_item_id' => ['in', $cate_ids],
                    ],
                    'index' => 'list_item_id',
                ]) : [];

            foreach ($datas as $k => $v) {
                $v['cate_name'] = $cates[$v['cate_id']]['item_desc'];
                $datas[$k] = $v;
            }

            $this->responseList($datas);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function yimaAppliesGetInfo()
    {
        $id = I('get.id', 0);
        try {
            $token_id = $this->requireAuth(['factory', 'admin']);
            $token_type = AuthService::getModel();
            //             if (!in_array($token_type, ['factory', 'admin'])) {
            //                 $this->throwException(ErrorCode::SYS_NOT_POWER);
            //             }

            $factory_info = [
                'factory_id'         => null,
                'factory_status'     => null,
                'factory_type'       => null,
                'code'               => null,
                'factory_short_name' => null,
                'factory_full_name'  => null,
                'linkphone'          => null,
                'linkman'            => null,
                'group_id'           => null,
            ];

            $where = $id;
            if ($token_type == 'factory') {
                $where = [
                    'excel_id'   => $id,
                    'factory_id' => $token_id,
                ];
                $factory_info = [
                    'factory_id'         => AuthService::getAuthModel()->factory_id,
                    'factory_status'     => AuthService::getAuthModel()->factory_status,
                    'factory_type'       => AuthService::getAuthModel()->factory_type,
                    'code'               => AuthService::getAuthModel()->code,
                    'factory_short_name' => AuthService::getAuthModel()->factory_short_name,
                    'factory_full_name'  => AuthService::getAuthModel()->factory_full_name,
                    'linkphone'          => AuthService::getAuthModel()->linkphone,
                    'linkman'            => AuthService::getAuthModel()->linkman,
                    'group_id'           => AuthService::getAuthModel()->group_id,
                ];
            }

            $data = BaseModel::getInstance('factory_excel')
                ->getOneOrFail($where);

            if (!$factory_info['factory_id']) {
                $data['factory_info'] = array_intersect_key(BaseModel::getInstance('factory')
                    ->getOne($data['factory_id']), $factory_info);
            } else {
                $data['factory_info'] = $factory_info;
            }

            $type_ids = implode(',', array_filter([$data['qr_type'], $data['qr_guige']]));
            $yima_qr = $type_ids ? BaseModel::getInstance('yima_qr_category')
                ->getList([
                    'where' => [
                        'id' => ['in', $type_ids],
                    ],
                    'index' => 'id',
                ]) : [];

            $data['qr_type_desc'] = $yima_qr[$data['qr_type']]['title'];
            $data['qr_guige_desc'] = $yima_qr[$data['qr_guige']]['title'];

            $list = BaseModel::getInstance('factory_product_qrcode')->getList([
                'where' => [
                    'factory_excel_id' => $data['excel_id'],
                ],
                'field' => 'id,product_id,factory_id,plan_id,qr_first_int,qr_last_int,is_used,nums,shengchan_time,chuchang_time,zhibao_time,active_json,datetime',
                'order' => 'qr_first_int ASC',
            ]);

            $result = (new \Admin\Logic\YimaLogic())->yimaCodeNumsBindInfoCount($data, $list);

            $data['not_bind_nums'] = $result['not_bind_nums'];
            $data['bind_nums'] = $result['bind_nums'];
            $data['bind_list'] = $result['bind_list'];

            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function yimaAppliesBindInfoCheck()
    {
        $this->yimaAppliesBindInfo();
    }

    public function yimaAppliesBindInfo()
    {
        // die(AuthCode::encrypt(json_encode(['user_id' => '28624','type' => 'wxuser']), C('TOKEN_CRYPT_CODE'), 0));
        // die(AuthCode::encrypt(json_encode(['user_id' => '567','type' => 'factory']), C('TOKEN_CRYPT_CODE'), 0));
        // $id         = I('post.excel_id', 0);
        // $nums       = I('post.nums', 0);
        // $first_code = I('post.first_code', 0);
        // $last_code  = I('post.last_code', 0);
        $id = I('post.excel_id', 0);
        $nums = I('post.nums', 0);
        $first_code = I('post.first_code', 0, 'intval');
        $last_code = I('post.last_code', 0, 'intval');
        $product_id = I('post.product_id', 0);
        $cate_id = I('post.cate_id', 0);
        $guige_id = I('post.guige_id', 0);
        $brand_id = I('post.brand_id', 0);
        $shengchan_time = I('post.shengchan_time', 0);
        $chuchang_time = I('post.chuchang_time', 0);
        $zhibao_time = I('post.zhibao_time', 0);
        $remarks = I('post.remarks', '');
        $diy_remarks = I('post.diy_remarks', '');
        $is_check = I('post.is_check', '');
        // 激活策略 is_active_type 允许激活身份 is_order_type 允许报修身份 active_credence_day 提供购买凭证 cant_active_credence_day 禁止激活 active_reward_moth 激活赠送延保
        $active_arr = [
            'is_active_type'           => I('post.is_active_type', ''),                      // 1,2  1消费者， 2经销商
            'is_order_type'            => I('post.is_order_type', ''),                       // 1,2  1消费者， 2经销商
            'active_credence_day'      => I('post.active_credence_day', 0, 'intval'),        // 需要上传发票   单位天
            'cant_active_credence_day' => I('post.cant_active_credence_day', 0, 'intval'),   // 禁止激活产品   单位天
            'active_reward_moth'       => I('post.active_reward_moth', 0, 'intval'),          // 激活赠送延保   单位天
        ];
        // TODO 因为忙着其他事，误以为这个就是logic  所以写到了这里，后续迁移  
        try {
            $fid = $this->requireAuthFactoryGetFid();
            // e - s + 1 = n
            // s = e - n + 1
            // e = n - 1 - s
            if (!$first_code) {
                $first_code = $last_code - $nums + 1;
            } elseif (!$last_code) {
                $last_code = $first_code + $nums - 1;
            } elseif (!$nums && $first_code && $last_code) {
                $nums = $last_code - $first_code + 1;
            }
            
            // 'excel_id' => $id,
            $data = BaseModel::getInstance('factory_excel')->getOne([
                'first_code' => ['ELT', $first_code],
                'last_code'  => ['EGT', $last_code],
                'factory_id' => $fid,
            ]);

            if (!$data['excel_id']) {
                $this->throwException(ErrorCode::SYS_DATA_NOT_EXISTS, '请输入正确的码段');
            } elseif ($data['is_check'] == 0) {
                $this->throwException(ErrorCode::NOT_CHECK);
            } elseif ($data['is_check'] == 2) {
                $this->throwException(ErrorCode::IS_NOT_CHECK_WRONG);
            }

            // if (!$cate_id || !$guige_id || !$brand_id) {
            //     $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '产品数据不能为空');
            // } else
            if ((!$first_code && !$last_code) || $nums <= 0 || ($first_code + $nums - 1) != $last_code || $first_code < $data['first_code'] || $last_code > $data['last_code']) {
                $this->throwException(ErrorCode::BIND_BEEN_CODE_IS_WRONG);
            } elseif (!$shengchan_time) {
                $this->throwException(ErrorCode::SHENGCHAN_TIME_NOT_EMPTY);
            } elseif (!$chuchang_time) {
                $this->throwException(ErrorCode::CHUCHANG_TIME_NOT_EMPTY);
            } elseif ($chuchang_time < $shengchan_time) {
                $this->throwException(ErrorCode::CHUCHANG_TIME_NOT_LT_SHENGCHAN_TIME);
            } elseif (!BaseModel::getInstance('factory_product')
                ->getOne($product_id)
            ) {
                $this->throwException(ErrorCode::SYS_DATA_NOT_EXISTS, '产品型号不能为空/数据不存在');
            }
            // elseif (!BaseModel::getInstance('cm_list_item')->getOne($cate_id) || !BaseModel::getInstance('product_standard')->getOne($guige_id) || !BaseModel::getInstance('factory_product_brand')->getOne($brand_id)) {
            //     $this->throwException(ErrorCode::SYS_DATA_NOT_EXISTS, '产品数据不能为空/数据不存在');
            // }

            $id = $data['excel_id'];

            $model = BaseModel::getInstance('factory_product_qrcode');

            // $othors = $model->getList(['factory_excel_id' => $id]);
            $othors = $model->getList([
                // 'qr_first_int' => ['EGT', $first_code],
                // 'qr_last_int' => ['ELT', $last_code],
                '_string' => " (qr_first_int <= {$first_code} AND qr_last_int >= {$first_code}) OR ((qr_first_int <= {$last_code} AND qr_last_int >= {$last_code})) OR factory_excel_id = {$id} ",

                'factory_id' => $fid,
            ]);
            $all_count = 0;
            foreach ($othors as $k => $v) {
                if (($first_code <= $v['qr_first_int'] && $v['qr_first_int'] <= $last_code) || ($first_code <= $v['qr_last_int'] && $v['qr_last_int'] <= $last_code)) {
                    $this->throwException(ErrorCode::BIND_BEEN_CODE_IS_USED);
                }
                $all_count += $v['nums'];
            }

            if (($data['nums'] - $all_count) < $nums) {
                $this->throwException(ErrorCode::BIND_NUMS_IS_FULL);
            }

            if ($is_check == 'is_check') {
                $this->okNull();
            }

            $add = [
                'product_id'       => $product_id,
                'factory_id'       => $fid,
                'factory_code'     => AuthService::getAuthModel()->factory_type . AuthService::getAuthModel()->code,
                'nums'             => $nums,
                'qr_first_int'     => $first_code,
                'qr_last_int'      => $last_code,
                'factory_excel_id' => $id,
                'shengchan_time'   => $shengchan_time,
                'chuchang_time'    => $chuchang_time,
                'zhibao_time'      => $zhibao_time,
                'remarks'          => $remarks,
                'diy_remarks'      => $diy_remarks,
                'active_json'      => json_encode($active_arr, JSON_UNESCAPED_UNICODE),
                'datetime'         => NOW_TIME,
            ];

            $bind_where = [
                'factory_id'                => $fid,
                'water'                     => ['BETWEEN', "{$first_code},{$last_code}"],
                'register_time'             => ['GT', 0],
                'factory_product_qrcode_id' => 0,
            ];
            $yima_model = BaseModel::getInstance(factoryIdToModelName($fid));
            $bind_li_nums = $yima_model->getNum($bind_where);
            $bind_li = $bind_li_nums ? $yima_model->getList([
                'field' => 'code',
                'where' => $bind_where,
                'limit' => 5,
            ]) : null;
            $result = [
                'codes' => arrFieldForStr($bind_li, 'code'),
                'nums'  => (int)$bind_li_nums,
            ];

            $model->insert($add);

            $cache = [
                'user_type'    => AuthService::getModel(),
                'user_id'      => AuthService::getAuthModel()->factory_id,
                'content_type' => 1,
            ];
            $cache_data = array_merge($cache, [
                'content'     => $add['active_json'],
                'update_time' => NOW_TIME,
            ]);
            $cache_model = BaseModel::getInstance('data_last_cache');
            if ($cache_model->getOne($cache)) {
                BaseModel::getInstance('data_last_cache')
                    ->update($cache, $cache_data);
            } else {
                BaseModel::getInstance('data_last_cache')->insert($cache_data);
            }

            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function cancelYimaApplies()
    {
        $id = I('get.id', 0);
        try {
            $fid = $this->requireAuth('factory');
            //     AuthService::getModel() != 'factory'
            // &&  $this->throwException(ErrorCode::NOT_FACTORY);

            $model = BaseModel::getInstance('factory_excel');
            $data = $model->getOneOrFail([
                'excel_id'   => $id,
                'factory_id' => $fid,
                'is_check'   => 0,
            ]);
            $model->update($id, [
                'is_check'   => 3,
                'check_time' => NOW_TIME,
            ]);
            // event(new FactoryCancelExcdeApplyEvent($id));
            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function yimaApplies()
    {
        $export = I('get.is_export', 0) == 1 ? true : false;
        $factory_id = I('get.factory_id', '');
        try {
            $fcode = '';
            $where = $fdata = [];
            $id = $this->requireAuth();

            if (AuthService::getModel() == 'factory') {
                $where['factory_id'] = $id;
                $fdata = AuthService::getAuthModel();
            } elseif (!empty($factory_id)) {
                $where['factory_id'] = $factory_id;
                $fdata = BaseModel::getInstance('factory')->getOne($factory_id);
            }
            $fcode = $fdata['factory_type'] . $fdata['code'];

            $model = BaseModel::getInstance('factory_excel');
            $count = $model->getNum($where);
            if (!$count && !$export) {
                $this->paginate();
            }
            $opt = [
                // 'field' => '*,CONCAT_WS(",",`qr_type`,`qr_guige`) as qr_ids',
                'field' => '*',
                'where' => $where,
                'limit' => getPage(),
                'order' => 'add_time DESC',
            ];

            if ($export) {
                unset($opt['limit']);
                $excel_ids = implode(',', array_filter(explode(',', I('excel_ids', ''))));
                $excel_ids && ($opt['where']['excel_id'] = ['in', $excel_ids]);
            }

            $list = $model->getList($opt);

            $f_ids = $qr_ids = $excel_ids = [];
            foreach ($list as $k => $v) {
                $qr_ids[$v['qr_type']] = $v['qr_type'];
                $qr_ids[$v['qr_guige']] = $v['qr_guige'];
                $excel_ids[$v['excel_id']] = $v['excel_id'];
                $f_ids[$v['factory_id']] = $v['factory_id'];

            }
            $qr_ids = implode(',', array_filter($qr_ids));
            $excel_ids = implode(',', array_filter($excel_ids));
            // $qr_ids    = arrFieldForStr($list, 'qr_ids');
            // $excel_ids = arrFieldForStr($list, 'excel_id');
            // $qr_ids    = implode(',', array_filter(explode(',', $qr_ids)));

            $factorys = $f_ids ? BaseModel::getInstance('factory')->getList([
                'where' => [
                    'factory_id' => ['in', $f_ids],
                ],
                'field' => 'factory_id,linkman,linkphone',
                'index' => 'factory_id',
            ]) : [];

            $count_qr_code = $excel_ids ? BaseModel::getInstance('factory_product_qrcode')
                ->getList([
                    'where' => [
                        'factory_excel_id' => ['in', $excel_ids],
                    ],
                    'field' => 'factory_excel_id,SUM(nums) as all_nums',
                    'group' => 'factory_excel_id',
                    'index' => 'factory_excel_id',
                ]) : [];                

            $qr_type_list = $qr_ids ? BaseModel::getInstance('yima_qr_category')
                ->getList([
                    'where' => [
                        'id' => ['in', $qr_ids],
                    ],
                    'index' => 'id',
                ]) : [];

            foreach ($list as $k => $v) {
                $v['first_code_full'] = $fcode && $v['first_code'] ? $fcode . $v['first_code'] : $v['first_code'];
                $v['last_code_full'] = $fcode && $v['last_code'] ? $fcode . $v['last_code'] : $v['last_code'];

                $v['linkman'] = $factorys[$v['factory_id']]['linkman'];
                $v['linkphone'] = $factorys[$v['factory_id']]['linkphone'];
                $v['factoory_admin'] = '0';
                $bind_nums = $count_qr_code[$v['excel_id']]['all_nums'] ? $count_qr_code[$v['excel_id']]['all_nums'] : 0;
                // unset($v['qr_ids']);
                $v['qr_type_desc'] = $qr_type_list[$v['qr_type']]['title'];
                $v['qr_guige_desc'] = $qr_type_list[$v['qr_guige']]['title'];
                $v['not_bind_nums'] = ($v['is_check'] == 1) ? (string)($v['nums'] - $bind_nums) : '——';
                $v['bind_nums'] = $bind_nums;

                if ($export) {
                    $v['add_time'] = date('Y-m-d H:i:s', $v['add_time']);
                    $v['qr_guige_type'] = $v['qr_type_desc'] . ' ' . $v['qr_guige_desc'];
                    $v['remarks'] = $v['remarks'] ? $v['remarks'] : '——';
                    $v['start_end_code'] = $v['first_code'] ? $v['first_code_full'] . '-' . $v['last_code_full'] : '——';
                }

                $list[$k] = $v;
            }

            if ($export) {
                (new \Admin\Logic\YimaLogic())->excelYimaApplys($list);
            }

            $this->paginate($list, $count);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function addYimaApply()
    {
        try {
            $product_id = I('post.product_id', 0);
            $nums = I('post.nums', 0);
            $type = I('post.type', 0);
            $guige = I('post.guige', 0);
            $remarks = I('post.remarks', '');
            if ($nums > 20000 || !$nums) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
            }

            $fid = $this->requireAuth('factory');

            $model = BaseModel::getInstance('factory_excel');
            $add = [
                'factory_id' => $fid,
                'product_id' => 0,
                'nums'       => $nums,
                'add_time'   => NOW_TIME,
                'qr_type'    => $type,
                'qr_guige'   => $guige,
                'remarks'    => $remarks,
            ];

            $id = $model->insert($add);
            event(new CreateFactoryExcdeApplyEvent($id));
            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function factoryOrderServiceType()
    {
        try {
            $this->requireAuth('factory');
            //     'factory' !== AuthService::getModel()
            // &&  $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请求参数类型错误');

            $type = I('get.type', 0);
            $factory_service_model = array_filter(explode(',', AuthService::getAuthModel()->factory_service_model));

            // 没有设置则默认 
            if (!count($factory_service_model)) {
                $factory_service_model = [106, 107];
            }

            $field = 'list_item_id as id,0 as is_selected,item_desc as title,item_parent as parent_id,item_thumb as img,"" as img_full,item_sort as sort,lat,lng';

            $where = [];
            if ($type == 1) {
                $field = 'list_item_id as id,1 as is_selected,item_desc as title,item_parent as parent_id,item_thumb as img,"" as img_full,item_sort as sort,lat,lng';
                $where['list_item_id'] = ['in', implode(',', $factory_service_model)];
            }

            $list = D('CmListItem')->getOrderServices($where, $field);

            foreach ($list as $k => $v) {
                if (in_array($v['id'], $factory_service_model)) {
                    $v['is_selected'] = 1;
                }

                $v['img_full'] = $v['img'] ? Util::getServerFileUrl($v['img']) : '';
                $list[$k] = $v;
            }

            $this->responseList($list);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function technology()
    {
        try {
            $this->requireAuth();
            $factory = AuthService::getAuthModel();
            $technologies = D('Factory', 'Logic')->technology($factory['factory_id']);

            $this->responseList($technologies);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function addDealer()
    {
        try {
            $this->requireAuth('factory');

            $factory = AuthService::getAuthModel();

            $data = [
                'factory_id' => $factory['factory_id'],
                'name'       => I('name'),
                'user_name'  => I('phone'),
                'desc'       => I('desc', ''),
                'status'     => 1, // 默认为已授权（已启用）
            ];
            if (!Util::isPhone($data['user_name'])) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '手机号码格式有误,请检查~');
            } elseif (!$data['name']) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请填写经销商姓名');
            }
            $user = BaseModel::getInstance('wx_user')
                ->getOne(['telephone' => $data['user_name']], 'id,user_type');
            if ($user) {
                if ($user['user_type'] == 0) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该手机号码已被注册成为消费者，不允许再次添加为经销商');
                }

                $dealer_model = BaseModel::getInstance('factory_product_white_list');

                if ($dealer_model->getNum(['factory_id' => $factory['factory_id'], 'user_name' => $data['user_name']]) > 0) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该账号已在您的经销商列表中');
                }

                $dealer_info = BaseModel::getInstance('dealer_info')
                    ->getOne(['wx_user_id' => $user['id']], 'id,name');

                if ($dealer_info) {
                    $data['name'] = $dealer_info['name'];
                }

                $dealer_model->insert($data);

                if ($dealer_info) {
                    $this->response('经销商添加成功，由于该经销商账号已在系统中有注册记录，现将其资料覆盖您填写的资料信息');
                } else {
                    $this->response('经销商添加成功');
                }
            } else {
                $dealer_model = BaseModel::getInstance('factory_product_white_list');
                if ($dealer_model->getNum(['factory_id' => $factory['factory_id'], 'user_name' => $data['user_name']]) > 0) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '该账号已在您的经销商列表中');
                }

                $dealer_model->insert($data);

                $this->response();
            }

            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function dealerList()
    {
        try {
//            $this->requireAuth('factory');
            $factory = $this->requireAuthFactoryGetFactory();

            $dealer_info = D('Factory', 'Logic')->dealerList($factory['factory_id']);

            $this->paginate($dealer_info['list'], $dealer_info['count']);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function showDealer()
    {
        try {
            $this->requireAuth();
            $id = I('get.id');

            $dealer_info = D('Factory', 'Logic')->showDealer($id);

            $this->response($dealer_info);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function updateDealer()
    {
        try {
            $this->requireAuthFactoryGetFactory();

            $id = I('get.id');

            $data = [
                'status' => I('status'),
            ];

            if (!in_array($data['status'], [0, 1, 2])) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '审核状态错误,请检查~');
            }

            $type = I('type', 1);
            if ($type == 2) {
                $data['desc'] = I('desc');
            }

            D('Factory', 'Logic')->updateDealer($id, $data);

            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getDealerActiveRecord()
    {
        try {
            $this->requireAuth();
            $factory = AuthService::getAuthModel();

            $id = I('get.id');

            $record_info = D('Factory', 'Logic')->getDealerActiveRecord($id, $factory['factory_id']);

            $this->paginate($record_info['list'], $record_info['count']);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function importDealer()
    {
        try {
            $this->requireAuth('factory');

            $factory = AuthService::getAuthModel();

            $config['exts'] = ['xls', 'xlsx'];
            $file_info = Util::upload($config);

            $objPHPExcelReader = \PHPExcel_IOFactory::load($file_info['file_path']);

            $sheet = $objPHPExcelReader->getSheet();

            $max_num = 10000;   // 暂时设定为10000
            $excel_data = [];
            $count = 0;
            foreach ($sheet->getRowIterator() as $key => $row) {
                // 防止导入内容过多时不读取完直接返回错误
                if ($key > ($max_num << 2)) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, "导入数量过多,每次最多导入{$max_num}条数据");
                }
                if ($row->getRowIndex() < 2) {
                    continue;
                }

                $it = $row->getCellIterator();
                $it->rewind();
                $name = $it->current()->getValue();
                $it->next();
                $phone = $it->current()->getValue();
                $it->next();
                $desc = $it->current()->getValue() ?: '';
                if (!$name && !$phone && !$desc) {
                    break;  // 所有数据都为空则不再向下读取
                }
                if (!$name || !$phone) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '导入的经销商中，部分字段没有填写完整，请检查文件后重新导入');
                } elseif (!Util::isPhone($phone)) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '导入的经销商中，存在手机号码有误，请检查文件后重新导入');
                }
                $excel_data[] = [
                    'name'      => $name,
                    'user_name' => $phone,
                    'desc'      => $desc,
                ];

                ++$count;
            }
            if ($count > $max_num) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, "导入数量过多,每次最多导入{$max_num}条数据");
            }

            $phone_list = Arr::pluck($excel_data, 'user_name');
            $users = BaseModel::getInstance('wx_user')->getFieldVal([
                'telephone' => ['IN', $phone_list],
            ], 'telephone,id,user_type');
            foreach ($users as $p => $user) {
                if ($user['user_type'] == 0) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, "手机号码{$p}已被注册成为消费者，不允许再次添加为经销商");
                }
            }

            $dealer_model = BaseModel::getInstance('factory_product_white_list');
            $dealer_phone_info_map = $dealer_model->getFieldVal([
                'factory_id' => $factory['factory_id'],
                'user_name'  => ['IN', $phone_list],
            ], 'user_name,id,status');


            $dealer_model->startTrans();
            foreach ($excel_data as $item) {
                if (isset($dealer_phone_info_map[$item['user_name']])) {    // 已存在该经销商
                    $dealer = $dealer_phone_info_map[$item['user_name']];

                    $update_data = [];
                    if (!isset($users[$item['user_name']])) {    // 未注册成为经销商
                        $update_data['name'] = $item['name'];
                        $update_data['desc'] = $item['desc'];
                        $update_data['status'] = 1;
                    }
                    if ($update_data) {
                        $dealer_model->update($dealer['id'], $update_data);
                    }
                } else {
                    $insert_data = [
                        'factory_id' => $factory['factory_id'],
                        'user_name'  => $item['user_name'],
                        'name'       => $item['name'],
                        'desc'       => $item['desc'],
                        'status'     => 1,
                    ];
                    $dealer_model->insert($insert_data);
                }
            }
            $dealer_model->commit();

            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function yimaAppliesAndBind()
    {
        $id = I('get.id', 0);
        $start = I('get.start', 0, 'intval');
        $end = I('get.end', 0, 'intval');
        $model = BaseModel::getInstance('factory_product_qrcode');
        try {
            if ($end < $start || $end <= 0 || $start <= 0) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
            }
            $list = $model->getList([
                'where' => [
                    'factory_id' => $id,
                    '_complex'   => [
                        '_logic'                   => 'or',
                        'qr_first_int|qr_last_int' => ['BETWEEN', "{$start},{$end}"],
                        '_string'                  => " (qr_first_int <= {$start} AND {$end} <= qr_last_int) ",
                    ],
                ],
                'field' => 'id,qr_first_int,qr_last_int,"" as bind_codes,0 as bind_nums',
                'index' => 'id',
            ]);

            $qr_ids = implode(',', array_keys($list));

            if ($qr_ids) {
                $binds = BaseModel::getInstance(factoryIdToModelName($id))
                    ->getList([
                        'field' => "factory_product_qrcode_id,substring_index(group_concat(code),',',5) as codes,count(*) as bind_nums",
                        'where' => [
                            'factory_id'                => $id,
                            'factory_product_qrcode_id' => ['in', $qr_ids],
                            'register_time'             => ['GT', 0],
                        ],
                        'group' => 'factory_product_qrcode_id',
                    ]);
                foreach ($binds as $k => $v) {
                    $list[$v['factory_product_qrcode_id']]['bind_codes'] = $v['codes'];
                    $list[$v['factory_product_qrcode_id']]['bind_nums'] = $v['bind_nums'];
                }
            }

            $this->responseList(array_values($list));
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function moneyFrozenThawRecord()
    {
        $fid = I('get.id', 0, 'intval');
        $start_time = I('get.start_time', 0);
        $end_time = I('get.end_time', 0);
        $orno = I('get.orno', '');

        try {
            $id = $this->requireAuth([AuthService::ROLE_ADMIN, AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN]);

            switch (AuthService::getModel()) {
                case AuthService::ROLE_FACTORY:
                    $fid != BaseModel::getInstance('factory')->getFieldVal(AuthService::getAuthModel()->factory_id, 'factory_id') &&
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
                    break;

                case AuthService::ROLE_FACTORY:
                    $fid != AuthService::getAuthModel()->getPrimaryValue() && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
                    break;
            }

            $search = [];

            $start_time && $search['start_time'] = (new Carbon(date('Y-m-d', $start_time)))->timestamp;
            $end_time && $search['end_time'] = (new Carbon(date('Y-m-d', $end_time)))->addDay(1)->timestamp - 1;
            !empty($orno) && $search['orno'] = $orno;

            $logic = new FactoryLogic();
            $arr = $logic->factoryMoneyFrozenThawRecord($fid, $search);

            $this->paginate($arr['list'], $arr['num'], ['total_money' => $arr['total_money']]);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}
