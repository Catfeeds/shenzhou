<?php
/**
 * File: PayLogic.class.php
 * Function:
 * User: sakura
 * Date: 2018/4/13
 */

namespace Qiye\Logic;


use Common\Common\Service\AuthService;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\OrderService;
use Common\Common\Service\OrderUserService;
use Common\Common\Service\PayPlatformRecordService;
use Common\Common\Service\OrderSettlementService;
use Common\Common\Service\PayService;
use Common\Common\Service\WorkerOrderOutWorkerAddFeeService;
use EasyWeChat\Foundation\Application;
use EasyWeChat\Payment\Order;
use Qiye\Common\ErrorCode;
use Qiye\Model\BaseModel;

class PayLogic extends BaseLogic
{

    public function pay($worker_order_id, $param = [])
    {
        $url = empty($param['url']) ? '' : $param['url'];

        //获取工单
        $field = 'worker_order_type,orno';
        $order = BaseModel::getInstance('worker_order')
            ->getOneOrFail($worker_order_id, $field);
        if (in_array($order['worker_order_type'], OrderService::ORDER_TYPE_IN_INSURANCE_LIST)) {
            $this->throwException(ErrorCode::ORDER_IS_INSURANCE_NOT_USER_PAY);
        }

        //获取加收费用单
        $opts = [
            'field' => 'id,total_fee_modify,is_add_fee,out_order_no,pay_type,pay_time',
            'where' => [
                'worker_order_id' => $worker_order_id,
            ],
            'order' => 'create_time desc,id desc',
            'limit' => 2,
        ];
        $fee_model = BaseModel::getInstance('worker_order_out_worker_add_fee');
        $add_fees = $fee_model->getList($opts);

        if (empty($add_fees)) {
            $this->throwException(ErrorCode::SYS_DATA_NOT_EXISTS, '加收记录不存在');
        }

        $add_fee_cnt = count($add_fees);

        $add_fee = null;
        if (1 == $add_fee_cnt) {
            $add_fee = $add_fees[0];
            $out_order_no = $add_fee['out_order_no'];

            if (0 < $add_fee['pay_time']) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '已支付');
            }

        } else {
            $prev_add_fee = $add_fees[1];

            if (WorkerOrderOutWorkerAddFeeService::PAY_TYPE_WORKER_WX != $prev_add_fee['pay_type']) {
                $this->throwException(ErrorCode::ORDER_OUT_WARRANTY_PAY_TYPE_NOT_FIT);
            }

            if (0 >= $prev_add_fee['pay_time']) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '多个加收记录未支付');
            }

            $add_fee = $add_fees[0];
            $out_order_no = $add_fee['out_order_no'];

            if (0 < $add_fee['pay_time']) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '已支付');
            }
        }

        if (!empty($out_order_no)) {
            $record = BaseModel::getInstance('pay_platform_record')
                ->getOne([
                    'out_order_no'  => $out_order_no,
                    'platform_type' => PayService::PLATFORM_TYPE_WECHAT_VALUE,
                ], 'status');
            if (!empty($record) && PayService::WECHAT_PAY_STATUS_SUCCESS == $record['status']) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '加收费用单已支付');
            }
        }

        $total_fee_modify = $add_fee['total_fee_modify'];
        if ($total_fee_modify <= 0) {
            $this->throwException(ErrorCode::SYS_SYSTEM_ERROR, '费用不能小于等于0');
        }

        $out_trade_no = '';
        PayService::createOutOrderNo($out_trade_no);

        M()->startTrans();

        $pay_log = [
            'platform_type' => PayService::PLATFORM_TYPE_WECHAT_VALUE,
            'out_order_no'  => $out_trade_no,
            'money'         => $total_fee_modify,
            'pay_type'      => PayPlatformRecordService::PAY_TYPE_ORDER_WECHAT_PAY,
            'data_id'       => $worker_order_id,
            'user_id'       => AuthService::getAuthModel()
                ->getPrimaryValue(),
            'user_type'     => PayPlatformRecordService::USER_TYPE_WORKER,
            'status'        => PayService::WECHAT_STATUS_NOT_PAY,
            'pay_ment'      => PayService::PAYMENT_WXPAY,
            'create_time'   => NOW_TIME,
            'syn_url'       => urldecode($url),
        ];
        BaseModel::getInstance('pay_platform_record')->insert($pay_log);

        if (1 == $add_fee_cnt) {
            BaseModel::getInstance('worker_order_user_info')
                ->update($worker_order_id, [
                    'pay_type' => OrderUserService::PAY_TYPE_CASH,
                ]);
        }
        $fee_model->update($add_fee['id'], [
            'out_order_no' => $out_trade_no,
            'pay_type'     => WorkerOrderOutWorkerAddFeeService::PAY_TYPE_WORKER_WX,
        ]);
        OrderSettlementService::orderFeeStatisticsUpdateFee($worker_order_id);

        M()->commit();

        return [
            'out_order_no' => $out_trade_no,
            'amount'       => $total_fee_modify,
            'text'         => ($add_fee['is_add_fee'] ? '保外单工单加收费用' : '保外单工单费用'),
        ];
    }

    /**
     * @param $wxpay_pay_config
     *
     * @throws \EasyWeChat\Core\Exceptions\FaultException
     */
    public function notify($wxpay_pay_config)
    {
        $app = new Application($wxpay_pay_config);
        $response = $app->payment->handleNotify(function ($message, $fail) {
            $out_trade_no = $message['out_trade_no'];

            $field = 'id,status,data_id,user_id';
            $record_model = BaseModel::getInstance('pay_platform_record');
            $record = $record_model
                ->getOne([
                    'platform_type' => PayService::PLATFORM_TYPE_WECHAT_VALUE,
                    'out_order_no'  => $out_trade_no,
                ], $field);
            $user_id = $record['user_id'];

            if (empty($record) || PayService::WECHAT_PAY_STATUS_SUCCESS == $record['status']) {
                //支付记录不存在 或 已支付
                return true;
            }

            if ('SUCCESS' === $message['return_code']) { // return_code 表示通信状态，不代表支付状态
                // 用户是否支付成功
                $fee_model = BaseModel::getInstance('worker_order_out_worker_add_fee');
                $opts = [
                    'field' => 'id,worker_order_id',
                    'where' => [
                        'out_order_no' => $out_trade_no,
                    ],
                ];
                $add_fee = $fee_model->getOne($opts);
                if (empty($add_fee)) {
                    return true;
                }

                $worker_order_id = $add_fee['worker_order_id'];

                $opts = [
                    'where' => [
                        'worker_order_id' => $worker_order_id,
                        'pay_time'        => 0,
                        'id'              => ['neq', $add_fee['id']],
                    ],
                ];
                $fees = $fee_model->getList($opts);
                $fee_len = count($fees);

                M()->startTrans();
                if ('SUCCESS' === array_get($message, 'result_code')) {

                    //更新加收费用表
                    $update_data = [
                        'pay_time' => NOW_TIME,
                    ];
                    $fee_model->update($add_fee['id'], $update_data);

                    //更新平台支付表

                    $record_model->update($record['id'], [
                        'status'      => PayService::WECHAT_PAY_STATUS_SUCCESS,
                        'pay_ment'    => PayService::PAYMENT_WXPAY,
                        'notify_time' => NOW_TIME,
                        'pay_time'    => NOW_TIME,
                    ]);

                    $user_info_model = BaseModel::getInstance('worker_order_user_info');

                    $is_user_pay = OrderUserService::IS_USER_PAY_HAD_PAY;

                    if (0 == $fee_len) {
                        $is_user_pay = OrderUserService::IS_USER_PAY_SUCCESS;
                    }
                    $user_info_model->update($worker_order_id, [
                        'is_user_pay' => $is_user_pay,
                    ]);

                    OrderOperationRecordService::create($worker_order_id, OrderOperationRecordService::WORKER_REPRESENT_USER_PAY, [
                        'operator_id' => $user_id,
                        'remark'      => '微信支付',
                    ]);
                    OrderSettlementService::orderFeeStatisticsUpdateFee($worker_order_id);
                } elseif ('FAIL' === array_get($message, 'result_code')) {
                    // 用户支付失败

                    //更新平台支付表,更新回调时间,支付状态
                    $record_model->update($record['id'], [
                        'status'      => PayService::WECHAT_PAY_STATUS_FAIL,
                        'pay_ment'    => PayService::PAYMENT_WXPAY,
                        'notify_time' => NOW_TIME,
                    ]);

                    $fee_model->update($add_fee['id'], [
                        'pay_type' => WorkerOrderOutWorkerAddFeeService::PAY_TYPE_NO,
                    ]);
                }
                M()->commit();

            } else {
                return $fail('通信失败，请稍后再通知我');
            }

            return true;

        });

        $response->send();
    }

}