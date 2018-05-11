<?php
/**
 * File: PayPlatformRecordService.class.php
 * User: zjz
 * Date: 2017/12/07
 */
namespace Common\Common\Service;

use Common\Common\Model\BaseModel;
use Common\Common\Service\PayService;
use Common\Common\Service\FactoryMoneyRecordService;

 class PayPlatformRecordService
 {
 	public static $paylog;
 	public static $status;
 	public static $response;

 	const THIS_HERE_TABLE_NAME 				= 'pay_platform_record';
 	const FACTORY_MONEY_CHANGE_TABLE_NAME 	= 'factory_money_change_record';
    const FACTORY_TABLE_NAME 				= 'factory';
    const FACTORY_ADMIN_TABLE_NAME 			= 'factory_admin';

	const USER_TYPE_ADMIN			= '1';	// 1 平台客服
	const USER_TYPE_FACTORY			= '2';	// 2 厂家客服
	const USER_TYPE_FACTORY_ADMIN	= '3';	// 3 厂家子账号
	const USER_TYPE_WORKER			= '4';	// 4 技工
	const USER_TYPE_WEUSER			= '5';	// 5 微信用户(普通用户)
	const USER_TYPE_WEDEALER		= '6';	// 6 微信用户(经销商)

 	const PAY_TYPE_FACTORY_MONEY_RECHARGE = '1'; // 厂家资金充值
 	const PAY_TYPE_ORDER_WECHAT_PAY = '2';       // 保外单微信支付
 	// 支付类型允许的操作人
 	const PAY_TYPE_ACTION_ROLE = [
		self::PAY_TYPE_FACTORY_MONEY_RECHARGE => [
			self::USER_TYPE_FACTORY,
			self::USER_TYPE_FACTORY_ADMIN,	
		],
	];

 	public static function asynResponse()
 	{
 		exit(static::$response);
 	}

 	public static function goToSynUrl()
 	{
 		if (static::$paylog['syn_url']) {
 			header("Location: ".static::$paylog['syn_url']);
 		}
 	}

 	public static function autoSetPayLog()
    {
        $no = PayService::getOutOrderNno();

        static::$paylog = static::$paylog ?? BaseModel::getInstance(self::THIS_HERE_TABLE_NAME)->getOneOrFail(['out_order_no' => $no]);
    }

 	// 支付结果处理
 	public static function paymentResult()
 	{
 	    self::autoSetPayLog();

 		$order_status = PayService::getOrderStatus();
 		switch ($order_status) {
			case PayService::PAY_STATUS_SUCCESS:
				self::paySuccess();
				break;

			case PayService::PAY_STATUS_FAIL:
				self::payFail();
				break;
			
			default:
				static::$response = '0002';
				break;
		}
 	}

 	// 厂家资金充值
 	public static function factoryMoneyRecharge(&$paylog)
 	{
		$f_model = BaseModel::getInstance(self::FACTORY_TABLE_NAME);
		if (PayService::getOrderStatus() == PayService::PAY_STATUS_SUCCESS) {

				!static::$paylog['pay_time']
 			&&  $paylog['pay_time'] = NOW_TIME;

			switch (static::$paylog['user_type']) {
				case self::USER_TYPE_FACTORY_ADMIN:
					$factory_admin = BaseModel::getInstance(self::FACTORY_ADMIN_TABLE_NAME)->getOneOrFail(static::$paylog['user_id']);
					$paylog['data_id'] = FactoryMoneyRecordService::yilianCreate($factory_admin['factory_id'], PayService::getSystemPayMent(), PayService::PAY_STATUS_SUCCESS);
					break;

				case self::USER_TYPE_FACTORY:
					$factory = $f_model->getOneOrFail(static::$paylog['user_id']);
					$fid = static::$paylog['user_id'];
					$paylog['data_id'] = FactoryMoneyRecordService::yilianCreate($fid, PayService::getSystemPayMent(), PayService::PAY_STATUS_SUCCESS);
					break;

				default:
					static::$response = '0003';
					break;
			}	
		}
	    static::$response = '0000';
 	}

 	public static function paySuccess()
 	{
		$status = PayService::getPayStatus();
 		if (static::$paylog && static::$paylog['status'] != $status) {
 			$update = [
 				'status' 	=> $status,
 				'pay_ment'	=> PayService::getPayMent(),
 			];
 			$no = PayService::getPlatformOrderNo();
            empty(PayPlatformRecordService::$paylog['platform_order_no']) && !empty($no) && $update['platform_order_no'] = $no;
			switch (static::$paylog['pay_type']) {
				case self::PAY_TYPE_FACTORY_MONEY_RECHARGE:
					self::factoryMoneyRecharge($update);
					break;
				
				default:
					static::$response = '0001';
					break;
			}
			$model = BaseModel::getInstance(self::THIS_HERE_TABLE_NAME);
			$model->update(static::$paylog['id'], $update);
		} else {
	    	static::$response = '0000';
		}
 	}

 	public static function payFail()
 	{
 		$status = PayService::getPayStatus();
 		if (static::$paylog && static::$paylog['status'] != $status) {
 			$update = [
				'status' 	=> PayService::getPayStatus(),
				'pay_ment'	=> PayService::getPayMent(),
			];
            $no = PayService::getPlatformOrderNo();
            empty(PayPlatformRecordService::$paylog['platform_order_no']) && !empty($no) && $update['platform_order_no'] = $no;
			$model = BaseModel::getInstance(self::THIS_HERE_TABLE_NAME);
			$model->update(static::$paylog['id'], $update);
 		}

 		static::$response = '0000';
 	}

 }
