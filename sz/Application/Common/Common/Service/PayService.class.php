<?php
/**
 * File: PayService.class.php
 * User: zjz
 * Date: 2017/11/29
 * Remarks: 神州支付公用Service
 */
namespace Common\Common\Service;

use Common\Common\Model\BaseModel;
use Common\Common\Service\PayPlatformRecordService;

 class PayService
 {
 	private static $payservice;
 	private static $paytype;
 	private static $payplatform;
 	private static $payresult;

 	const PAY_RECORD_TABLE_NAME = 'pay_platform_record'; // 支付记录表

 	const PAY_NUMBER_PRE = 'SZLB';	// 外部订单号前缀

 	// 支付平台类型
 	const PLATFORM_TYPE_YILIAN_VALUE = '1';	// 易联支付
 	const PLATFORM_TYPE_WECHAT_VALUE = '2';	// 微信支付
 	const PLATFORM_TYPE_PINGPP_VALUE = '3';	// ping++支付

 	const PLATFORM_TYPE = [
 		self::PLATFORM_TYPE_YILIAN_VALUE => '\Common\Common\Service\YiLianService\PcPayStoreService',
        self::PLATFORM_TYPE_PINGPP_VALUE => '\Common\Common\Service\PayPlatformService\PingPayService',
        self::PLATFORM_TYPE_WECHAT_VALUE => '\Common\Common\Service\PayPlatformService\WechatService',
 	];

 	const USER_TYPE_ADMIN 			= PayPlatformRecordService::USER_TYPE_ADMIN; 	// 1 平台客服
 	const USER_TYPE_FACTORY 		= PayPlatformRecordService::USER_TYPE_FACTORY; 	// 2 厂家客服
 	const USER_TYPE_FACTORY_ADMIN 	= PayPlatformRecordService::USER_TYPE_FACTORY_ADMIN; 	// 3 厂家子账号
 	const USER_TYPE_WORKER 			= PayPlatformRecordService::USER_TYPE_WORKER; 	// 4 技工
 	const USER_TYPE_WEUSER 			= PayPlatformRecordService::USER_TYPE_WEUSER; 	// 5 微信用户(普通用户)
 	const USER_TYPE_WEDEALER 		= PayPlatformRecordService::USER_TYPE_WEDEALER; 	// 6 微信用户(经销商)

 	// 充值类型
	const PAY_TYPE_FACTORY_MONEY_RECHARGE = PayPlatformRecordService::PAY_TYPE_FACTORY_MONEY_RECHARGE; // 厂家资金充值

	// 支付状态
	const PAY_STATUS_NOT_PAY = '0'; // 未支付
	const PAY_STATUS_SUCCESS = '1'; // 支付成功
	const PAY_STATUS_FAIL 	 = '2'; // 支付失败
	const PAY_STATUS_OTHER 	 = '3'; // 其他

	const PAYMENT_UNIONPAY 	 = '1'; // 银联在线支付
    const PAYMENT_ALIPAY   	 = '2'; // 支付宝支付
    const PAYMENT_WXPAY    	 = '3'; // 微信支付
    const PAYMENT_NAME_KEY_VALUE = [
        self::PAYMENT_UNIONPAY => '银联在线支付',
        self::PAYMENT_ALIPAY => '支付宝支付',
        self::PAYMENT_WXPAY => '微信支付',
    ];


    //微信支付支付状态
    const WECHAT_STATUS_NOT_PAY = '1'; // 未支付
    const WECHAT_PAY_STATUS_SUCCESS = '2'; // 支付成功
    const WECHAT_PAY_STATUS_FAIL = '3'; // 支付成功

    // 微信支付交易类型
    const WECHAT_PAY_TRADE_TYPE = '1'; //JSAPI

	// 自定义初始化
 	public static function initDiy($type, $response_text = '', $extras = [])
 	{
 		if (!isset(self::PLATFORM_TYPE[$type])) {
 			throw new \Exception("未支持此支付", -9999999);
 		} 
 		$cp = self::PLATFORM_TYPE[$type];
 		static::$payservice = new $cp();
 		static::$payplatform = $type;
 		static::$payresult  = static::$payservice->xmlDecrypt($response_text);
 		return static::$payservice;
 	}

 	public static function getPayService()
 	{
 		return static::$payservice;
 	}

 	public static function getPlatformOrderNo()
    {
        return static::$payservice->getPlatformOrderNo();
    }

 	public static function getPayMent()
 	{
 		return static::$payservice->getPayMent();
 	}

 	public static function getSystemPayMent($pay_ment = '')
 	{
 		return static::$payservice->getSystemPayMent($pay_ment);
 	}

 	public static function getPayResult()
 	{
 		static::$payresult = static::$payservice->getPayResult();
 		return static::$payresult;
 	} 	

 	public static function getOrderStatus($pay_status = '')
 	{	
 		return static::$payservice->getOrderStatus($pay_status);
 	}

 	public static function getPayStatus()
 	{
 		return static::$payservice->getPayStatus();
 	}

 	public static function getOutOrderNno()
 	{
 		return static::$payservice->getOutOrderNno();
 	}

 	public static function getPayAmount()
 	{
 		return static::$payservice->getPayAmount();
 	}

 	public static function createOutOrderNo(&$no)
 	{
 		$date = date('Ymd', NOW_TIME);
 		$no = self::PAY_NUMBER_PRE."{$date}".str_pad(rand(111111, 999999), 6, '0', STR_PAD_LEFT);
 		if (BaseModel::getInstance(self::PAY_RECORD_TABLE_NAME)->getNum(['out_order_no' => $no])) {
 			self::createOutOrderNo($no);
 		}
 	}

 }
