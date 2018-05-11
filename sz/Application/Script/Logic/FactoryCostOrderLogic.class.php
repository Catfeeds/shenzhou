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

class FactoryCostOrderLogic extends DbLogic
{
	
	protected $rule;
	protected $resultRule;
	protected $structureKey = 'factory_cost_order';
	protected $ignoreIdArr  = [];
	protected $ignoreIdArrRecord  = [];
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
		$not_in_order_ids && $where['id'] = ['not in', $not_in_order_ids];

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
				'field' => 'id,0 as admin_id,worker_id,worker_order_id,factory_id,worker_order_detail_id as worker_order_product_id,cost_order_number as apply_cost_number,cost_type as type,worker_remarks as reason,money as fee,cost_img as imgs,addtime as create_time,remarks as factory_check_remark,cs_remarks as admin_check_remark,0 as status,is_check,is_cs_check',
				'where' => $where,
				'limit' => $rule_nums,
				'order' => 'id asc',
			]);
			
			$cost_ids = arrFieldForStr($data, 'id');
			$times = $cost_ids ? $this->setOrderAtModel('factory_cost_order_record')->getList([
					// 'field' => 'cost_order_id,max(add_time) as add_time',
					'field' => 'cost_order_id,max(add_time) as add_time,max(IF(ope_type="SA",concat(add_time,".",ope_user_id),0)) as admin_id',
					'where' => [
						'cost_order_id' => ['in', $cost_ids],
					],
					'group' => 'cost_order_id',
					'index' => 'cost_order_id',
				]) : [];

			foreach ($data as $k => $v) {
				if ($v['is_check'] == 1) {
					$v['status'] = 4; 
				} elseif ($v['is_check'] == 2) {
					$v['status'] = 3; 
				} elseif ($v['is_cs_check'] == 1) {
					$v['status'] = 2; 
				} elseif ($v['is_cs_check'] == 2) {
					$v['status'] = 1; 
				} else {
					$v['status'] = 0;
				}
				unset($v['is_check'], $v['is_cs_check']);
				$v['last_update_time'] = $times[$v['id']]['add_time'];
				$v['admin_id'] = explode('.', $times[$v['id']]['admin_id'])[1] ?? 0;
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
		$model = $this->setOrderAtModel($this->structureKey);
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
		   admin_id             int(11),
		   worker_id            int(11) not null comment 'worker_id,技工id',
		   worker_order_id      int(11) not null comment 'order_id,维修工单id',
		   factory_id           int(11) not null,
		   worker_order_product_id int(11) not null comment 'order_detail_id,工单详情id',
		   apply_cost_number    varchar(20) not null comment '费用单号',
		   type                 tinyint not null default 5 comment 'cost_type,申请费用类型 1 远程上门； 2 购买配件费用 ； 3 旧机拆机合和打包费用  4 旧机返厂运费  5 其他',
		   reason               varchar(255) not null comment 'worker_remarks,申请原因',
		   fee                  decimal(10,2) not null default 0.00 comment 'money,申请额度（元）',
		   imgs                 text comment 'cost_img,申请费用附图 (json 化数据)',
		   create_time          int not null comment 'addtime,申请（添加）时间',
		   factory_check_remark varchar(255) comment 'remarks,备注 (技工)',
		   admin_check_remark   varchar(255) not null comment 'cs_remarks,客服审核意见（回复）',
		   status               tinyint not null comment '0创建费用单（待客服审核）;1客服审核不通过;2客服审核（待厂家审核）;3厂家审核不通过;4厂家审核（完结）',
		   last_update_time     int not null default 0 comment '最后操作时间',
		   primary key (id)
		) ENGINE=INNODB AUTO_INCREMENT={$nums} DEFAULT CHARSET=utf8 comment='费用申请单';
MYSQL;
	
		$this->resultRule[] = $this->sqlRunEnd($sql);
		$this->rule['is_db_name'] = $this->tableShowStatus($this->resultRule['db_name']);
	}

}
