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

class WorkerOrderAppointLogic extends DbLogic
{
	
	protected $rule;
	protected $resultRule;
	protected $structureKey = 'worker_order_appoint';
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

		$where = [];

		if ($arr) {
    		$delete = $this->sqlDataTransferWhere($where, $arr);
    		$this->setOrderAtModel($this->rule['db_name'], false)->remove($delete);
    	}

		// $where['_string'] = ' id > 440455 ';
		$rule_nums = self::RULE_NUMS;

		$return = [];
		$i = 0;
		do {
			$i += 1;
			// var_dump(memory_get_usage(), $i, '========================================================================');
			$db_model = $this->setOrderAtModel($this->rule['db_name'], false);
			$ch_model = $this->setOrderAtModel($this->structureKey);  // 旧数据库的model
			$data = $ch_model->getList([
				'field' => '*',
				'where' => $where,
				'limit' => $rule_nums,
				'order' => 'id asc',
			]);

			$order_ids = arrFieldForStr($data, 'worker_order_id');

			$list = $order_ids ? $this->setOrderAtModel('worker_order')->getList([
				'field' => 'order_id,worker_id',
				'where' => [
					'order_id' => ['in', $order_ids],
				],
				'index' => 'order_id',
			]) : [];

			$adds = [];
			foreach ($data as $k => $v) {
				$add = [
					'id' 							=> $v['id'],
					'worker_id' 					=> $list[$v['worker_order_id']]['worker_id'],
					'worker_order_id' 				=> $v['worker_order_id'],
					'appoint_status' 				=> 1,
                    'appoint_time'                  => $v['appoint_time'],
					'update_reason'					=> $v['update_reason'] ? $v['update_reason'] : 0,
					'factory_appoint_fee'			=> $v['hf_fact'],
					'factory_appoint_fee_modify'	=> $v['hf_fact_modify'],
					'factory_appoint_reason'		=> $v['hf_fact_reason'],
					'factory_appoint_remark'		=> $v['hf_fact_remark'],
					'worker_appoint_fee'			=> $v['hf_work'],
					'worker_appoint_fee_modify'		=> $v['hf_work_modify'],
					'worker_appoint_reason'			=> $v['hf_work_reason'],
					'worker_appoint_remark'			=> $v['hf_work_remark'],
					'appoint_remark'				=> $v['remarks'],
					'create_time'					=> $v['addtime'],
					'last_update_time'				=> $v['last_update_time'],
					'is_sign_in'					=> $v['is_sign_in'] == 3 ? 1 :  $v['is_sign_in'],
					'sign_in_time'					=> $v['sign_in_time'],
					'is_over'						=> $v['is_over'],
					'over_time'						=> $v['over_time'],
				];

				if ($v['is_over'] == 1 && $v['remarks'] == '系统操作添加预约记录') {
					$add['appoint_status'] = 6;
				} elseif ($v['link_result'] == '再次预约,等待上门签到') {
					$add['appoint_status'] = 5;
				} elseif ($v['link_result'] == '修改预约时间') {
					$add['appoint_status'] = 4;
				} elseif ($v['link_result'] == '签到失败') {
					$add['appoint_status'] = 3;
				} elseif ($v['link_result'] == '修改预约时间') {
					$add['appoint_status'] = 2;
				} elseif ($v['link_result'] == '等待上门签到') {
					$add['appoint_status'] = 1;
				}

				$adds[] = $add;
			}
			$adds && $db_model->insertAll($adds);
			$end = end($data);
			$end['id'] && $where['_string'] = ' id > '.$end['id'].' ';

			unset($order_ids, $data, $list, $add, $adds, $k, $v);
		} while ($end);
		return [];
    }
	
    public function structureResult()
    {
    	$this->createNewStructures(true);
    	$this->createNewStructures();
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
		   worker_id            int(11) not null comment 'worker_id，技工id',
		   worker_order_id      int(11) not null comment 'order_id，维修工单id',
		   appoint_status       tinyint not null comment 'link_result,预约状态:可能值：1等待上门签到；2修改预约时间；3签到失败；4已签到；5再次预约,等待上门签到；6签到成功（客服修改上门次数操作添加的预约记录）；',
		   appoint_time         int not null default 0 comment '预约上门时间',
		   update_reason        tinyint not null comment '重新预约原因,1, 用户不在家 2, 我临时有事 3,用户没收到产品 4, 收到的产品有问题 5,收到的配件有问题 6, 其他',
		   factory_appoint_fee  decimal(10,2) not null comment 'hf_fact_total,厂家上门费用',
		   factory_appoint_fee_modify decimal(10,2) not null comment 'hf_fact_total_modify,上门费，修改后',
		   factory_appoint_reason varchar(255) not null comment 'hf_fact_reason,上门费用修改原因',
		   factory_appoint_remark text not null comment 'hf_fact_remark,厂家上门费计费说明',
		   worker_appoint_fee   decimal(10,2) not null default 0.00 comment 'hf_work_total,技工上门费',
		   worker_appoint_fee_modify decimal(10,2) not null default 0.00 comment 'hf_work_total_modify,技工上门费(修改后)',
		   worker_appoint_reason varchar(255) not null comment 'hf_work_reason,技工上门费修改原因',
		   worker_appoint_remark varchar(255) not null comment 'hf_work_remark,技工上门费说明',
		   appoint_remark       text not null comment 'remarks,预约备注  （App/技工 提交预约结果的备注）',
		   create_time          int not null comment 'addtime',
		   last_update_time     int not null comment '最后修改时间',
		   is_sign_in           tinyint not null default 0 comment '签到状态,0否，1是，2签到失败',
		   sign_in_time         int not null comment '签到记录时间',
		   is_over              tinyint not null default 0 comment '是否上传服务报告，0未，1是 ;最新工单数据结合接口触发改变状态,上门次数 依据字段',
		   over_time            int not null comment '上传服务报告时间',
		   primary key (id)
		) ENGINE=INNODB AUTO_INCREMENT={$nums} DEFAULT CHARSET=utf8 comment='工单技工预约记录';
MYSQL;

		$this->resultRule[] = $this->sqlRunEnd($sql);
		$this->rule['is_db_name'] = $this->tableShowStatus($this->resultRule['db_name']);
    }

}
