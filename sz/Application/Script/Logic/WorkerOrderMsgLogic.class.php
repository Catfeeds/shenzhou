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

class WorkerOrderMsgLogic extends DbLogic
{
	
	protected $rule;
	protected $resultRule;
	protected $structureKey = 'worker_order_msg';
	const FACTORY_ORDER_MSG = 'factory_order_msg';
	const RULE_NUMS = 1000;
	const FACTORY_VAR_DATA = [
		'A1'		=> 1,
		'A2'		=> 2,
		'B1'		=> 3,
		'B2'		=> 4,
		'B3'		=> 5,
		'B8'		=> 6,
		'B9'		=> 7,
		'B10'		=> 8,
	];
	const ADMIN_VAR_DATA = [
		'FY'		=> 9,
		'B9'		=> 10,
		'B8'		=> 11,
		'B13'		=> 12,
		'B12'		=> 13,
		'B10'		=> 14,
	];
	const VAR_DATA_TO = [
		'15' 		=> '提交了产品错误报告',
		'16'		=> '修改预约,预约时间为',
		'17'		=> '完成维修】',
		'18'		=> '工单退回】',
		'19'		=> '客户已签收',
		'20'		=> '申请费用',
		'21'		=> '提交的费用单不通过',
		'22'		=> '配件单厂家不通过',
		'23'		=> '厂家已经发件',
		'24'		=> '申请了一个新配件',
		'25'		=> '配件已签收',
		'26'		=> ['开点申请处理结果为', '开点申请已经开点成功'],
	];

	function __construct($rule) {
       	$this->rule = $rule ? $rule : $this->isDbStructure($this->structureKey);
    }

    public function syncTimeCheckAndUpdateWhere(&$merge = [])
    {
    	$data = $this->setOrderAtModel($this->structureKey)->getList([
    			'field' => '1 as type,id',
    			'where' => [
    				C('SYNC_TIME') => ['gt', $this->syncTimeWhere(NOW_TIME)],
    			],
    		]);
    	$data = array_merge($data, $this->setOrderAtModel(self::FACTORY_ORDER_MSG)->getList([
    			'field' => '2 as type,id',
    			'where' => [
    				C('SYNC_TIME') => ['gt', $this->syncTimeWhere(NOW_TIME)],
    			],
    		]));
    	foreach ($data as $key => $value) {
    		$k = $value['type'] == 1 ? 'worker_msg_id' : 'factry_msg_id';
    		$merge[$k][] = $value['id'];
    	}
    	// var_dump($merge);die;
    }

    public function sqlDataTransferWhere(&$where, $arr = [])
    {
    	$return = [];
    	foreach ($arr as $key => $value) {
    		switch ($key) {
    			case 'worker_msg_id':
    				$ids = implode(',', array_unique(array_filter($value)));
    				$ids && $where['id'] = $return[C('TRANSFER_EXTEND_ID')] = ['in', $ids];
    				$return['user_type'] = 1;
    				break;

    			case 'factry_msg_id':
    				$ids = implode(',', array_unique(array_filter($value)));
    				$ids && $where['id'] = $return[C('TRANSFER_EXTEND_ID')] = ['in', $ids];
    				$return['user_type'] = 2;
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
			$check['worker_msg_id'] = $arr['worker_msg_id'];
			$delete = $this->sqlDataTransferWhere($where, $check);
			if (!$where) {
				$where['id'] = false;
				$delete['id'] = false;
			}
			$this->setOrderAtModel($this->rule['db_name'], false)->remove($delete);
		}

		$rule_nums = self::RULE_NUMS;
		$return = [];
		do {
			$i += 1;
			$db_model = $this->setOrderAtModel($this->rule['db_name'], false);
			$ch_model = $this->setOrderAtModel($this->structureKey);  // 旧数据库的model

			$data = $ch_model->getList([
				'field' => 'msg as msg_content,substring_index(href, "/", -1) as data_id,admin_id as user_id,1 as user_type,type as msg_type,add_time as create_time,id as transfer_extend_id',
				'where' => $where,
				'limit' => $rule_nums,
				'order' => 'id asc',
			]);
			
			$adds = [];
			foreach ($data as $k => $v) {
				$v['msg_type'] = $this->adminOldTypeToNew($v['msg_type'], $v['msg_content']);
				if (!$v['msg_type']) {
					continue;
				}
				$adds[] = $v;
			}
			$adds && $db_model->insertAll($adds);
			$end = end($data);
			$end['transfer_extend_id'] && $where['_string'] = ' id > '.$end['transfer_extend_id'].' ';
			unset($data);
		} while ($end);

		$this->transferFactoryOrderMsg($arr);

		return $return;
    }

    public function transferFactoryOrderMsg($arr = [])
    {
    	$old_table = self::FACTORY_ORDER_MSG;
    	
    	set_time_limit(0);
		$where = [];

		if ($arr) {
			$check['factry_msg_id'] = $arr['factry_msg_id'];
			$delete = $this->sqlDataTransferWhere($where, $check);
			if (!$where) {
				$where['id'] = false;
				$delete['id'] = false;
			}
			$this->setOrderAtModel($this->rule['db_name'], false)->remove($delete);
		}

		$rule_nums = self::RULE_NUMS;
		$return = [];
		do {
			$i += 1;
			$db_model = $this->setOrderAtModel($this->rule['db_name'], false);
			$ch_model = $this->setOrderAtModel($old_table);  // 旧数据库的model

			$data = $ch_model->getList([
				'field' => 'msg as msg_content,substring_index(href, "/", -1) as data_id,admin_id as user_id,2 as user_type,type as msg_type,add_time as create_time,id as transfer_extend_id',
				'where' => $where,
				'limit' => $rule_nums,
			]);

			$adds = [];
			foreach ($data as $k => $v) {
				$v['msg_type'] = $this->factoryOldTypeToNew($v['msg_type'], $v['msg_content']);
				if (!$v['msg_type']) {
					continue;
				}
				$adds[] = $v;
			}
			$adds && $db_model->insertAll($adds);

			$end = end($data);
			$end['transfer_extend_id'] && $where['_string'] = ' id > '.$end['transfer_extend_id'].' ';
			unset($data);
		} while ($end);

		return $return;
    }

    public function factoryOldTypeToNew($old_type = '', $content = '')
    {
    	$type = self::FACTORY_VAR_DATA[$old_type];
		if (!$type) {
			var_dump($content);die;
		}
    	return $type;
    }

    public function adminOldTypeToNew($old_type = '', $content = '')
    {
    	$type = self::ADMIN_VAR_DATA[$old_type];
		$i = 0;
		$while_to = self::VAR_DATA_TO;
		$nums = count($while_to);
    	
		for ($i=0;!$type && $i < $nums; $i++) { 
			$value = $i == 0 ? current($while_to) : next($while_to);
			if (is_array($value)) {
				foreach ($value as $v) {
					strpos($content, $v) && $type = key($while_to);
				}
			} else {
				strpos($content, $value) && $type = key($while_to);
			}
		}
		
		if (!$type) {
			var_dump($content);die;
		}
    	return $type;
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
		$b_model = $this->setOrderAtModel($this->structureKey);
		$nums = $b_model->getNum();
		foreach ($this->rule['other'] as $k => $v) {
			$v = is_string($k) ?? $v;
			$che = [];
			if (!$this->tableShowStatus($v)) {
				continue;
			}
			$nums += $this->setOrderAtModel($v)->getNum();
		}
		$nums = $nums ? $nums+1 : 0;
		$sql = <<<MYSQL
		create table {$this->rule['db_name']}
		(
		   id                   int not null auto_increment,
		   msg_content          varchar(255) not null comment '消息,msg  => msg_content',
		   data_id              int comment '（msg_type和data_id决定href）可能有： 工单id，配件单id，费用单id',
		   user_id              int not null default 0 comment 'admin_id =》 user_id  结合 user_type使用，为0时表示该角色都能查看',
		   user_type            tinyint not null comment 'v3.0添加的字段, 1客服；2 厂家；3 厂家子帐号；4 技工（占坑）；5 微信用户 （占坑）',
		   is_read              tinyint not null default 0 comment '是否已阅读 0 否  1是',
		   read_time            int not null default 0 comment '阅读时间',
		   category_type        tinyint not null default 0 comment '消息种类',
		   msg_type             smallint not null comment '消息类型。type =》msg_type,可能值 参考：Shenzhou-Document/数据库设计/v2.0xB端后台消息系统整理.txt Shenzhou-Document/需求原型/V3.0需求-09.20 /短信后台消息通知模板/后台系统消息模板-7.20.doc',
		   create_time          int not null comment '添加时间（add_time）',
		   transfer_extend_id   int not null default 0,
		   primary key (id)
		) ENGINE=INNODB AUTO_INCREMENT={$nums} DEFAULT CHARSET=utf8 comment='厂家-客服工单相关的通知消息';
MYSQL;
		$this->resultRule[] = $this->sqlRunEnd($sql);
		$this->rule['is_db_name'] = $this->tableShowStatus($this->resultRule['db_name']);
	}


	
}
