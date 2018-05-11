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

class WorkerAddApplyLogic extends DbLogic
{
	
	protected $rule;
	protected $resultRule;
	protected $structureKey = 'worker_add_apply';
	const RULE_NUMS 		= 1000;

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
		$field_arr = [
			'id',
			'0 as worker_id',
			'order_id as worker_order_id',
			'admin_id as auditor_id',
			'apply_member_id as apply_admin_id',
			'orno',
			'"" as apply_worker_number',
			'area_ids',
			'`desc` as remark',
			'add_time as create_time',
			'status',
			'result_desc as result_remark',
			'worker_info',
			'comment_status as is_valid',
			'comment as result_evaluate',
			'reply_time as audit_time',
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
		   worker_order_id      int(11) comment 'order_id,维修工单id',
		   worker_id            int(11) not null comment 'worker_id，技工id',
		   auditor_id           int(11),
		   apply_admin_id       int(11) not null,
		   orno                 varchar(150) not null comment '工单号',
		   apply_worker_number  varchar(150) not null comment '开点单号',
		   area_ids             varchar(500) not null comment '开点地区IDs, 多个逗号分隔'',''',
		   remark               text not null comment 'desc,申请点备注',
		   create_time          int not null default 0 comment 'add_time,添加时间',
		   status               smallint not null default 0 comment '0待处理 ，1已开点，2不能开点，3已取消 4正在处理, 5 跟进中(v3.0添加)',
		   result_remark        varchar(500) not null comment 'result_desc,开点结果备注',
		   worker_info          text not null comment '技工信息',
		   is_valid             smallint not null default 0 comment 'comment_status,1:有效 2：无效 (开点结果是否有效)',
		   result_evaluate      varchar(500) not null comment 'comment,开点评价 (客服评价 开点客服 结果的评估)',
		   audit_time           int not null default 0 comment 'reply_time 开点处理时间',
		   primary key (id)
		) ENGINE=INNODB AUTO_INCREMENT={$nums} DEFAULT CHARSET=utf8 comment='维修商开点单';
MYSQL;
	
		$this->resultRule[] = $this->sqlRunEnd($sql);
		$this->rule['is_db_name'] = $this->tableShowStatus($this->resultRule['db_name']);
	}

}
