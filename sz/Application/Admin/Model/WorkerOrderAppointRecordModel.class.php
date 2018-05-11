<?php
/**
 * User: zjz
 * Date: 2017/11/02
 * Time: 10:31
 */
namespace Admin\Model;

class WorkerOrderAppointRecordModel extends BaseModel
{
	
	public function getOverNumsByOid($order_id = 0)
	{
		return $this->getNum([
			'is_over' => 1,
			'worker_order_id' => $order_id,
		]);
	}
	
}
