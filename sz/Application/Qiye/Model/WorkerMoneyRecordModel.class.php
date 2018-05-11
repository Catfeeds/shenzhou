<?php

/**
 * File: WorkerMoneyRecordModel.class.php
 * User: zjz
 * Date: 2017/11/24
 */

namespace Qiye\Model;

use Qiye\Model\BaseModel;
use Common\Common\Service\WorkerService;

class WorkerMoneyRecordModel extends BaseModel
{
	const WORKER_QUALITY_TABLE_NAME = 'worker_quality_money_record';

	public function getWorkerMoneyTotal($worker_id, $type = 0)
	{
		$all_money = 0;
		$where = [
			'worker_id' => $worker_id,
		];
		switch ($type) {
			case WorkerService::WORKER_MONEY_REPAIR_TYPE:
				$where['type'] = ['in', WorkerService::WORKER_MONEY_RECORD_REPAIR.','.WorkerService::WORKER_MONEY_RECORD_REPAIR_OUT];
				$all_money = $this->where($where)->Sum('money');
				break;

			case WorkerService::WORKER_MONEY_ADJUST_RECORD_TYPE:
				$where['type'] = ['in', WorkerService::WORKER_MONEY_RECORD_ADJUST.','.WorkerService::WORKER_MONEY_SYSTEM_ADJUST];
				$all_money = $this->where($where)->Sum('money');
				break;

			case WorkerService::WORKER_MONEY_WITHDRAWCASH_RECORD_TYPE:
				$in = WorkerService::WORKER_MONEY_RECORD_WITHDRAWCASHING.','.WorkerService::WORKER_MONEY_RECORD_WITHDRAWCASHED;
				$where['type'] = ['in', $in];
				$all_money = $this->where($where)->Sum('-`money`');
				break;

			case WorkerService::WORKER_MONEY_RECORD_QUALITY_TYPE:
				$all_money = BaseModel::getInstance(self::WORKER_QUALITY_TABLE_NAME)->where($where)->Sum('quality_money');
				break;

			default:
				// $in = WorkerService::WORKER_MONEY_RECORD_REPAIR.','.WorkerService::WORKER_MONEY_RECORD_ADJUST.','.WorkerService::WORKER_MONEY_RECORD_WITHDRAWCASHING.','.WorkerService::WORKER_MONEY_RECORD_WITHDRAWCASHED;
				// $where['type'] = ['in', $in];
				$all_money = $this->where($where)->Sum('money');
				break;
		}

		return $all_money;
	}

	public function countAllMoneuRecoredById($worker_id, $where = [], $field = '*', $limit = 0, $order_by = 'create_time desc')
	{
		$where['worker_id'] = $worker_id;
		$opt = [
			'where' => $where,
			'field' => $field,
			'order' => $order_by,
			'limit' => $limit,
		];
		return $field == 'count' ? 
			   $this->getNum($opt) : 
			   $this->getList($opt);
	}

}
