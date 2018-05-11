<?php
/**
* 
*/
namespace Admin\Controller;

use Common\Common\Service\PayService;
use Common\Common\Service\YiLianService\util\TripleDES;
use Common\Common\Service\YiLianService\KeyConfigService;
use Common\Common\Service\PayPlatformRecordService;
use Library\Common\Util;
use Library\Common\XML;
use Admin\Model\BaseModel;

class PayController extends BaseController
{
	const FACTORY_MONEY_CHANGE_TABLE_NAME = 'factory_money_change_record';
    const PAY_RECORD_TABLE_NAME = 'pay_platform_record';
    const FACTORY_TABLE_NAME = 'factory';
    const FACTORY_ADMIN_TABLE_NAME = 'factory_admin';

	public function returnedAsyn()
	{

		try {
			$type = I('get.type', 0, 'intval');	// 支付回调支付系统
			$response_text = I('post.response_text');
			// 支付结果解析
			PayService::initDiy($type, $response_text);
			// 支付结果处理
			M()->startTrans();
			PayPlatformRecordService::paymentResult();
	        M()->commit();

			PayPlatformRecordService::asynResponse();
		} catch (\Exception $e) {
			file_put_contents('./xml.xml', $e);
			exit('0001');
			// $this->getExceptionError($e);
		}
	}

	public function returnedSyn()
	{
		try {
			$type = I('type', 0, 'intval');	// 支付回调支付系统
			$response_text = I('response_text');
			// 支付结果解析
			PayService::initDiy($type, $response_text);
			// 支付结果处理
//			M()->startTrans();
//			PayPlatformRecordService::paymentResult();
//	        M()->commit();

            PayPlatformRecordService::autoSetPayLog();
            $id = PayPlatformRecordService::$paylog['id'];
            $platform_order_no = PayPlatformRecordService::$paylog['platform_order_no'];
            $no = PayService::getPlatformOrderNo();
            $id && empty($platform_order_no) && !empty($no) &&  BaseModel::getInstance('pay_platform_record')->update($id, [
                'platform_order_no' => $no,
            ]);

			PayPlatformRecordService::goToSynUrl();
			$this->response();
		} catch (\Exception $e) {
			$this->getExceptionError($e);
		}
		
	}
	
	public function testPayPc()
	{
		try {
			$type = 1;
			$date = date('Ymd', NOW_TIME);
			$yilian_pay_number = PayService::PAY_NUMBER_PRE."{$date}".rand(111111, 999999);
			$data = [
				'amount' 			=> '0.01',
				'description'		=> 'orderen test',
				'remark'			=> '下单测试 封装类测试',
				'yilian_pay_number'	=> $yilian_pay_number,
				// 'syn_address'		=> Util::getServerUrl().__SELF__, // 同步通知接口
				'syn_address'		=> Util::getServerUrl().__APP__.'/yilian/returned/syn/'.$type, // 同步通知接口
				'asyn_address'		=> Util::getServerUrl().__APP__.'/yilian/returned/asyn/'.$type, // 异步通知接口
			];

			PayService::initDiy(PayService::PLATFORM_TYPE_YILIAN_VALUE)->createOrder($data);
		} catch (\Exception $e) {
			$this->getExceptionError($e);
		}
	}
	
}
