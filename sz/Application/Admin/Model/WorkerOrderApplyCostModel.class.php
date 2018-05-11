<?php
/**
 * User: zjz
 * Date: 2017/12/23
 * Time: 15:31
 */
namespace Admin\Model;

use Common\Common\Service\ApplyCostService;

class WorkerOrderApplyCostModel extends BaseModel
{
	
	public function getNotCompleteNumsByOid($order_id = 0)
	{
		return $this->getNum([
			'worker_order_id' => $order_id,
			'status' => ['in', implode(',', ApplyCostService::STATUS_IS_ONGOING)],
		]);
	}
	
}
