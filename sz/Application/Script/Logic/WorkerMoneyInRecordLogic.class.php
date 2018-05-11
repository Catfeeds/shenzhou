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

class WorkerMoneyInRecordLogic extends DbLogic
{
	
	protected $rule;
	protected $resultRule;
	protected $structureKey = 'worker_money_in_record';
	const RULE_NUMS = 1000;

	function __construct($rule) {
       	$this->rule = $rule ? $rule : $this->isDbStructure($this->structureKey);
    }

    public function syncTimeCheckAndUpdateWhere(&$merge = [])
    {
    	$data = $this->setOrderAtModel($this->structureKey)->getList([
    			'field' => 'id,order_id',
    			'where' => [
    				C('SYNC_TIME') => ['gt', $this->syncTimeWhere(0, $this->structureKey)],
    			],
    		]);
    	
    	foreach ($data as $key => $value) {
    		$merge['id'][] = $value['id'];
    	}
    }

    public function sqlDataTransferWhere(&$where, $arr = [])
    {
    	$return = [];
    	$p_ids = [];
    	foreach ($arr as $key => $value) {
    		switch ($key) {
    			case 'id':
    				$ids = implode(',', array_unique(array_filter($value)));
    				$where['id'] = $return['id'] = ['in', $ids];
    				break;
    		}
    	}
    	return $return;
    }

    public function sqlDataTransfer($arr = [])
    {
		set_time_limit(0);
		$where = ['type' => 0];

		if ($arr) {
			$delete = $this->sqlDataTransferWhere($where, $arr);
			$this->setOrderAtModel($this->rule['db_name'], false)->remove($delete);
		}

		$rule_nums = self::RULE_NUMS;
		$return = [];
		do {
			$i += 1;
			$db_model = $this->setOrderAtModel($this->rule['db_name'], false);
			$ch_model = $this->setOrderAtModel($this->structureKey);  // 旧数据库的model

			$data = $ch_model->getList([
				'field' => 'id,factory_id,worker_id,0 as admin_id,order_id as worker_order_id,order_money,netreceipts_money,"1.00" as insurance_fee,quality_money,last_money,last_quality_money,add_time as create_time',
				'where' => $where,
				'limit' => $rule_nums,
				'order' => 'id asc',
			]);
			// insurance_fee

			$data && $db_model->insertAll($data);

			$end = end($data);
			$end['id'] && $where['_string'] = ' id > '.$end['id'].' ';
			unset($data);
		} while ($end);

		$this->transferWorkerQualityMoneyRecord($arr);

		return $return;
    }

    public function transferWorkerQualityMoneyRecord($arr = [])
    {
    	$table = 'worker_quality_money_record';
    	set_time_limit(0);
		// $where = ['_string' => ' type = 1 or quality_money  <> 0.00 '];
		$where = [
			'_complex' => [
				'_logic' => 'or',
				'type'	 => 1,
				'quality_money'	 => ['neq', 0],
			],
		];

		$rule_nums = self::RULE_NUMS;
		$return = [];

		if ($arr) {
			/* 311078,311066,311060,29118 */
			$delete = $this->sqlDataTransferWhere($where, $arr);
			$this->setOrderAtModel($table, false)->remove($delete);
		}

		$admins = [];
		$end = ['id' => 0];
		do {
			$i += 1;
			$db_model = $this->setOrderAtModel($table, false);
			$ch_model = $this->setOrderAtModel($this->structureKey);  // 旧数据库的model

			$data = $ch_model->getList([
				'field' => 'id,worker_id,0 as admin_id,order_id as worker_order_id,order_sn,quality_money,last_quality_money,add_time as create_time,type,"" as remark',
				'where' => $where,
				'limit' => $rule_nums,
				'index' => 'id',
			]);

			$end = end($data);

			$admin = [];
			$repleace = [];
			foreach ($data as $k => $v) {
				// --操作人:
				$orno_qrr = explode('--操作人:', $v['order_sn']);
				$data[$k]['remark'] = $v['order_sn'] ?? '';
				$name = trim($orno_qrr[1]);
				if ($name && !isset($admins[$name])) {
					$admin[$name] = $name;
					$repleace[$name][] = $v['id'];
				}
				unset($data[$k]['order_sn']); // , $data[$k]['id']
			}

			if ($admin) {
				$list = BaseModel::getInstance('admin')->getList([
						'field' => 'id,nickout',
						'where' => [
							'nickout' => ['in', implode(',', $admin)
						],
						'index' => 'nickout',
					]]);
				foreach ($list as $value) {
					foreach ($repleace[$value['nickout']] as $k => $v) {
						$data[$v]['admin_id'] = $value['id'];
					}
				}
			}
			
			$data && $db_model->insertAll(array_values($data));

			$end['id'] && $where['_string'] = ' id > '.$end['id'].' ';
			unset($data);
		} while ($end);
		return $return;
    }

    public function structureResult()
    {
    	$this->createNewStructures(true);
    	$this->workerQualityMoneyRecord(true);

    	$this->createNewStructures();
    	$this->workerQualityMoneyRecord();
    }
    
	protected function createNewStructures($is_delete = false)
	{
		$result = $this->deleteTable($this->rule['db_name']);
		$this->resultRule[] = $result['sql'];
		if ($is_delete) {
			$this->rule['is_db_name'] = $this->tableShowStatus($this->rule['db_name']);
			return $result;
		}
		$b_model = $this->setOrderAtModel($this->structureKey);
		$data = $b_model->getOne(['order' => 'id DESC', 'field' => 'id']);
		$nums = $data['id'];
		foreach ($this->rule['other'] as $k => $v) {
			$che = [];
			$v = is_string($k) ?? $v;
			if (!$this->tableShowStatus($v)) {
				continue;
			}
			$che = $this->setOrderAtModel($v)->getOne(['order' => 'id DESC', 'field' => 'id']);
			$nums += $che['id'];
		}
		$nums = $nums ? $nums+1 : 0;
		$sql = <<<MYSQL
		create table {$this->rule['db_name']}
		(
		   id                   int(11) not null auto_increment,
		   worker_order_id      int(11) not null comment 'order_id，维修工单id',
		   worker_id            int(11) not null comment 'worker_id，技工id',
		   factory_id           int(11) not null,
		   order_money          decimal(10,2) not null default 0.00 comment '工单收入金额',
		   netreceipts_money    decimal(10,2) not null default 0.00 comment '实收（进入余额账户）',
		   insurance_fee        decimal(10,2) not null default 0.00 comment 'is_insurance_cost，需要缴纳的保险费， 单位元（RMB）',
		   quality_money        decimal(10,2) not null default 0.00 comment '工单质保金额（进账，该工单需要缴纳的质保金）',
		   last_money           decimal(10,2) not null default 0.00 comment '技工总余额',
		   last_quality_money   decimal(10,2) not null default 0.00 comment '调整后的质量保证金， 已缴纳质保金',
		   create_time          int not null default 0 comment 'add_time，结算时间',
		   primary key (id)
		) ENGINE=INNODB AUTO_INCREMENT={$nums} DEFAULT CHARSET=utf8 comment='技工 维修金（收入）记录';
MYSQL;
		$this->resultRule[] = $this->sqlRunEnd($sql);
		$this->rule['is_db_name'] = $this->tableShowStatus($this->resultRule['db_name']);
	}

	public function workerQualityMoneyRecord($is_delete = false)
	{
		$table = 'worker_quality_money_record';
		if ($is_delete) {
			$result = $this->deleteTable($table);
			$this->resultRule[] = $result['sql'];
			return $result;
		} elseif ($this->tableShowStatus($table)) {
			return false;
		}

		$sql = <<<MYSQL
		create table {$table}
		(
		   id                   int(11) not null auto_increment,
		   worker_id            int(11) not null comment 'worker_id,技工id',
		   admin_id             int(11),
		   worker_order_id      int(11) comment 'order_id,维修工单id',
		   type                 tinyint not null default 0 comment '0 工单结算自动扣除;1 客服手动更改',
		   quality_money        decimal(10,2) not null default 0.00 comment '正数表示扣除（技工交付）技工质保金，负数为增加（技工在原质保金上再增加）质保金',
		   last_quality_money   decimal(10,2) not null default 0.00 comment '变动后的质保金',
		   remark               varchar(255),
		   create_time          int not null,
		   primary key (id)
		) ENGINE=INNODB DEFAULT CHARSET=utf8 comment='质保金操作记录';
MYSQL;
		$this->resultRule[] = $this->sqlRunEnd($sql);
		$this->rule['is_db_name'] = $this->tableShowStatus($this->resultRule['db_name']);
	}
	
}
