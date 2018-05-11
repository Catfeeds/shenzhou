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

class WorkerOrderComplaintLogic extends DbLogic
{
	
	protected $rule;
	protected $sync_time = '';
	protected $resultRule;
	protected $structureKey = 'worker_order_complaint';
	protected $ignoreIdArr  = [];
	protected $ignoreIdArrRecord  = [];
	const RULE_NUMS 		= 1000;
	const COMPLAINT_TYPE 	= 'complaint_type';
	const ADMIN_TYPE 		= 1;
	const FACTORY_TYPE 		= 2;
	const WORKER_TYPE 		= 4;
	const WX_USER_TYPE 		= 5;
	const TYPE_VALUE_COMPLAINT = 1;
	const TYPE_VALUE_COMFIRM   = 2;
	const SPECIEL_TYPE_ARR = [
		'S1' => 13, 
		'S2' => 14, 
		'S3' => 15, 
		'S4' => 16
	];
	const TO_TYPE_ARR =  [
		'S' => 1,
		'F' => 2,
		'W' => 4,
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
			// 'copltno' => '171019718325',
		];

		if ($arr) {
			$delete = $this->sqlDataTransferWhere($where, $arr);
			$this->setOrderAtModel($this->rule['db_name'], false)->remove($delete);
		} else {
			$this->createComplaintType();
		}
 
		$rule_nums = self::RULE_NUMS;
		$return = [];
		$field_arr = [
			'id',
			'complaint_type as complaint_type_id',
			'"" as cp_complaint_type_name',
			'comfirm_type as complaint_modify_type_id',
			'"" as cp_complaint_type_name_modify',
			'worker_order_id',
			'copltno as complaint_number',
			'complaint_from as complaint_from_id',
			'complaint_from_type',
			'complaint_to as complaint_to_id',
			'complaint_to_type',
			'content',
			'addtime as create_time',
			'link_name as contact_name',
			'link_tell as contact_tell',
			'reply_result',
			'reply_time',
			'is_check as is_true',
			'is_saticsfy as is_satisfy',
			'remark as verify_remark',
			'deduction  as worker_deductions',
		];
		$types = BaseModel::getInstance(self::COMPLAINT_TYPE, false)->getList([
				'field' => '*',
				'index' => 'id',
			]);
		$nums_arr = [];
		foreach ($types as $k => $v) {
			$nums_arr[$v['type']] = $k;
		}
		
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

			// TODO S1,S2,S3,S4
			foreach ($data as $k => $v) {
				$nums = (int)$nums_arr[self::TYPE_VALUE_COMPLAINT-1];
				$v['complaint_type_id'] = $v['complaint_type_id'] +  $nums;
				$v['cp_complaint_type_name'] = $types[$v['complaint_type_id']]['name'] ?? '';

				$nums = (int)$nums_arr[self::TYPE_VALUE_COMPLAINT-1];
				if (isset(self::SPECIEL_TYPE_ARR[$v['complaint_modify_type_id']])) {
					$v['complaint_modify_type_id'] = self::SPECIEL_TYPE_ARR[$v['complaint_modify_type_id']];
				}
				$v['complaint_modify_type_id'] = $v['complaint_modify_type_id'] +  $nums;
				$v['cp_complaint_type_name_modify'] = $types[$v['complaint_modify_type_id']]['name'] ?? '';

				$v['complaint_from_type'] = self::TO_TYPE_ARR[$v['complaint_from_type']] ?? 0;
				$v['complaint_to_type'] = self::TO_TYPE_ARR[$v['complaint_to_type']] ?? 0;
				$data[$k] = $v;
			}

			$data && $db_model->insertAll($data);

			$end = end($data);
			$end['id'] && $where['_string'] = ' id > '.$end['id'].' ';
			unset($data);
		} while ($end);

		return $return;
	}

	public function createComplaintType($arr = [])
	{
		$data = [
			[
				'id' 	=> 1,
				'name' 	=> '服务态度不好',
				'user_type' => self::WORKER_TYPE,
				'type' => self::TYPE_VALUE_COMPLAINT,
				'sort' => 1,
				'score_deductions' => 0,
			],
			[
				'id' 	=> 2,
				'name' 	=> '未按时上门',
				'user_type' => self::WORKER_TYPE,
				'type' => self::TYPE_VALUE_COMPLAINT,
				'sort' => 2,
				'score_deductions' => 0,
			],
			[
				'id' 	=> 3,
				'name' 	=> '未清理施工场地',
				'user_type' => self::WORKER_TYPE,
				'type' => self::TYPE_VALUE_COMPLAINT,
				'sort' => 3,
				'score_deductions' => 0,
			],
			[
				'id' 	=> 4,
				'name' 	=> '乱收用户费用',
				'user_type' => self::WORKER_TYPE,
				'type' => self::TYPE_VALUE_COMPLAINT,
				'sort' => 4,
				'score_deductions' => 0,
			],
			[
				'id' 	=> 5,
				'name' 	=> '使用劣质配件',
				'user_type' => self::WORKER_TYPE,
				'type' => self::TYPE_VALUE_COMPLAINT,
				'sort' => 5,
				'score_deductions' => 0,
			],
			[
				'id' 	=> 6,
				'name' 	=> '抵毁厂家产品',
				'user_type' => self::WORKER_TYPE,
				'type' => self::TYPE_VALUE_COMPLAINT,
				'sort' => 6,
				'score_deductions' => 0,
			],
			[
				'id' 	=> 7,
				'name' 	=> '和用户发生冲突',
				'user_type' => self::WORKER_TYPE,
				'type' => self::TYPE_VALUE_COMPLAINT,
				'sort' => 7,
				'score_deductions' => 0,
			],
			[
				'id' 	=> 8,
				'name' 	=> '其他',
				'user_type' => self::WORKER_TYPE,
				'type' => self::TYPE_VALUE_COMPLAINT,
				'sort' => 8,
				'score_deductions' => 0,
			],
			[
				'id' => 9,
				'name' => '服务态度不好',
				'user_type' => self::ADMIN_TYPE,
				'type'	=> self::TYPE_VALUE_COMPLAINT,
				'sort'	=> 9,
				'score_deductions' => 0,
			],
			[
				'id' => 10,
				'name' => '业务流程不专业',
				'user_type' => self::ADMIN_TYPE,
				'type'	=> self::TYPE_VALUE_COMPLAINT,
				'sort'	=> 10,
				'score_deductions' => 0,
			],
			[
				'id' => 11,
				'name' => '不维护厂家形象',
				'user_type' => self::ADMIN_TYPE,
				'type'	=> self::TYPE_VALUE_COMPLAINT,
				'sort'	=> 11,
				'score_deductions' => 0,
			],
			[
				'id' => 12,
				'name' => '反馈进度不及时',
				'user_type' => self::ADMIN_TYPE,
				'type'	=> self::TYPE_VALUE_COMPLAINT,
				'sort'	=> 12,
				'score_deductions' => 0,
			],
			// ========================================================
			[
				'id' 	=> 12 + 1,
				'name'  => '单方取消工单',
				'user_type' => self::WORKER_TYPE,
				'type'	=> self::TYPE_VALUE_COMFIRM,
				'sort'	=> 1,
				'score_deductions' => 30,
			],
			[
				'id' 	=> 12 + 2,
				'name'  => '二次催单',
				'user_type' => self::WORKER_TYPE,
				'type'	=> self::TYPE_VALUE_COMFIRM,
				'sort'	=> 2,
				'score_deductions' => 10,
			],
			[
				'id' 	=> 12 + 3,
				'name'  => '未按时上门',
				'user_type' => self::WORKER_TYPE,
				'type'	=> self::TYPE_VALUE_COMFIRM,
				'sort'	=> 3,
				'score_deductions' => 10,
			],
			[
				'id' 	=> 12 + 4,
				'name'  => '安装维修不规范',
				'user_type' => self::WORKER_TYPE,
				'type'	=> self::TYPE_VALUE_COMFIRM,
				'sort'	=> 4,
				'score_deductions' => 10,
			],
			[
				'id' 	=> 12 + 5,
				'name'  => '使用伪劣旧配件',
				'user_type' => self::WORKER_TYPE,
				'type'	=> self::TYPE_VALUE_COMFIRM,
				'sort'	=> 5,
				'score_deductions' => 20,
			],
			[
				'id' 	=> 12 + 6,
				'name'  => '乱收费用',
				'user_type' => self::WORKER_TYPE,
				'type'	=> self::TYPE_VALUE_COMFIRM,
				'sort'	=> 6,
				'score_deductions' => 30,
			],
			[
				'id' 	=> 12 + 7,
				'name'  => '服务语言不规范',
				'user_type' => self::WORKER_TYPE,
				'type'	=> self::TYPE_VALUE_COMFIRM,
				'sort'	=> 7,
				'score_deductions' => 10,
			],
			[
				'id' 	=> 12 + 8,
				'name'  => '服务态度恶劣',
				'user_type' => self::WORKER_TYPE,
				'type'	=> self::TYPE_VALUE_COMFIRM,
				'sort'	=> 8,
				'score_deductions' => 20,
			],
			[
				'id' 	=> 12 + 9,
				'name'  => '抵毁企业形象',
				'user_type' => self::WORKER_TYPE,
				'type'	=> self::TYPE_VALUE_COMFIRM,
				'sort'	=> 9,
				'score_deductions' => 50,
			],
			[
				'id' 	=> 12 + 10,
				'name'  => '与用户发生严重冲突',
				'user_type' => self::WORKER_TYPE,
				'type'	=> self::TYPE_VALUE_COMFIRM,
				'sort'	=> 10,
				'score_deductions' => 100,
			],
			[
				'id' 	=> 12 + 11,
				'name'  => '服务不佳导致退换机',
				'user_type' => self::WORKER_TYPE,
				'type'	=> self::TYPE_VALUE_COMFIRM,
				'sort'	=> 11,
				'score_deductions' => 50,
			],
			[
				'id' 	=> 12 + 12,
				'name'  => '乱报媒体网络',
				'user_type' => self::WORKER_TYPE,
				'type'	=> self::TYPE_VALUE_COMFIRM,
				'sort'	=> 12,
				'score_deductions' => 200,
			],
			[
				'id' => 12 + 13,
				'name' => '服务态度不好',
				'user_type' => self::ADMIN_TYPE,
				'type' => self::TYPE_VALUE_COMFIRM,
				'sort' => 13,
				'score_deductions' => 0,
			],
			[
				'id' => 12 + 14,
				'name' => '业务流程不专业',
				'user_type' => self::ADMIN_TYPE,
				'type' => self::TYPE_VALUE_COMFIRM,
				'sort' => 14,
				'score_deductions' => 0,
			],
			[
				'id' => 12 + 15,
				'name' => '不维护厂家形象',
				'user_type' => self::ADMIN_TYPE,
				'type' => self::TYPE_VALUE_COMFIRM,
				'sort' => 15,
				'score_deductions' => 0,
			],
			[
				'id' => 12 + 16,
				'name' => '反馈进度不及时',
				'user_type' => self::ADMIN_TYPE,
				'type' => self::TYPE_VALUE_COMFIRM,
				'sort' => 16,
				'score_deductions' => 0,
			],
		];

		$model = $this->setOrderAtModel(self::COMPLAINT_TYPE, false);
		$model->insertAll($data);
	}

	public function structureResult()
	{
		// 删除
		$this->createNewStructures(true);
		$this->complaintType(true);
		// 新增
		$this->createNewStructures();
		$this->complaintType();
		
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
		   complaint_modify_type_id int not null,
		   verifier_id          int(11) not null comment '管理员id（平台客服id）',
		   replier_id           int(11) not null comment '管理员id（平台客服id）',
		   complaint_type_id    int not null,
		   worker_order_id      int(11) not null comment 'order_id，维修工单id',
		   complaint_number     varchar(30) not null comment 'copltno，投诉单号',
		   cp_complaint_type_name varchar(16) not null comment '投诉类型',
		   complaint_from_id    int not null comment 'complaint_from，投诉人ID',
		   complaint_from_type  tinyint not null comment '投诉人类别，( F：厂家 2，W技工 4，S：客服1):1 客服；2 厂家；3 厂家子账号；4 技工；5用户；',
		   complaint_to_id      int not null comment 'complaint_to，被投诉人的ID',
		   complaint_to_type    tinyint not null comment '投诉对象类型（ F：厂家 2，W技工 4，S：客服1）  1 客服；2 厂家；3 厂家子账号；4 技工；5用户；',
		   content              text not null comment '投诉具体内容',
		   create_time          int not null comment '投诉提交时间',
		   contact_name         varchar(50) not null comment 'link_name，联系人名称 (处理结果,通知该人的联系信息)',
		   contact_tell         varchar(50) not null comment 'link_tell，联系人电话',
		   reply_result         text not null comment '处理(回复)',
		   reply_time           int not null comment '处理（回复）时间',
		   is_true              tinyint not null default 0 comment 'is_check，投诉是否属实，0否，1是 (核实处理结果,,客诉专员)',
		   cp_complaint_type_name_modify varchar(20) not null comment 'comfirm_type，处理结果,核实后的投诉类型 ( complaint_to_type )',
		   response_type_id    int not null default 0 comment '核实后责任人id',
		   response_type        tinyint not null comment '责任方：(1维修商，2客服，3下单人，4用户)改：1 客服；2 厂家；3 厂家子账号；4 技工；5用户；',
   		   is_satisfy           tinyint not null comment '(is_saticsfy)投诉人是否满意，1是，2否',
		   verify_remark        text not null comment 'remark，客诉主管备注',
		   verify_time          int not null default 0 comment '核实了的投诉单的时间',
		   worker_deductions    tinyint not null default 0 comment 'deduction，技工扣分',
		   primary key (id)
		) ENGINE=INNODB AUTO_INCREMENT={$nums} DEFAULT CHARSET=utf8 comment='工单投诉单';
MYSQL;

		$this->resultRule[] = $this->sqlRunEnd($sql);
		$this->rule['is_db_name'] = $this->tableShowStatus($this->resultRule['db_name']);
	}

	public function complaintType($is_delete = false)
	{
		$table = self::COMPLAINT_TYPE;
		if ($is_delete) {
			$result = $this->deleteTable(self::COMPLAINT_TYPE);
			$this->resultRule[] = $result['sql'];
			return $result;
		} elseif ($this->tableShowStatus(self::COMPLAINT_TYPE)) {
			return false;
		}

		$sql = <<<MYSQL
		create table {$table}
		(
		   id                   int not null auto_increment,
		   name                 varchar(16) not null comment '名称',
		   user_type            tinyint comment '1 客服、2 厂家、4 技工商、 5 普通用户',
		   type                 tinyint not null comment '字典类别 ： 1 发起投诉；2确认投诉；',
		   sort                 smallint not null default 0 comment '排序',
		   score_deductions     smallint not null default 0 comment '分数扣除',
		   primary key (id)
		) ENGINE=INNODB DEFAULT CHARSET=utf8 comment='投诉单类型字典';
MYSQL;

		$this->resultRule[] = $this->sqlRunEnd($sql);
		$this->rule['is_db_name'] = $this->tableShowStatus($this->resultRule['db_name']);
	}

}
