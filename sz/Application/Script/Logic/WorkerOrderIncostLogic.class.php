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

class WorkerOrderIncostLogic extends DbLogic
{
	
	protected $rule;
	protected $resultRule;
	protected $structureKey = 'worker_order_incost';
	protected $ignoreIdArr  = [];
	protected $ignoreIdArrRecord  = [];
	const RULE_NUMS = 1000;

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

		$where = [];

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
				'field' => 'id,add_id as admin_id,0 as auditor_id,worker_order_id,change_type as type,amount as apply_fee,reason as apply_remark,addtime as create_time,is_check as status,IF(is_check!=0,last_uptime,0) as check_time,remarks as check_remark,amount_modify as apply_fee_modify,modify_reason',
				'where' => $where,
				'limit' => $rule_nums,
				'order' => 'id asc',
			]);
			
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
		   admin_id             int(11) not null comment '管理员id（平台客服id）',
		   auditor_id           int(11) comment '管理员id（平台客服id）',
		   worker_order_id      int(11) not null comment 'order_id，维修工单id',
		   type                 int not null comment 'change_type，补贴类型 1 调整上门费， 2 调整维修费，  3 工单奖励',
		   apply_fee            decimal(10,2) not null default 0.00 comment 'amount，申请费用额度',
		   apply_remark         text not null comment 'reason，申请原因',
		   create_time          int not null comment 'addtime，申请时间',
		   status               tinyint not null default 0 comment '(is_check 是否审核通过，0为否，1为是),v3.0，0待审核，1审核通过，2不通过，3系统取消',
		   check_time           int not null default 0 comment '审核时间',
		   check_remark         text not null comment 'remarks，审核备注',
		   apply_fee_modify     decimal(10,2) not null default 0.00 comment 'amount_modify，补贴费用修改（后）',
		   modify_reason        text not null comment '补贴费用修改原因',
		   primary key (id)
		) ENGINE=INNODB AUTO_INCREMENT={$nums} DEFAULT CHARSET=utf8 comment='补贴申请单';
MYSQL;
	
		$this->resultRule[] = $this->sqlRunEnd($sql);
		$this->rule['is_db_name'] = $this->tableShowStatus($this->resultRule['db_name']);
	}

}
