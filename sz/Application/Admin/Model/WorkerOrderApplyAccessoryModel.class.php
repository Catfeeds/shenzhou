<?php
/**
 * User: zjz
 * Date: 2017/11/02
 * Time: 10:31
 */
namespace Admin\Model;

use Common\Common\Service\AccessoryService;

class WorkerOrderApplyAccessoryModel extends BaseModel
{
	
	// 只要配件单技工有返件（status>=8），并且返件费支付类型是现付的（worker_return_pay_method = 1），就算配件单被取消了也要算进worker_order_fee。
	public function getNotCompleteNumsByOid($order_id = 0)
	{
		return $this->getNum([
			'worker_order_id' => $order_id,
			'accessory_status' => ['in', implode(',', AccessoryService::STATUS_IS_ONGOING)],
			// 'accessory_status' => ['neq', AccessoryService::COMPLETE],
			'cancel_status' => AccessoryService::CANCEL_STATUS_NORMAL,
		]);
	}
	
}
