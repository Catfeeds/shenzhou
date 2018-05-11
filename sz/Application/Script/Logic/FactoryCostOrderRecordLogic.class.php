<?php
/**
 * Created by Sublime Text
 * User: zjz
 * Date: 2017/10/11
 * PM 16:44
 */
namespace Script\Logic;

use Script\Logic\DbLogic;
use Script\Model\BaseModel;

class FactoryCostOrderRecordLogic extends DbLogic
{
	
	protected $rule;
	protected $resultRule;
	protected $structureKey = 'factory_cost_order_record';
	protected $ignoreIdArr  = [];
	protected $ignoreIdArrRecord  = [];
	const FACTORY_COST_ORDER = 'factory_cost_order';
	const RULE_NUMS = 1000;
	const TYPE_DEFAULT = 'TYDE';
	const VAR_DATA = [
 		'WI' 	=> 4000,
 		'SA' 	=> 1000,
 		'N_SA' 	=> 1001,
 		'SFA' 	=> 1002,
 		'N_SFA' => 1003,
 		'FA' 	=> 2000,
 		'N_FA' 	=> 2001,
 		'TYDE' 	=> 0,
	];

	function __construct($rule) {
       	$this->rule = $rule ? $rule : $this->isDbStructure($this->structureKey);
    }

    public function syncTimeCheckAndUpdateWhere(&$merge = [])
    {
    	$data = $this->setOrderAtModel($this->structureKey)->getList([
    			'field' => 'id',
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
    				if ($ids) {
    				 	$return['id'] = ['in', $ids];
    				 	if ($where['id']) {
    				 		$where['_complex'] = [
								'_logic'   => 'and',
								'id' => $return['id'],
							];
    				 	} else {
    				 		$where['id'] = $return['id'];
    				 	}
						
    				} 
    				break;
    		}
    	}
    	return $return;
    }

    // 数据开始迁移
	public function sqlDataTransfer($arr = [])
	{
		set_time_limit(0);
		$where = [];

		$not_in_order_ids = $this->checkNullWorkerOrderIds();
		$not_in_order_ids && $where['cost_order_id'] = ['not in', $not_in_order_ids];

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
				'field' => 'id,cost_order_id as worker_order_apply_cost_id,add_time as create_time,ope_user_id as user_id,ope_type as type,operation as operation_content,`desc` as operation_remark',
				'where' => $where,
				'limit' => $rule_nums,
				'order' => 'id asc',
			]);
			
			foreach ($data as $k => $v) {
				$v['type'] = $this->getUserType($v['type'], $v['operation_content']);
				$data[$k] = $v;
			}

			$data && $db_model->insertAll($data);

			$end = end($data);
			$end['id'] && $where['_string'] = ' id > '.$end['id'].' ';
			unset($data);
		} while ($end);
		
		return $return;
	}

	public function getUserType($type = '', $operation = '')
	{
		$return = 0;

		$in_check = ['SA', 'SFA', 'FA'];
    	if (in_array($type, $in_check)) {
			switch ($type) {
				case 'SA':
					if (count(explode('不通过', $operation)) > 1) {
    					$type = 'N_SA';
    				}
					break;

				case 'SFA':
					if (count(explode('不通过', $operation)) > 1) {
    					$type = 'N_SFA';
    				}
					break;

				case 'FA':
					if (count(explode('不通过', $operation)) > 1) {
    					$type = 'N_FA';
    				}
					break;
			}
		}
		$return = isset(self::VAR_DATA[$type]) ? self::VAR_DATA[$type] : self::VAR_DATA[self::TYPE_DEFAULT];
		return $return;
	}

	public function checkNullWorkerOrderIds()
	{
		$model = $this->setOrderAtModel(self::FACTORY_COST_ORDER);
		$list = $model->getList([
				'alias' => 'a',
				'join'	=> 'left join worker_order b on a.worker_order_id = b.order_id',
				'field' => 'a.id',
				'where' => [
					'_string' => ' b.order_id is null ',
				],
				'index' => 'id',
			]);
		
		$this->ignoreIdArr = array_merge($this->ignoreIdArr, array_keys($list));
		return implode(',', array_unique($this->ignoreIdArr));
	}

	public function checkNullFactoryCostIds($value='')
	{
		$table = 'factory_cost_order_record';
		$model = $this->setOrderAtModel($table);
		$list = $model->getList([
				'alias' => 'a',
				'join'	=> 'left join factory_cost_order b on a.cost_order_id = b.id',
				'field' => 'a.cost_order_id',
				'where' => [
					'_string' => ' b.id is null ',
				],
				'group' => 'a.cost_order_id',
				'index' => 'cost_order_id',
			]);

		$this->ignoreIdArrRecord = array_merge($this->ignoreIdArrRecord, $this->ignoreIdArr, array_keys($list));
		return implode(',', array_unique($this->ignoreIdArrRecord));
	}


	public function structureResult()
	{
		// 删除
		$this->createNewStructures(true);
		// 新增
		$this->createNewStructures();
		
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
		$nums = $data['id'] ? $data['id']+1 : 0;

		$sql = <<<MYSQL
		create table {$this->rule['db_name']}
		(
		   id                   int(11) not null auto_increment,
		   worker_order_apply_cost_id int(11) not null,
		   create_time          int not null comment 'add_time,记录时间',
		   user_id              int not null comment 'ope_user_id,操作人ID',
		   type                 varchar(50) not null comment 'ope_type,操作类型  （区间=角色）worker 技工  factory 厂家 admin 客服,1000~1999       平台客服(1000:客服审核通过；1001 客服审核不通过；1002:客服代厂家审核通过；1003 客服代厂家审核不通过),2000~2999       厂家客服 (2000厂家审核通过；2001厂家审核不通过),3000~3999       厂家子帐号(3000厂家子帐号审核通过；3001厂家子帐号审核不通过),4000~4999       技工 (4000 申请费用单)',
		   operation_content    varchar(255) not null comment 'operation,操作内容',
		   operation_remark     text not null comment 'desc,备注',

		   primary key (id)
		) ENGINE=INNODB AUTO_INCREMENT={$nums} DEFAULT CHARSET=utf8 comment='费用申请单操作记录';
MYSQL;
		$this->resultRule[] = $this->sqlRunEnd($sql);
		$this->rule['is_db_name'] = $this->tableShowStatus($this->resultRule['db_name']);
	}

}
