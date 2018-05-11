<?php
/**
 * File: PcPayStoreSerive.class.php
 * User: zjz
 * Date: 2017/11/29
 */
namespace Common\Common\Service\YiLianService;

use Common\Common\Service\PayService;
use Common\Common\Service\YiLianService\KeyConfigService;
use Common\Common\Service\YiLianService\util\Toolkit;
use Common\Common\Service\YiLianService\entity\MerchantMessage;
use Library\Common\XML;

class PcPayStoreService
{

    private static $payresult;

    public function createOrder($data)
    {
        $msg = new MerchantMessage();

        $msg->synAddress 		= $data['syn_address'];      // urlencode($data['syn_address']);		// 同步地址
        $msg->asynAddress 		= $data['asyn_address'];     // urlencode($data['asyn_address']);		// 异步地址
        // $msg->synAddress 		= KeyConfigService::URL_SERVLET_SYN_ADRESS;		// 同步地址
        // $msg->asynAddress 		= KeyConfigService::URL_SERVLET_ASYN_ADRESS;	// 异步地址

        $msg->merchantOrderNo 	        = $data['yilian_pay_number'];	
        $msg->amount 			= number_format($data['amount'], 2, '.', '');
        $msg->acqSsn 			= date('His', NOW_TIME);		// 系统跟踪号
        $msg->transDatetime 	        = date('YmdHis', time()); 	// 传输时间
        $msg->description 		= $data['description']; 		//'orderen test';		//商品描述
        $msg->remark			= $data['remark'];
        $msg->orderFrom                 = KeyConfigService::CONFIG_ORDER_FROM_PCPAY;

        // $msg->mac                       = $msg->computeMac(KeyConfigService::CONFIG_MERCHANT_PASSWORD);
        $msg->mac 		        = $msg->computeMac(C('YILIAN_CONFIG_MERCHANT_PASSWORD'));

        $src_xml			= '';
        $request_text		        = '';
        $src_xml                        = $msg->toXml();
        file_put_contents('./xml.xml', $src_xml);
        // die;
        // $encryptkey 	= '1234567890qwertyuiopasdf';
        $encryptkey 	= Toolkit::random();	//3DES加密密钥
        $rsa_public_key = KeyConfigService::getYiLianPublicKey();	//易联公钥证书

        $tmp = Toolkit::signWithMD5($encryptkey, $src_xml, $rsa_public_key);
        $tmp = str_pad(strlen($tmp), 6, '0', STR_PAD_LEFT).$tmp;
        // $url = KeyConfigService::URL_SERVICES_API_RSA;
        $url = C('YILIAN_URL_SERVICES_API_RSA');

        header("Content-Type: text/html; charset=utf-8");
        $html  = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>';
        $html .= '<html><head></head><body>';
        $html .= "<form id='paysubmit' name='paysubmit' action='".$url."' method='post'>";
        $html .= "<input type='hidden' name='".$tmp."'/>";
        $html .= $html."<script>document.forms['paysubmit'].submit();</script>";
        $html .= '</body></html>';
        echo $html;
        echo htmlentities($html, ENT_QUOTES, "UTF-8");
    }

    public function getPayResult()
    {
        return static::$payresult;
    }

    public function getOrderStatus($pay_status = '')
    {
        $status = $pay_status !== '' ? KeyConfigService::payStatusFormat((int)$pay_status) : static::$payresult['OrderState'];
        return KeyConfigService::ORDER_STATUS_FOR_SYSTEM_PAY_STATUS[$status];
    }

    public function getPlatformOrderNo()
    {
        return static::$payresult['OrderNo'];
    }


    public function getPayMent()
    {
        return static::$payresult['PayType'];
    }

    public function getSystemPayMent($pay_ment = '')
    {
        $pay_ment = $pay_ment !== '' ? $pay_ment : static::$payresult['PayType'];
        return KeyConfigService::PAYMENT_FOR_SYSTEM_PAYMENT[$pay_ment] ?? PayService::PAYMENT_UNIONPAY;
    }

    public function getPayStatus()
    {
        $status = static::$payresult['OrderState'];
        return empty($status) ? $status : (int)$status;
    }

    public function getOutOrderNno()
    {
        return static::$payresult['MerchantOrderNo'];
    }

    public function getPayAmount()
    {
        return static::$payresult['Amount'];
    }

    // 报文解密
    public function xmlDecrypt($response_xml)
    {       
        $xml = base64_decode($response_xml);
        $xml = XML::parse($xml);

        if (!$xml) {
                $xml = urldecode($response_xml);
                $xml = base64_decode($xml);
                $xml = XML::parse($xml);
        }
        static::$payresult = $xml;
        return static::$payresult;
    }
	
}
