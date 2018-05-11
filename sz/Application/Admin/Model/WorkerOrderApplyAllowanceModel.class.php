<?php
/**
 * User: zjz
 * Date: 2017/11/02
 * Time: 10:31
 */
namespace Admin\Model;

use Common\Common\Service\AllowanceService;

class WorkerOrderApplyAllowanceModel extends BaseModel
{
	
	public function getNotCompleteNumsByOid($order_id = 0)
	{
		return $this->getNum([
			'worker_order_id' => $order_id,
			'status' => AllowanceService::STATUS_UNCHECKED,
		]);
	}
	
}
