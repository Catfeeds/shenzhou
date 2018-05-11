<?php
/**
 * File: KeyConfigService.class.php
 * User: zjz
 * Date: 2017/11/29
 */
namespace Common\Common\Service\YiLianService;

use Common\Common\Service\PayService;

 class KeyConfigService
 {
 	const CONFIG_YILIAN_VERSION			= '2.1.0';	// 使用的易联支付版本
 	const CONFIG_PROCODE_TYPE_0200		= '0200';	// 报文类型	0200 下单
 	const CONFIG_PROCODE_TYPE_0210		= '0210';	// 报文类型	0210 支付
 	const CONFIG_PROCESS_CODE			= '190011';	// 处理码：190011

	// 28 PC收银台(web页面集成支付方式：包括快捷、网银、对公代扣、外卡、微信扫码、支付宝扫码)
	// 29 H5收银台（暂不支持，H5可对接插件产品）
	// 30 三码合一（微信/支付宝扫码）
	// 31 SDK收银台（目前只对吉利支持）
	// 32 微信扫码API（用户主扫）
	// 33 支付宝扫码API（用户主扫）
	// 34 微信公众号支付API
	// 35 微信原生APP（API）
	// 36 支付宝APP （API）
	// 38 微信条码支付API（用户被扫）
	// 39 支付宝条码支付API（用户被扫）
	// 40 银联二维码同步
	// 41 上海迪士尼SDK
	// 42 白条支付H5
 	const CONFIG_ORDER_FROM_PCPAY		= 28;		// 处理码：190011

 	// 1 快捷 3 个人网银 4 企业网银 5企业快捷 6境外卡支付 7 微信扫码 8 支付宝扫码
 	const CONFIG_YILIAN_PAY_TYPE_WECHAT 	= '7';
 	const CONFIG_YILIAN_PAY_TYPE_APLIPAY 	= '8';

 	// const CONFIG_MERCHANT_NUMBER  		= '100120000511';
 	// const CONFIG_MERCHANT_PASSWORD  	= 'E6D839089FF94742';	// 商户密钥（密码）
 	const CONFIG_MERCHANT_NUMBER  		= '1472181543236';	// 1462847066331  1472181543236 (商户号)
 	const CONFIG_MERCHANT_PASSWORD  	= '123456';	// 商户密钥（密码）

 	const URL_GDYILIAN_CERT_PUBLIC_64 	= '/pem/gdyilian_cert_public_64.pem'; 			// 易联的公钥
 	const URL_RSA_PRIVATE_KEY	 		= '/pem/rsa_private_key.pem'; 					// 神舟私钥
 	const URL_RSA_PKCS8_PRIVATE_KEY 	= '/pem/yilian_pkcs8_private_key_171129.pem'; 	// 神舟pkcs8私钥
 	const URL_RSA_PUBLIC_KEY 			= '/pem/rsa_public_key.pem'; 					// 神舟公钥
 	// const URL_SERVICES_API_RSA 			= 'https://dnaserver.payeco.com/services/ApiV2ServerRSA';		// 生产环境 (订单支付,订单撤销,调账退货)
 	 // const URL_SERVICES_API_RSA 		= 'https://dnapay.payeco.com/services/ApiV2ServerRSA';		// 生产环境 (订单支付,订单撤销,调账退货)
 	const URL_SERVICES_API_RSA 			= 'http://test.payeco.com:9080/services/ApiV2ServerRSA'; 	// 测试环境
 	const URL_SERVLET_SYN_ADRESS		= 'http://test.payeco.com:9080/payecodemo/servlet/CallBackServlet';
 	const URL_SERVLET_ASYN_ADRESS		= 'asynAddress';

 	const CURCODE_CNY = 'CNY';
 	const PROCODE_ARR = [
 		self::CONFIG_PROCODE_TYPE_0200,
 		self::CONFIG_PROCODE_TYPE_0210,
 	];

 	// 支付状态
 	const ORDER_STATUS_NOT_PAY				= '01'; // 未支付
 	const ORDER_STATUS_PAY_ED 				= '02'; // 已支付
 	const ORDER_STATUS_RETURNED 			= '03';	// 已退款(全额撤销/冲正)
 	const ORDER_STATUS_BE_OVERDUE 			= '04';	// 已过期
 	const ORDER_STATUS_TO_VOID 				= '05';	// 已作废
 	const ORDER_STATUS_PAY_ING 				= '06';	// 支付中
 	const ORDER_STATUS_PAYED_ING 			= '07';	// 退款中
 	const ORDER_STATUS_STORE_CANCEL 		= '08';	// 已被商户撤销
 	const ORDER_STATUS_PARER_CANCEL 		= '09';	// 已被持卡人撤销
 	const ORDER_STATUS_DIAOZHANG_PAY_ED 	= '10';	// 调账-支付成功
 	const ORDER_STATUS_DIAOZHANG_RETURNED	= '11';	// 调账-退款成功
 	const ORDER_STATUS_RETURN_GOODS 		= '12';	// 已退货
 	const ORDER_STATUS_NAME_ARR = [
 		self::ORDER_STATUS_NOT_PAY 				=> '未支付',
 		self::ORDER_STATUS_PAY_ED 				=> '已支付',
 		self::ORDER_STATUS_RETURNED 			=> '已退款',
 		self::ORDER_STATUS_BE_OVERDUE			=> '已过期',
		self::ORDER_STATUS_TO_VOID 				=> '已作废',
		self::ORDER_STATUS_PAY_ING 				=> '支付中',
		self::ORDER_STATUS_PAYED_ING 			=> '退款中',
		self::ORDER_STATUS_STORE_CANCEL 		=> '已被商户撤销',
		self::ORDER_STATUS_PARER_CANCEL 		=> '已被持卡人撤销',
		self::ORDER_STATUS_DIAOZHANG_PAY_ED 	=> '调账-支付成功',
		self::ORDER_STATUS_DIAOZHANG_RETURNED 	=> '调账-退款成功',
		self::ORDER_STATUS_RETURN_GOODS 		=> '已退货',
 	];

 	// 易联支付状态 对应 神州系统的支付状态
	const ORDER_STATUS_FOR_SYSTEM_PAY_STATUS = [
		self::ORDER_STATUS_NOT_PAY 				=> PayService::PAY_STATUS_NOT_PAY,
 		self::ORDER_STATUS_PAY_ED 				=> PayService::PAY_STATUS_SUCCESS,
 		// self::ORDER_STATUS_RETURNED 			=> '已退款',
 		self::ORDER_STATUS_BE_OVERDUE			=> PayService::PAY_STATUS_FAIL,
		self::ORDER_STATUS_TO_VOID 				=> PayService::PAY_STATUS_FAIL,
		self::ORDER_STATUS_PAY_ING 				=> PayService::PAY_STATUS_NOT_PAY,
		// self::ORDER_STATUS_PAYED_ING 			=> '退款中',
		self::ORDER_STATUS_STORE_CANCEL 		=> PayService::PAY_STATUS_FAIL,
		self::ORDER_STATUS_PARER_CANCEL 		=> PayService::PAY_STATUS_FAIL,
		self::ORDER_STATUS_DIAOZHANG_PAY_ED 	=> PayService::PAY_STATUS_SUCCESS,
		// self::ORDER_STATUS_DIAOZHANG_RETURNED 	=> '调账-退款成功',
		// self::ORDER_STATUS_RETURN_GOODS 		=> '已退货',
    ];

    // 1 快捷 3 个人网银 4 企业网银 5企业快捷 6境外卡支付 7 微信扫码 8 支付宝扫码
    const PAYMENT_QUICKPAY 			= 1;
    const PAYMENT_PERSONAL_UNIONPAY = 3;
    const PAYMENT_COMPANY_UNIONPAY  = 4;
    const PAYMENT_COMPANY_QUICKPAY  = 5;
    const PAYMENT_OVERSEAS_CARDPAY 	= 6;
    const PAYMENT_WXPAY_SCANPAY 	= 7;
    const PAYMENT_ALIPAY_SCANPAY 	= 8;
    // 易联支付方式 对应 神州系统的支付方式
    const PAYMENT_FOR_SYSTEM_PAYMENT = [
		self::PAYMENT_QUICKPAY			=> PayService::PAYMENT_UNIONPAY,
        self::PAYMENT_PERSONAL_UNIONPAY	=> PayService::PAYMENT_UNIONPAY,
        self::PAYMENT_COMPANY_UNIONPAY	=> PayService::PAYMENT_UNIONPAY,
        self::PAYMENT_COMPANY_QUICKPAY	=> PayService::PAYMENT_UNIONPAY,
        self::PAYMENT_OVERSEAS_CARDPAY	=> PayService::PAYMENT_UNIONPAY,
        self::PAYMENT_WXPAY_SCANPAY		=> PayService::PAYMENT_WXPAY,
        self::PAYMENT_ALIPAY_SCANPAY    => PayService::PAYMENT_ALIPAY,
    ];

    public static function payStatusFormat($status)
    {
    	return str_pad(intval($status), 2, '0', STR_PAD_LEFT);
    }

 	public static function getYiLianPublicKey()
	{
		// return file_get_contents(dirname(__FILE__).self::URL_GDYILIAN_CERT_PUBLIC_64);
		return file_get_contents(dirname(__FILE__).C('YILIAN_URL_GDYILIAN_CERT_PUBLIC_64'));
	}

	public static function getSZPrivateKey()
	{
		// return file_get_contents(dirname(__FILE__).self::URL_RSA_PRIVATE_KEY);
		return file_get_contents(dirname(__FILE__).C('YILIAN_URL_URL_RSA_PRIVATE_KEY'));
	}

	public static function getSZPKCS8PrivateKey()
	{
		// return file_get_contents(dirname(__FILE__).self::URL_RSA_PKCS8_PRIVATE_KEY);
		return file_get_contents(dirname(__FILE__).C('YILIAN_URL_URL_RSA_PKCS8_PRIVATE_KEY'));
	}

	public static function getSZPublicKey()
	{
		// return file_get_contents(dirname(__FILE__).self::URL_RSA_PUBLIC_KEY);
		return file_get_contents(dirname(__FILE__).C('YILIAN_URL_URL_RSA_PUBLIC_KEY'));
	}

	public static function getCommonConfig()
	{
		$return = [
			'version'		=> self::CONFIG_YILIAN_VERSION,
			'procCode'		=> self::CONFIG_PROCODE_TYPE_0200,
			'processCode'	=> self::CONFIG_PROCESS_CODE,
			// 'merchantNo'	=> self::CONFIG_MERCHANT_NUMBER,
			'merchantNo'	=> C('YILIAN_CONFIG_MERCHANT_NUMBER'),			
			'currency'		=> self::CURCODE_CNY,
		];
		return $return;
	}

 }
