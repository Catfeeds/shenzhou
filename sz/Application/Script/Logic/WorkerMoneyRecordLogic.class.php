<?php
/**
 * Created by Sublime Text
 * User: zjz
 * Date: 2017/11/07
 * PM 10:44
 */
namespace Script\Logic;

use Script\Logic\DbLogic;
use Script\Model\BaseModel;

class WorkerMoneyRecordLogic extends DbLogic
{
	
	protected $rule;
	protected $resultRule;
	protected $structureKey = 'worker_money_record';
	const RULE_NUMS = 1000;
	const WORKER_MONEY_IN_RECORD = 'worker_money_in_record';
	const WORKER_MONEY_ADJUST_RECORD = 'worker_money_adjust_record';
	const WORKER_MONEY_OUT_RECORD = 'worker_money_out_record';
	const OUT_RECORD_TYPE = '0,1,4';
	
	function __construct($rule) {
       	$this->rule = $rule ? $rule : $this->isDbStructure($this->structureKey);
    }

    public function syncTimeCheckAndUpdateWhere(&$merge = [])
    {

    	$data_1 = (array)$this->setOrderAtModel(self::WORKER_MONEY_IN_RECORD)->getList([
    			'field' => '1 as type,id',
    			'where' => [
    				C('SYNC_TIME') => ['gt', $this->syncTimeWhere(0, self::WORKER_MONEY_IN_RECORD)],
    			],
    		]);
    	
    	$data_2 = (array)$this->setOrderAtModel(self::WORKER_MONEY_ADJUST_RECORD)->getList([
    			'field' => '2 as type,id',
    			'where' => [
    				C('SYNC_TIME') => ['gt', $this->syncTimeWhere(0, self::WORKER_MONEY_ADJUST_RECORD)],
    			],
    		]);
    	$data_3 = (array)$this->setOrderAtModel(self::WORKER_MONEY_OUT_RECORD)->getList([
    			'field' => 'IF(status=1,4,3) as type,id',
    			'where' => [
    				C('SYNC_TIME') => ['gt', $this->syncTimeWhere(0, self::WORKER_MONEY_OUT_RECORD)],
    				'status' => ['in', self::OUT_RECORD_TYPE],
    			],
    		]);

    	$type_arr = [
    		'1' => 'worker_money_in_record_id',
    		'2' => 'worker_money_adjust_record_id',
    		'3' => 'worker_money_out_record_id',
    		'4' => 'worker_money_out_record_id',
    	];
    	foreach (array_merge($data_1, $data_2, $data_3) as $key => $value) {
    		$k = $type_arr[$value['type']];
    		if (!$k) {
    		 	continue;
    		 }

    		$merge[$k][] = $value['id'];
    	}
    }

    public function sqlDataTransferWhere(&$where, $arr = [])
    {
    	$return = [];
    	foreach ($arr as $key => $value) {
    		switch ($key) {
    			case 'worker_money_in_record_id':
    				$ids = implode(',', array_unique(array_filter($value)));
    				$where['id'] = $return[C('TRANSFER_EXTEND_ID')] = ['in', $ids];
    				$return['type'] = 1;
    				break;

				case 'worker_money_adjust_record_id':
    				$ids = implode(',', array_unique(array_filter($value)));
    				$where['id'] = $return[C('TRANSFER_EXTEND_ID')] = ['in', $ids];
    				$return['type'] = 2;
    				break;

    			case 'worker_money_out_record_id':
    				$ids = implode(',', array_unique(array_filter($value)));
    				$where['id'] = $return[C('TRANSFER_EXTEND_ID')] = ['in', $ids];
    				$return['type'] = ['in', '3,4'];
    				break;    			
    		}
    	}
    	return $return;
    }

    public function sqlDataTransfer($arr = [])
    {
    	$this->transferWorkerMoneyInRecord($arr);
    	$this->transferWorkerMoneyAdjustRecord($arr);
    	$this->transferWorkerMoneyOutRecord($arr);
    }

    public function transferWorkerMoneyInRecord($arr = [])
    {
    	$table = $this->structureKey;
    	$old_table = self::WORKER_MONEY_IN_RECORD;
    	set_time_limit(0);
		$where = [
			'type' => 0,
		];

		if ($arr) {
			$check['worker_money_in_record_id'] = $arr['worker_money_in_record_id'];
			$delete = $this->sqlDataTransferWhere($where, $check);
			if (!$arr['worker_money_in_record_id']) {
				$delete[C('TRANSFER_EXTEND_ID')] = false;
				$where['id'] = false;
			}
			$db_model = $this->setOrderAtModel($table, false)->remove($delete);
		}

		$rule_nums = self::RULE_NUMS;
		$return = [];
		$last = [];
		do {
			$i += 1;
			$db_model = $this->setOrderAtModel($table, false);
			$ch_model = $this->setOrderAtModel($old_table);  // 旧数据库的model

			$data = $ch_model->getList([
				'field' => 'worker_id,1 as type,order_id as data_id,netreceipts_money as money,last_money,add_time as create_time,id as '.C('TRANSFER_EXTEND_ID'),
				'where' => $where,
				'limit' => $rule_nums,
				'order' => 'id asc',
			]);

			$data && $db_model->insertAll($data);
			
			$end = end($data);
			$end[C('TRANSFER_EXTEND_ID')] && $where['_string'] = ' id > '.$end[C('TRANSFER_EXTEND_ID')].' ';

			unset($data);
		} while ($end);

		return $return;
    }

    public function transferWorkerMoneyAdjustRecord($arr = [])
    {
    	$table = $this->structureKey;
    	$old_table = self::WORKER_MONEY_ADJUST_RECORD;
    	set_time_limit(0);
		$where = [];

		if ($arr) {	
			$check['worker_money_adjust_record_id'] = $arr['worker_money_adjust_record_id'];
			$delete = $this->sqlDataTransferWhere($where, $check);
			if (!$arr['worker_money_adjust_record_id']) {
				$delete[C('TRANSFER_EXTEND_ID')] = false;
				$where['id'] = false;
			}
			$db_model = $this->setOrderAtModel($table, false)->remove($delete);
		}

		$rule_nums = self::RULE_NUMS;
		$return = [];
		$last = [];
		do {
			$i += 1;
			$db_model = $this->setOrderAtModel($table, false);
			$ch_model = $this->setOrderAtModel($old_table);  // 旧数据库的model

			$data = $ch_model->getList([
				'field' => 'worker_id,2 as type,id as data_id,add_money as money,last_money,add_time as create_time,id as '.C('TRANSFER_EXTEND_ID'),
				'where' => $where,
				'limit' => $rule_nums,
				'order' => 'id asc',
			]);
				
			$data && $db_model->insertAll($data);

			$end = end($data);
			$end['data_id'] && $where['_string'] = ' id > '.$end['data_id'].' ';
			unset($data);
		} while ($end);

		return $return;
    }

    public function transferWorkerMoneyOutRecord($arr = [])
    {
    	$table = $this->structureKey;
    	$old_table = self::WORKER_MONEY_OUT_RECORD;
    	set_time_limit(0);
		$where = [
			'status' => ['in', self::OUT_RECORD_TYPE],
		];

		if ($arr) {
			$check['worker_money_out_record_id'] = $arr['worker_money_out_record_id'];
			$delete = $this->sqlDataTransferWhere($where, $check);
			if (!$arr['worker_money_out_record_id']) {
				$delete[C('TRANSFER_EXTEND_ID')] = false;
				$where['id'] = false;
			}
			$db_model = $this->setOrderAtModel($table, false)->remove($delete);
		}

		$rule_nums = self::RULE_NUMS;
		$return = [];
		$last = [];
		$worker_ids = [];
		do {
			$i += 1;
			$db_model = $this->setOrderAtModel($table, false);
			$ch_model = $this->setOrderAtModel($old_table);  // 旧数据库的model

			$data = $ch_model->getList([
				// 'field' => 'worker_id,IF(status=1,4,3) as type,id as data_id,out_money as money,0 as last_money,complete_time as create_time',
				'field' => 'worker_id,IF(status=1,4,3) as type,id as data_id,-`out_money` as money,0 as last_money,add_time as create_time,id as '.C('TRANSFER_EXTEND_ID'),
				'where' => $where,
				'limit' => $rule_nums,
				'order' => 'id asc',
			]);

			foreach ($data as $k => $v) {
				$worker_ids[$v['worker_id']] = $v['worker_id'];
			}

			$data && $db_model->insertAll($data);

			$end = end($data);
			$end['data_id'] && $where['_string'] = ' id > '.$end['data_id'].' ';
			unset($data);
		} while ($end);

		$data && $this->transferUpdateWorkerMoneyRecord($worker_ids);

		return $return;
    }

    public function transferUpdateWorkerMoneyRecord($worker_ids = [])
    {
    	$table = $this->structureKey;
    	if (!$worker_ids) {
    		$list = $this->setOrderAtModel($table, false)->getList([
    			'field' => 'worker_id',
    			'where' => [
    				'type' => ['in', '3,4'],
    			],
    			'group' => 'worker_id',
    			'index' => 'worker_id',
    		]);
    		$worker_ids = array_keys($list);
    	}

    	foreach ($worker_ids as $worker_id) {
			$db_model = $this->setOrderAtModel($table, false);
			$up_where = [
				'field' => 'id,type,last_money,money',
				'where' => [
					'worker_id' => $worker_id
				],
				'order' => 'create_time asc',
			];
			$pre = [];
			foreach ($db_model->getList($up_where) as $k => $v) {
				if ($v['type'] == 3 || $v['type'] == 4) {
					$v['last_money'] = $pre['last_money'] + $v['money'];
					$db_model->update($v['id'], [
							'last_money' => $v['last_money'],
						]);
				}
				$pre = $v;
			}
		}
    }

    public function structureResult()
    {
    	$this->createNewStructures(true);

    	$this->createNewStructures();
    }

    protected function createNewStructures($is_delete = false)
	{
		$result = $this->deleteTable($this->rule['db_name']);
		$this->resultRule[] = $result['sql'];
		if ($is_delete) {
			$this->rule['is_db_name'] = $this->tableShowStatus($this->rule['db_name']);
			return $result;
		}
		
		$sql = <<<MYSQL
		create table {$this->rule['db_name']}
		(
		   id                   int(11) not null auto_increment,
		   worker_id            int(11) not null comment 'worker_id;技工id',
		   type                 tinyint not null default 0 comment '1 工单维修金收入；2 奖惩记录（客服手动调整钱包）；3 技工提现申请(提现中)；4  技工提现申请(提现成功),5 工单保外单（维修金收入）,6 系统变动（系统自动调整钱包）',
		   data_id              int not null default 0 comment '记录类型标记的id',
		   money                decimal(10,2) not null default 0.00 comment '变动资金',
		   last_money           decimal(10,2) not null default 0.00 comment '变动资金',
		   create_time          int not null,
		   transfer_extend_id   int not null default 0,
		   primary key (id),
		   KEY `worker_id` (`worker_id`)
		) ENGINE=INNODB DEFAULT CHARSET=utf8 comment='技工资金变动记录';
MYSQL;
		$this->resultRule[] = $this->sqlRunEnd($sql);
		$this->rule['is_db_name'] = $this->tableShowStatus($this->resultRule['db_name']);
	}
	
}
