<?php
/**
 * @author fengmuhai
 * @date 2016-12-28
 */
namespace Common\Common\Service\YiLianService\entity;

use Common\Common\Service\YiLianService\util\MD5;
use Common\Common\Service\YiLianService\KeyConfigService;

class MerchantMessage {
	public $Version           = ""; //版本号
	public $procCode          = ""; //消息类型
	public $processCode       = ""; //处理码
	public $accountNo         = ""; //卡号
	public $amount            = ""; //金额
	public $currency          = ""; //币种

	public $remark            = ""; //备注
	public $terminalNo        = ""; //终端号
	public $merchantNo        = ""; //商户号
	public $merchantOrderNo   = ""; //商户订单号
	public $orderFrom         = ""; //订单来源
	public $language          = ""; //语种
	public $description       = ""; //订单描述
	public $orderType         = ""; //下单类型
	public $acqSsn            = ""; //系统跟踪号
	public $reference         = ""; //系统参考
	public $transDatetime     = ""; //传输时间
	public $uiLanguage        = ""; //UI语言
	public $transData         = ""; //其他数据
	public $synAddress        = ""; //同步地址
	public $asynAddress       = ""; //异步地址

	public $respCode          = ""; //响应码
	public $orderState        = ""; //订单状态
	public $upsNo             = ""; //易联流水号
	public $tsNo              = ""; //易联终端流水号
	public $orderNo           = ""; //下单返回的订单号

	public $mac               = ""; //校验码
   public $sdkExtData        = ""; //不知道什么东西 

      function __construct()
      {
         $this->setCommonData();
      }

   	function computeMac($password) {
   		//先转换成大写再MAC
   		$data = strtoupper($this->getMacString()." ".$password);
   		$md5 = new MD5();
   		return $md5->getMD5ofStr($data);
   	}

   	function getMacString() {
   		$macStr = '';
   		$macStr .= $this->procCode
   				  .$this->getAppendString($this->accountNo)
   				  .$this->getAppendString($this->processCode)
   				  .$this->getAppendString($this->amount)
   				  .$this->getAppendString($this->transDatetime)
   				  .$this->getAppendString($this->acqSsn)
   				  .$this->getAppendString($this->orderNo)
   				  .$this->getAppendString($this->transData)
   				  .$this->getAppendString($this->reference)
   				  .$this->getAppendString($this->respCode)
   				  .$this->getAppendString($this->terminalNo)
   				  .$this->getAppendString($this->merchantNo)
   				  .$this->getAppendString($this->merchantOrderNo)
   				  .$this->getAppendString($this->orderState);
   		return $macStr;
   	}

   	function getAppendString($src) {
   		if($src) {
   			return " ".$src;
   		}
   		return '';
   	}

   	function toXml() {
        $xml_data = array(
            'Version'   => $this->version,
            'ProcCode'  => $this->procCode,
            'ProcessCode' => $this->processCode,
            'AccountNo' => $this->accountNo,
            'Amount'    => $this->amount,
            'Currency'  => $this->currency,
            'TerminalNo' => $this->terminalNo,
            'MerchantNo' => $this->merchantNo,
            'MerchantOrderNo' => $this->merchantOrderNo,
            'OrderNo' => $this->orderNo,
            'UILanguage' => $this->uiLanguage,
            'Description' => $this->description,
            'AcqSsn' => $this->acqSsn,
            'TransDatetime' => $this->transDatetime,
            'TransData' => $this->transData,
            'Reference' => $this->reference,
            'Remark' => $this->remark,
            'RespCode' => $this->respCode,
            'OrderState' => $this->orderState,
            'UpsNo' => $this->upsNo,
            'TsNo' => $this->tsNo,
            'SynAddress' => $this->synAddress,
            'AsynAddress' => $this->asynAddress,
            'OrderFrom' => $this->orderFrom,
            'Mac'   => $this->mac,
        );
   	$dom = new \DomDocument('1.0','utf-8');
		$root = $dom->createElement('x:NetworkRequest');
		$root->setAttribute("xmlns:x", "http://www.payeco.com");
		$root->setAttribute("xmlns:xsi", "http://www.w3.org"); 
		$dom->appendchild($root);
		foreach($xml_data as $k=>$val){
		    $item = $dom->createElement($k);
		    $root->appendchild($item);
		    $value = $dom->createTextNode($val);
		    $item->appendChild($value);
		}
		$xml = $dom->saveXML();
		//remove backspace
		$xml=str_replace("\n","",$xml);
		$xml=str_replace("\r","",$xml);
		$xml=str_replace("\r\n","",$xml);

		return $xml;
   	}

      // zjz
      function setCommonData()
      {
         $this->version         = KeyConfigService::CONFIG_YILIAN_VERSION;
         $this->procCode        = KeyConfigService::CONFIG_PROCODE_TYPE_0200;
         $this->processCode     = KeyConfigService::CONFIG_PROCESS_CODE;
         // $this->merchantNo      = KeyConfigService::CONFIG_MERCHANT_NUMBER;
         $this->merchantNo      = C('YILIAN_CONFIG_MERCHANT_NUMBER');
         $this->currency        = KeyConfigService::CURCODE_CNY;
      }
}
?>