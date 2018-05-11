<?php
/**
 * Created by Sublime Text
 * User: zjz
 * Date: 2017/10/27
 * PM 16:44
 */
namespace Script\Logic;

use Script\Logic\DbLogic;
use Script\Model\BaseModel;

class FactoryMoneyFrozenLogic extends DbLogic
{
	protected $rule;
	protected $resultRule;
	protected $structureKey = 'factory_money_frozen';
	const RULE_NUMS = 1000;

	function __construct($rule) {
       	$this->rule = $rule ? $rule : $this->isDbStructure($this->structureKey);
    }

    public function syncTimeCheckAndUpdateWhere(&$merge = [])
    {
    	$data = $this->setOrderAtModel($this->structureKey)->getList([
    			'field' => 'id,order_id',
    			'where' => [
    				C('SYNC_TIME') => ['gt', $this->syncTimeWhere(NOW_TIME)],
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

		// $db_model = $this->setOrderAtModel($this->rule['db_name'], false);  // 新数据库的model
		// $ch_model = $this->setOrderAtModel($this->structureKey);  // 旧数据库的model

		$where = [];

		if ($arr) {
    		$delete = $this->sqlDataTransferWhere($where, $arr);
    		$this->setOrderAtModel($this->rule['db_name'], false)->remove($delete);
    	}

		// $num = $ch_model->getNum($where);
		$rule_nums = self::RULE_NUMS;
		// $foreach_num = ceil($num/$rule_nums);

		$return = [];
		// for ($i=1; $i <= $foreach_num; $i++) {
		do {
			$i += 1;
			$db_model = $this->setOrderAtModel($this->rule['db_name'], false);
			$ch_model = $this->setOrderAtModel($this->structureKey);  // 旧数据库的model

			$data = $ch_model->getList([
				'field' => 'id,factory_id,order_id as worker_order_id,orno,frozen_money,add_time as create_time,type',
				'where' => $where,
				'limit' => $rule_nums,
				'order' => 'id asc',
			]);

			$data && $db_model->insertAll($data);

			$end = end($data);
			$end['id'] && $where['_string'] = ' id > '.$end['id'].' ';
			
			unset($data);
		} while ($end);
		// }
		return [];
	}

	public function structureResult()
	{
		// 删除
		$this->createNewStructures(true);
		$this->factoryMoneyFrozenRecord(true);
		// 新增
		$this->createNewStructures();
		$this->factoryMoneyFrozenRecord();
		return $this->resultRule;
	}

	public function createNewStructures($is_delete = false)
	{
		$result = $this->deleteTable($this->rule['db_name']);
		$this->resultRule[] = $result['sql'];
		if ($is_delete) {
			$this->rule['is_db_name'] = $this->tableShowStatus($this->rule['db_name']);
			return $result;
		}
		
		$b_model = $this->setOrderAtModel($this->structureKey);
		$data = $b_model->getOne(['order' => 'id DESC', 'field' => 'id']);
		$nums = $data['id'] ? $data['id'] : 0;

		$sql = <<<MYSQL
		create table {$this->rule['db_name']}
		(
		   id                   int(11) not null auto_increment,
		   factory_id           int(11) not null,
		   worker_order_id      int(11) not null comment 'order_id，维修工单id',
		   orno                 char(16) not null comment '工单号',
		   frozen_money         decimal(10,2) not null default 0.00 comment '工单冻结资金',
		   create_time             int not null comment '添加时间',
		   type                 smallint not null default 0 comment '0预估 1结算，(0 平台财务审核之前， 1平台财务审核之后)',
		   primary key (id)
		)ENGINE=INNODB AUTO_INCREMENT={$nums} DEFAULT CHARSET=utf8 comment='厂家的工单冻结金';
MYSQL;

		$this->resultRule[] = $this->sqlRunEnd($sql);
		$this->rule['is_db_name'] = $this->tableShowStatus($this->resultRule['db_name']);
	}

	public function factoryMoneyFrozenRecord($is_delete = false)
	{
		$table = 'factory_money_frozen_record';
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
		   factory_id           int(11) not null,
		   worker_order_id      int(11) not null comment 'order_id，维修工单id',
		   type                 tinyint not null default 0 comment '1 工单通过厂家审核(厂家下单)；2 客服修改工单产品信息；3 技工上传工单产品维修项；4 客服修改工单产品维修项；5 工单结算成功；',
		   frozen_money         decimal(10,2) not null default 0.00,
		   last_frozen_money    decimal(10,2) not null default 0.00,
		   create_time          int not null,
		   primary key (id)
		)ENGINE=INNODB DEFAULT CHARSET=utf8 comment='厂家的工单冻结金变动记录';
MYSQL;

		$this->resultRule[] = $this->sqlRunEnd($sql);
	}
	
}
