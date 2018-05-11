<?php
/**
 * Created by Sublime Text
 * User: zjz
 * Date: 2017/10/14
 * PM 16:44
 */
namespace Script\Logic;

use Script\Logic\DbLogic;
use Script\Model\BaseModel;

class WorkerContactRecordLogic extends DbLogic
{
	
	protected $rule;
	protected $resultRule;
	protected $structureKey = 'worker_contact_record';
	const RULE_NUMS 		= 1000;
	const CONTACT_OBJECT_DETAULT = 0;
	const CONTACT_OBJECT_VALUE = [
		'维修商'			=> 1,
		'零售商'			=> 2,
		'零售商带维修商'	=> 3,
		'商家'			=> 4,
		'批发商'			=> 5,
		'批发商带维修商'	=> 6,
	];
	const CONTACT_METHOD_VALUE = [
		'电话'			=> 1,
		'微信'			=> 2,
		'QQ'			=> 3,
		'短信'			=> 4,
	];
	const CONTACT_TYPE_VALUE = [
		'派单咨询'		=> 1,
		'例行联系'		=> 2,
		'维修报价'		=> 3,
		'技术咨询'		=> 4,
		'代找网点'		=> 5,
		'其它'			=> 6,
	];
	const CONTACT_RESULT_VALUE = [
		'可以'			=> 1,
		'不可以'			=> 2,
		'其它'			=> 3,
	];
	const CONTACT_REPORT_VALUE = [
		'可以合作'		=> 1,
		'考虑合作'		=> 2,
		'不用再联系'		=> 3,
		'其它'			=> 4,
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
    				$where['id'] = $return['id'] = ['in', $ids];
    				break;
    		}
    	}
    	return $return;
    }

    // 数据开始迁移
	public function sqlDataTransfer($arr = [])
	{
		set_time_limit(0);

		$where = [
			'worker_id' => ['neq', 0],
		];

		if ($arr) {
			$delete = $this->sqlDataTransferWhere($where, $arr);
			$this->setOrderAtModel($this->rule['db_name'], false)->remove($delete);
		}

		$rule_nums = self::RULE_NUMS;
		$return = [];
		$field_arr = [
			'id',
			'admin_id as admin_id',
			'worker_id as worker_id',
			'0 as worker_order_id',
			'object_type as contact_object',
			'"" as contact_object_other',
			'contact_method',
			'contact_type',
			'contact_result',
			'contact_gu as contact_report',
			'contact_desc as contact_remark',
			'add_time as create_time',
		];
		
		do {
			$i += 1;
			$db_model = $this->setOrderAtModel($this->rule['db_name'], false);
			$ch_model = $this->setOrderAtModel($this->structureKey);  // 旧数据库的model

			$data = $ch_model->getList([
				'field' => implode(',', $field_arr),
				'where' => $where,
				'limit' => $rule_nums,
				'order' => 'id asc',
			]);

			foreach ($data as $k => $v) {
				if (self::CONTACT_OBJECT_VALUE[$v['contact_object']]) {
					$v['contact_object'] = self::CONTACT_OBJECT_VALUE[trim($v['contact_object'])];
				} else {
					$v['contact_object_other'] = trim($v['contact_object']);
					$v['contact_object'] = self::CONTACT_OBJECT_DETAULT;
				}
				$v['contact_method'] = self::CONTACT_METHOD_VALUE[trim($v['contact_method'])];
				$v['contact_type'] 	= self::CONTACT_TYPE_VALUE[trim($v['contact_type'])];
				$v['contact_result'] = self::CONTACT_RESULT_VALUE[trim($v['contact_result'])];
				$v['contact_report'] = self::CONTACT_REPORT_VALUE[trim($v['contact_report'])];

				$data[$k] = $v;
			}

			$data && $db_model->insertAll($data);

			$end = end($data);
			$end['id'] && $where['_string'] = ' id > '.$end['id'].' ';
			unset($data);
		} while ($end);

		return $return;
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
		   admin_id             int(11) not null,
		   worker_id            int(11) not null comment 'worker_id,技工id',
		   worker_order_id      int(11) comment 'order_id,维修工单id',
		   contact_object       int not null comment 'V1.0：object_type  ,V3.0：0其他，1维修商，2零售商，3零售商带维修商，4商家，5批发商，6批发商带维修商',
		   contact_object_other varchar(32) comment '联系对象的其他信息',
		   contact_method       tinyint not null comment '联系方式：1电话，2微信，3QQ，4短信',
		   contact_type         tinyint not null comment '联系类型 ：1派单咨询，2例行联系，3维修报价，4技术咨询，5代找网点，6其他',
		   contact_result       tinyint not null comment '联系结果， 1可以，2不可以，3其他',
		   contact_report       tinyint not null comment 'V1.0：contact_gu,客服评估  1可以合作，2考虑合作，3不再考虑，4其他',
		   contact_remark       text not null comment 'contact_desc,联系备注',
		   create_time          int not null default 0 comment '联系时间',
		   primary key (id)
		) ENGINE=INNODB AUTO_INCREMENT={$nums} DEFAULT CHARSET=utf8 comment='技工提现记录';
MYSQL;
	
		$this->resultRule[] = $this->sqlRunEnd($sql);
		$this->rule['is_db_name'] = $this->tableShowStatus($this->resultRule['db_name']);
	}

}
