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

class WorkerOrderRevisitLogic extends DbLogic
{
	
	protected $rule;
	protected $resultRule;
	protected $structureKey = 'worker_order_revisit';
	const RULE_NUMS = 1000;

	function __construct($rule) {
       	$this->rule = $rule ? $rule : $this->isDbStructure($this->structureKey);
    }

    public function syncTimeCheckAndUpdateWhere(&$merge = [])
    {
    	$data = $this->setOrderAtModel($this->structureKey)->getList([
    			'field' => 'id,worker_order_id',
    			'where' => [
    				C('SYNC_TIME') => ['gt', $this->syncTimeWhere(NOW_TIME)],
    			],
    		]);
    	
    	foreach ($data as $key => $value) {
    		// $merge[parent::WORKER_ORDER_P_STRING][] = $value['worker_order_id']; // 不参与工单模块整体数据变更
    		$merge['id'][] = $value['id'];
    	}
    }

    public function sqlDataTransferWhere(&$where, $arr = [])
    {
    	$return = [];
    	$p_ids = [];
    	foreach ($arr as $key => $value) {
    		switch ($key) {
    			case parent::WORKER_ORDER_P_STRING:
    				$ids = implode(',', array_unique(array_filter($value)));
    				$where['worker_order_id'] = $return['worker_order_id'] = ['in', $ids];
    				break;

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

		$sql = "select id,user_name from admin where user_name in (select add_name from worker_order_revisit group by add_name)";
		// $list = $this->setOrderAtModel('admin')->query($sql);
		$list = $this->setOrderAtModel('admin')->getList([
				'field' => 'max(id) as id,user_name',
				'where' => [
					'_string' => 'user_name in (select add_name from worker_order_revisit group by add_name)',
				],
				'group' => 'user_name',
				'index' => 'user_name',
			]);

    	$where = [];

    	if ($arr) {
    		$delete = $this->sqlDataTransferWhere($where, $arr);
    		$this->setOrderAtModel($this->rule['db_name'], false)->remove($delete);
    	}

		// $num = $ch_model->getNum($where);
		$rule_nums = self::RULE_NUMS;
		// $foreach_num = ceil($num/$rule_nums);

		$return = [];
		do {
			$i += 1;
			$db_model = $this->setOrderAtModel($this->rule['db_name'], false);
			$ch_model = $this->setOrderAtModel($this->structureKey);  // 旧数据库的model

			$data = $ch_model->getList([
				'field' => 'id,worker_order_id,is_work_apptime as is_visit_ontime,behavior as irregularities,is_work_satisfy
 as is_user_satisfy,quality_fraction as repair_quality_score,work_hs_reason as not_visit_reason,work_remarks as return_remark,add_time as create_time,add_name as admin_id',
				'where' => $where,
				'limit' => $rule_nums,
				'order' => 'id asc',
			]);

			foreach ($data as $k => $v) {
				$v['admin_id'] = $list[$v['admin_id']]['id'] ? $list[$v['admin_id']]['id'] : 0;
				$data[$k] = $v;
			}

			$data && $db_model->insertAll($data);

			$end = end($data);
			$end['id'] && $where['_string'] = ' id > '.$end['id'].' ';
			unset($data);
		} while ($end);
		return [];
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
		$nums = $data['id'] ? $data['id'] : 0;

		$sql = <<<MYSQL
		create table if not exists {$this->rule['db_name']}
			(
			   id                   int(11) not null auto_increment,
			   worker_order_id      int(11) not null comment 'order_id,维修工单id',
			   admin_id             int(11) not null,
			   is_visit_ontime      tinyint not null comment 'is_work_apptime,是否按时上门（技工）',
			   irregularities       varchar(255) not null comment 'behavior,技工违规行为',
			   is_user_satisfy      tinyint not null default 0 comment 'is_work_satisfy,用户对技工是否满意',
			   repair_quality_score smallint not null default 10 comment 'quality_fraction,质量分',
			   not_visit_reason     text not null comment 'work_hs_reason,厂家或普通用户不需师傅上门维修原因 (客服操作)',
			   return_remark        text not null comment 'work_remarks,回访内容（描述）',
			   create_time          int not null default 0 comment 'add_time,添加时间',
			   primary key (id)
			) ENGINE=InnoDB AUTO_INCREMENT={$nums} DEFAULT CHARSET=utf8 COMMENT='工单回访记录 worker_order_revisit';
MYSQL;

		$this->resultRule[] = $this->sqlRunEnd($sql);
		$this->rule['is_db_name'] = $this->tableShowStatus($this->resultRule['db_name']);
	}

}
