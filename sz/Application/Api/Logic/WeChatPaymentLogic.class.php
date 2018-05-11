<?php
/**
* @User zjz
*/
namespace Api\Logic;

use Api\Common\ErrorCode;
use Api\Model\BaseModel;
use Common\Common\Service\OrderService;
use Common\Common\Service\OrderSettlementService;
use Common\Common\Service\OrderUserService;
use Common\Common\Service\WorkerOrderOutWorkerAddFeeService;
use EasyWeChat\Foundation\Application;
use Library\Common\Util;
use Think\Log;
use Api\Logic\BaseLogic;
use EasyWeChat\Payment\Order;
use Common\Common\Service\PayService;
use Common\Common\Service\PayPlatformRecordService;
use Common\Common\Service\AuthService;
use Common\Common\Service\OrderOperationRecordService;

class WeChatPaymentLogic extends BaseLogic
{
	public $service_string = 'SERVICE';
	
	protected function init()
	{
//        $config = [
//            'app_id'  => 'wx0d957e6985b1bd17',  // AppID
//
//            /**
//             * 微信支付
//             */
//            'payment' => [
//                'merchant_id'        => '1301074801',
//                'key'                => 'e0682A4278265085Dbf5f77042bcb300'
//            ]
//        ];
		$config = C('easyWeChat');
		$app = new Application($config);
		return $app->payment;
	}

	public function getIsOutOrderServicePayConfigByOrderId($oid = 0)
	{
		$data = D('WorkerOrder')->getOneOrFail([
				'alias' => 'WO',
				'join'  => 'LEFT JOIN worker_order_detail WOD ON WO.order_id = WOD.worker_order_id',
				'where' => ['WO.order_id' =>$oid],
				'field' => 'WO.orno,WOD.code,WOD.product_id,WOD.servicebrand_desc,WOD.servicepro_desc,WOD.model as product_xinghao,WOD.stantard_desc',
			]);
		$code = $data['code'];
		$orno = $data['orno'];
		$product_id = $data['product_id'];
		unset($data['code'], $data['orno'], $data['product_id']);
		if (!$code) {
			$this->throwException(ErrorCode::WORKER_ORDER_NOT_OUT);
		}
		$md5 = D('WorkerOrderDetail')->codeToMd5Code($code);
		$user = BaseModel::getInstance('wx_user_product')->getOne([
				'alias' => 'WUP',
				'join'  => 'LEFT JOIN wx_user WU ON WUP.wx_user_id = WU.id ',
				'where' => ['WUP.md5code' => $md5],
				'order' => 'WU.id desc'
			]);
		$service = D('FactoryProduct')->getServiceById($product_id);
		
		if (!isset($service['cost'])) {
			$service_price = 10.00;
		} else {
			$service_price = $service['cost'];
		}

		$pay_data = [
			'openid' => $user['openid'],
			'body' => $data['servicepro_desc'],
			'detail' => implode(' ', array_filter($data)),
			'orno' => $this->service_string.$orno,
			'total_fee' => $service_price * 100,
			// 'notify_url' => Util::getServer().'/index.pgp/Api/PayNotify/WxPayNotify',
			'notify_url' => 'http://shenzhou.3ncto.com.cn/index.php/Api/PayNotify/WxPayNotify',
		];

		return $this->createPayOrder($pay_data);

	}

	public function createPayOrder($data = [])
	{
		if (!$data['orno'] || !$data['openid'] || !$data['body']) {
			$this->throwException(ErrorCode::DATA_IS_WRONG);
		}
		$attributes = [
		    'trade_type'       => 'JSAPI', // JSAPI，NATIVE，APP...
		    'body'             => $data['body'],
		    'detail'           => $data['detail'] ? $data['detail'] : $data['body'],
		    'out_trade_no'     => $data['orno'],
		    'total_fee'        => isset($data['total_fee']) ? $data['total_fee'] : 1000, 
		    'openid'           => $data['openid'], // trade_type=JSAPI，此参数必传，用户在商户appid下的唯一标识，
		];

		if (isset($data['notify_url'])) {
			$attributes['notify_url'] = $data['notify_url'];
		}
		$order = new \EasyWeChat\Payment\Order($attributes);
		$payment = $this->init();
		 $result = $payment->prepare($order);
		// var_dump($result);die;
		// var_dump($payment->configForJSSDKPayment($prepayId));die;
		if ($result->return_code == 'SUCCESS' && $result->result_code == 'SUCCESS'){
            $prepayId = $result->prepay_id;
            return $payment->configForJSSDKPayment($prepayId); // 返回数组
        } else {
            return $result;
        }
	}

	public function wxPayNotify()
	{
		$payment = $this->init();
		$response = $response = $payment->handleNotify(function($notify, $successful){
			// if ('SUCCESS') {
				
			// }

			// return true; // 处理完成，同志微信不再推送信息
		});

		return $response->send();
	}

	public function jsPay($request, $user_id)
    {
        $payment = $this->init();

        $worker_order_info = BaseModel::getInstance('worker_order')->getOne(['id'=>$request['id']]);
        if (in_array($worker_order_info['worker_order_type'], OrderService::ORDER_TYPE_IN_INSURANCE_LIST)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '保内单不需要微信支付');
        }

        //计算支付费用
        $order_user_info = BaseModel::getInstance('worker_order_user_info')->getOne($request['id']);

        $order_user_info == OrderUserService::IS_USER_PAY_SUCCESS && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '已支付');

        $fee_info = BaseModel::getInstance('worker_order_fee')->getOne([
            'where' => [
                'worker_order_id' => $request['id']
            ],
            'field' => 'worker_repair_fee_modify, accessory_out_fee, user_discount_out_fee'
        ]);

        $out_fees = BaseModel::getInstance('worker_order_out_worker_add_fee')->getOne([
            'order' => 'create_time desc,id desc',
            'where' => [
                'pay_time' => 0,
                'worker_order_id' => $request['id'],
            ],
        ]);

        !$out_fees && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '已支付');

        $fee = ($out_fees['worker_repair_fee_modify'] + $out_fees['accessory_out_fee_modify'] - $fee_info['user_discount_out_fee']) * 100;

        //计算优惠券
        if (!empty($request['coupon_receive_record_id']) && $out_fees['is_add_fee'] == WorkerOrderOutWorkerAddFeeService::IS_ADD_FEE_NO) {
            //检查优惠券是否满足使用权限
            $coupon = BaseModel::getInstance('coupon_receive_record')->getOne([
                'id'         => $request['coupon_receive_record_id'],
                'start_time' => ['lt', NOW_TIME],
                'end_time'   => ['gt', NOW_TIME],
                'status'     => 1,
                'cp_full_money' => ['lt', ($out_fees['worker_repair_fee_modify'] + $out_fees['accessory_out_fee_modify']) * 100]
            ]);
            if (!empty($coupon)) {
                $fee = $fee - $coupon['cp_reduce_money'];
                $coupon_id = $request['coupon_receive_record_id'];
            }

        }
        $coupon_id = !empty($coupon_id) ? $coupon_id : '';
        BaseModel::getInstance('worker_order')->update($request['id'], [
            'coupon_id' => $coupon_id
        ]);
        $fee = round($fee);

        $product_category_id = BaseModel::getInstance('worker_order_product')->getFieldVal([
            'worker_order_id' => $request['id']
        ], 'product_category_id');
        $item_desc = BaseModel::getInstance('cm_list_item')->getFieldVal([
            'list_item_id' => $product_category_id
        ], 'item_desc');
        $openid = BaseModel::getInstance('wx_user')->getFieldVal([
            'id' => $user_id
        ], 'openid');

        PayService::createOutOrderNo($no);

        $attributes = [
            'trade_type' => 'JSAPI', // 支付类型JSAPI，NATIVE，APP...
            'body' => '家电售后-'.$item_desc,
            'out_trade_no' => $no,//订单号
            'total_fee' => $fee, // 单位：分
            'notify_url' => Util::getServerUrl() . $_SERVER['SCRIPT_NAME'] .'/wechat/wxpaynotify', // 支付结果通知网址
            'openid' => $openid, // trade_type=JSAPI，此参数必传
        ];
        $order = new Order($attributes);

        $result = $payment->prepare($order);
        if ($result->return_code == 'SUCCESS' && $result->result_code == 'SUCCESS'){
            $prepayId = $result->prepay_id;
            //记录
            BaseModel::getInstance('worker_order_out_worker_add_fee')->update($out_fees['id'], [
                'type' => WorkerOrderOutWorkerAddFeeService::PAY_TYPE_USER_WX,
                'out_order_no' => $no
            ]);
            BaseModel::getInstance('pay_platform_record')->insert([
                'platform_type' => PayService::PLATFORM_TYPE_WECHAT_VALUE,
                'out_order_no'  => $no,
                'money'         => $fee / 100,
                'pay_type'      => PayPlatformRecordService::PAY_TYPE_ORDER_WECHAT_PAY,
                'data_id'       => $request['id'],
                'user_id'       => $user_id,
                'user_type'     => 5,
                'status'        => PayService::WECHAT_STATUS_NOT_PAY,
                'pay_ment'      => PayService::WECHAT_PAY_TRADE_TYPE,
                'create_time'   => NOW_TIME
            ]);
        } else {
            return $result;
        }

        $json = json_decode($payment->configForPayment($prepayId), true);
        $json['timestamp'] = $json['timeStamp'];
        unset($json['timeStamp']);

        return $json;
    }

    /*
     * 微信支付回调
     */
    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \EasyWeChat\Core\Exceptions\FaultException
     */
    public function payNotify(){
        $payment = $this->init();
        $response = $payment->handleNotify(function($notify, $successful){
            $pay_model = BaseModel::getInstance('pay_platform_record');
            $out_trade_no = $notify->out_trade_no;
            $order = $pay_model->getOne(['out_order_no' => $out_trade_no]);
            if (!$order) { // 如果订单不存在
                return 'Order not exist.'; // 告诉微信，处理完了，订单没找到
            }
            // 如果订单存在
            // 检查订单是否已经更新过支付状态
            if ($order['status'] == '2') {
                return true;       // 已经支付成功了就不再更新了
            }
            // 用户是否支付成功
            if ($successful) {
                // 修改订单支付状态
                M()->startTrans();
                $pay_model->update([
                    'out_order_no' => $out_trade_no
                ], [
                    'status'   => PayService::WECHAT_PAY_STATUS_SUCCESS,
                    'pay_time' => NOW_TIME
                ]);

                if ($order['pay_type'] == PayPlatformRecordService::PAY_TYPE_ORDER_WECHAT_PAY) {
                    $add_model = BaseModel::getInstance('worker_order_out_worker_add_fee');

                    $other_not_pay_add_fee = $add_model->getOne([
                        'worker_order_id' => $order['data_id'],
                        'id' => ['neq', $order['id']],
                        'pay_time' => 0
                    ]);

                    $add_model->update([
                        'worker_order_id' => $order['data_id'],
                        'out_order_no' => $out_trade_no,
                    ], [
                        'pay_type' => WorkerOrderOutWorkerAddFeeService::PAY_TYPE_USER_WX,
                        'pay_time' => NOW_TIME,
                    ]);

                    // 修改对应用户的支付状态
                    BaseModel::getInstance('worker_order_user_info')->update([
                        'worker_order_id' => $order['data_id']
                    ], [
                        'is_user_pay' => $other_not_pay_add_fee ? OrderUserService::IS_USER_PAY_SUCCESS : OrderUserService::IS_USER_PAY_HAD_PAY,
                        'pay_time' => NOW_TIME
                    ]);

                    // todo 修改对应优惠券的使用状态
                    $order_info = BaseModel::getInstance('worker_order')->getOne([
                        'where' => [
                            'id' => $order['data_id']
                        ],
                        'field' => 'orno, coupon_id'
                    ]);
                    if (!empty($order_info['coupon_id'])) {
                        BaseModel::getInstance('coupon_receive_record')->update([
                            'id' => $order_info['coupon_id']
                        ], [
                            'worker_order_id' => $order['data_id'],
                            'use_time' => NOW_TIME,
                            'status'   => 2,
                            'cp_orno'  => $order_info['orno']
                        ]);
                        $coupon_info = BaseModel::getInstance('coupon_receive_record')->getOne([
                            'where' => [
                                'id' => $order_info['coupon_id']
                            ],
                            'field' => 'cp_reduce_money, coupon_id'
                        ]);
                        BaseModel::getInstance('worker_order_fee')->update([
                            'worker_order_id' => $order['data_id']
                        ], [
                            'coupon_reduce_money' => $coupon_info['cp_reduce_money']
                        ]);
                        BaseModel::getInstance('coupon_rule')->setNumInc([
                            'id' => $coupon_info['coupon_id']
                        ], 'total_use');
                    }

                    $user_id = BaseModel::getInstance('wx_user')->getFieldVal([
                        'openid' => $notify->openid
                    ], 'id');

                    //保外单操作记录
                    OrderOperationRecordService::create($order['data_id'], OrderOperationRecordService::WX_USER_WECHAT_PAY_SUCCESS, [
                        'operator_id' => $user_id,
                        'remark' => '微信支付'
                    ]);
                    OrderSettlementService::orderFeeStatisticsUpdateFee($order['data_id']);
                }

                M()->commit();
            }
            return true; // 返回处理完成
        });
        return $response;
    }

    /*
     * 支付费用详情
     */
    public function payInfo($order_id, $user_id)
    {
        $order_info = BaseModel::getInstance('worker_order')->getOne([
            'where' => [
                'id' => $order_id
            ],
            'field' => 'orno, service_type, coupon_id'
        ]);
        if (empty($order_info)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }
        $fee_info = BaseModel::getInstance('worker_order_fee')->getOne([
            'where' => [
                'worker_order_id' => $order_id
            ],
            'field' => 'worker_repair_fee_modify, accessory_out_fee, user_discount_out_fee'
        ]);
        $user_pay_fee = $fee_info['worker_repair_fee_modify'] + $fee_info['accessory_out_fee'];
        if ($fee_info['user_discount_out_fee'] == '0.00') {
            //如果随机优惠金额为0.00则更新
            if ($user_pay_fee == 0.01) {
                $user_discount_out_fee = 0;
            } else {
                $rand_max = $user_pay_fee > 5.00 ? 500 : round($user_pay_fee) * 100;
                $user_discount_out_fee = rand(100, $rand_max) / 100;
            }
            BaseModel::getInstance('worker_order_fee')->update([
                'worker_order_id' => $order_id
            ], [
                'user_discount_out_fee' => $user_discount_out_fee
            ]);
            $fee_info['user_discount_out_fee'] = $user_discount_out_fee;
        }
        // todo 获取优惠券信息
        $coupon_count = BaseModel::getInstance('coupon_receive_record')->getNum([
            'wx_user_id' => $user_id,
            'start_time' => ['lt', NOW_TIME],
            'end_time'   => ['gt', NOW_TIME],
            'status'     => 1,
            'cp_full_money' => ['elt', ($fee_info['worker_repair_fee_modify'] + $fee_info['accessory_out_fee']) * 100]
        ]);
        $coupon_info = BaseModel::getInstance('coupon_receive_record')->getOne([
            'where' => [
                'id' => $order_info['coupon_id']
            ],
            'field' => 'id as coupon_receive_record_id, cp_reduce_money'
        ]);
//        $coupon_count = '0';
//        $coupon_info  = null;
        return [
            'id' => $order_id,
            'orno' => $order_info['orno'],
            'service_type' => $order_info['service_type'],
            'worker_repair_fee_modify' => $fee_info['worker_repair_fee_modify'],
            'accessory_out_fee'        => $fee_info['accessory_out_fee'],
            'user_discount_out_fee'    => $fee_info['user_discount_out_fee'],
            'coupon_count'             => $coupon_count,
            'coupon_receive_record_id' => $coupon_info['id'],
            'cp_reduce_money'          => number_format($coupon_info['cp_reduce_money'], 2, '.', '')
        ];
    }

}
