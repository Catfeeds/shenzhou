<?php
/**
 * File: WorkerExtractedSendNotification.class.php
 * User: xieguoqiu
 * Date: 2017/3/23 16:35
 */

namespace Common\Common\Repositories\Listeners;

use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;
use Common\Common\Model\BaseModel;
use Common\Common\Service\SMSService;
use Common\Common\ServiceAuthService;
use Common\Common\Logic\Sms\SmsServerLogic;

class CreateFactoryExcdeApplySendNotification implements ListenerInterface
{
    public function handle(EventAbstract $event)
    {
        try {
        	if (!C('FACTORY_EXCEL_APPLY_SMS_PHONE')) {
        		return;
        	}

        	$excel = BaseModel::getInstance('factory_excel')->getOneOrFail($event->data);

        	$fdata = BaseModel::getInstance('factory')->getOne($excel['factory_id'], 'factory_full_name');

        	$type_ids = implode(',', array_filter([$excel['qr_type'], $excel['qr_guige']]));
            $yima_qr = $type_ids ? BaseModel::getInstance('yima_qr_category')->getList([
				'where' => [
			   		'id' => ['in', $type_ids],
			   	],
			]) : [];
			$str = arrFieldForStr($yima_qr, 'title', '');

            $sms_content = "{$fdata['factory_full_name']}申请了{$excel['nums']}个二维码，标签类型为：{$str}，请及时处理。";
            sendSms(C('FACTORY_EXCEL_APPLY_SMS_PHONE'), SMSService::TMP_CREATE_FACTORY_EXCDE_APPLY, [
                'factory_name' => $fdata['factory_full_name'],
                'nums' => $excel['nums'],
                'str' => $str,
            ]);
//        	$data = [
//        		'table_id' => 0,
//                'phone'    => C('FACTORY_EXCEL_APPLY_SMS_PHONE'),
//                'content'  => $sms_content,
//                'type'     => 30,
//        	];
//        	$add_datas[] = $data;
//
//        	(new SmsServerLogic('queue_message', true))->addTemporary($add_datas);
        } catch (\Exception $e) {

        }

    }

}
