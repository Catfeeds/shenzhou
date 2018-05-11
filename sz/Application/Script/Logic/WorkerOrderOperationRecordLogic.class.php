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

class WorkerOrderOperationRecordLogic extends DbLogic
{
	protected $rule;
	protected $resultRule;
	protected $structureKey = 'worker_order_operation_record';
	const RULE_NUMS = 1000;
	const SEE_AUTH = [
		'1' => 1, 		// 客服
		'2' => 6,		// 厂家 2 厂家子账号 4  2+4 = 6
		'3' => 8, 		// 技工
	];
	const CHANGE_SET_SEE_AUTH = [
	    'SJ' => 1,
    ];
	const VAR_DATA = [
 		'FB' 	=> '2000',
 		'FC' 	=> '2001',
 		'FD' 	=> '2002',
 		'FE' 	=> '2003',
 		'FG' 	=> '2007',
 		'FI' 	=> '2009',
 		'FL' 	=> '2010',
 		'FK' 	=> '2011',
 		'FJ' 	=> '2012',
 		'FY' 	=> '2013',
 		'SA' 	=> '1000',
 		'SB' 	=> '1001',
 		'SC' 	=> '1002',
 		'SCA' 	=> '1003',
 		'SD' 	=> '1004',
 		'SF' 	=> '1008',
 		'SH' 	=> '1009',
 		'SI' 	=> '1010',
 		'SJ' 	=> '1011',
 		'SO' 	=> '1014',
 		'SST' 	=> '1015',
 		'SWG' 	=> '1016',
 		'SZ' 	=> '1017',
 		'ZZ' 	=> '1018',
 		'OT' 	=> '1019',
 		'WA'	=> '4000',
 		'WB'	=> '4001',
 		'WD'	=> '4004',
 		'WF'	=> '4005',
 		'WG'	=> '4006',
 		'WH'	=> '4007',
 		'WI'	=> '4008',
 		'WJ'	=> '4009',
 		'WK'	=> '4010',
 		'WL'	=> '4011',
  		'AB' 	=> '7000',
 		'AP'  	=> '7001',
	];

	/**
	 * 7000 AB  工单超过厂家预设时间没有派出，系统自动收回 
	 * 7001 AP  厂家超过{N}天时间未结算！神州系统自动结算
	 * 
	 * 2000 FB  修改用户信息
	 * 2001 FC  修改工单产品
	 * 2002 FD  添加工单产品
	 * 2003 FE  删除工单产品
	 * 2004 FF  审核了工单费用(待审核)
	 * 2005 FF  审核了工单费用(不通过)
	 * 2006 FF  审核了工单费用(通过)
	 * 2007 FG  申请查看技工联系方式
	 * 2008 FH  创建工单，提交客服中心审核.产品信息为：
	 * 2009 FI  厂家重新下单
	 * 2010 FL  厂家确认下单，产品信息为：
	 * 2011 FK  自行处理
	 * 2012 FJ  延迟工单回收时间至：
	 * 2013 FY  取消工单
	 * 
	 * 3000 SA  核实工单信息
	 * 3001 SB  修改用户信息
	 * 3002 SC  修改工单产品
	 * 3003 SCA 更改上门次数
	 * 3004 SD  添加工单产品
	 * 3005 SE  删除工单产品 (同意厂家查看技工联系方式,不同意厂家查看技工联系方式)
	 * 3006 SE  同意厂家查看技工联系方式
	 * 3007 SE  不同意厂家查看技工联系方式
	 * 3008 SF  财务内审通过并提交至厂家审核
	 * 3009 SH  派发(包括抢单池),技工已接单
	 * 3010 SI  提交开点申请
	 * 3011 SJ  修改技工结算费用明细,修改后总金额为
	 * 3012 SL  确认工单已完成并提交财务审核
	 * 3013 SL  确认工单未完成并将工单状态重置为待维修
	 * 3014 SO  客服放弃工单
	 * 3015 SST 修改工单服务类型
	 * 3016 SWG 修改工单产品服务项
	 * 3017 SZ  终止工单
	 * 3018 ZZ  客服手动添加操作记录
	 * 3019 OT  客服转单到客服
	 * 
	 * 4000 WA  预约客户成功,（时间）
	 * 4001 WB  延长预约时间延长至:（时间）
	 * 4002 WC  修改预约,预约时间为:（时间）
	 * 4003 WC  退回工单，(不会维修该产品 ，无法满足客户需求 ，及其他原因)
	 * 4004 WD  签到失败、成功（但必然算上门的钱）
	 * 4005 WF  技工再次预约,预约时间为:
	 * 4006 WG  选择产品维修项:（维修项名称）
	 * 4007 WH  申请配件
	 * 4008 WI  申请费用,费用单号为
	 * 4009 WJ  提交产品报告（无法完成、现场完成等）
	 * 4010 WK  完成工单本次服务评价为:
	 * 4011 WL  提交产品错误
	 * 
	 * 5000 FH  创建工单，提交厂家审核.产品信息为：(普通用户)
	 * 5001 FH  创建工单，提交厂家审核.产品信息为：(经销商)
	 * 
	**/

	function __construct($rule) {
       	$this->rule = $rule ? $rule : $this->isDbStructure($this->structureKey);
    }

    public function syncTimeCheckAndUpdateWhere(&$merge = [])
    {
    	$data = $this->setOrderAtModel($this->structureKey)->getList([
    			'field' => 'id',
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
    	foreach ($arr as $key => $value) {
    		switch ($key) {
    			// case parent::WORKER_ORDER_P_STRING:
    			// 	$ids = implode(',', array_unique(array_filter($value)));
    			// 	$ids && $where['worker_order_id'] = $return['worker_order_id'] = ['in', $ids];
    			// 	break;

    			case 'id':
    				$ids = implode(',', array_unique(array_filter($value)));
    				$ids && $where['id'] = $return['id'] = ['in', $ids];
    				break;
    		}
    	}
    	return $return;
    }

    public function sqlDataTransfer($arr = [])
    {
    	set_time_limit(0);

  //   	$num = $this->setOrderAtModel($this->structureKey)->getNum();
		// $rule_nums = self::RULE_NUMS;
		// $foreach_num = ceil($num/$rule_nums);
		// var_dump($foreach_num);die;

		$where = [];

		if ($arr) {
			$delete = $this->sqlDataTransferWhere($where, $arr);
			$this->setOrderAtModel($this->rule['db_name'], false)->remove($delete);
		}

		// $last = $this->setOrderAtModel($this->structureKey)->getOne([
		// 		'field' => 'id',
		// 		'order' => 'id desc',
		// 	]);
		// $last['id'] && $where['id'] = ['elt', $last['id']];
		// $this->setOrderAtModel($this->rule['db_name'], false)->remove($delete);

		$rule_nums = self::RULE_NUMS;

		$return = [];
		$i = 0;
		do {
			$i += 1;
			$db_model = $this->setOrderAtModel($this->rule['db_name'], false);
			$ch_model = $this->setOrderAtModel($this->structureKey);  // 旧数据库的model
			$data = $ch_model->getList([
				'field' => '*',
				'where' => $where,
				'limit' => $rule_nums,
				'order' => 'id asc',
			]);
			$adds = [];
			foreach ($data as $k => $v) {
				$is_system_create = 0;
				$add = [
					'id' 							=> $v['id'],
					'worker_order_id' 				=> $v['order_id'],
					'worker_order_product_id' 		=> $v['order_detail_id'],
					'create_time' 					=> $v['add_time'],
					'operator_id' 					=> $v['ope_user_id'],
					'operation_type' 				=> 0,
					'content' 						=> $v['operation'],
					'remark' 						=> $v['desc'],
					'is_super_login' 				=> $v['super_login'],
					'see_auth' 						=> isset(self::CHANGE_SET_SEE_AUTH[$v['ope_role']]) ? self::CHANGE_SET_SEE_AUTH[$v['ope_role']] : $this->getSeeAuth($v['see_auth']),
					'is_system_create' 				=> $is_system_create,
				];
				$this->getOperationType($v, $add, $v['ope_role']);
				$adds[] = $add;
			}

			$adds && $db_model->insertAll($adds);

			$end = end($data);
			$end['id'] && $where['_string'] = ' id > '.$end['id'].' ';
			
			unset($data, $add, $adds, $k, $v);
		} while ($end);
		// } while ($i < 10);
		return [];
    }

    protected function getSeeAuth($value = '')
    {
    	$return = 0;
    	foreach (explode(',', $value) as $k => $v) {
    		$return += self::SEE_AUTH[$v];
    	}

    	return $return;
    }

    protected function getOperationType($v = [], &$add = [], $old_role = '')
    {
    	// AB,AP,FB,FC,FD,FE,FG,FH,FI,FL,FK,FJ,FY,SA,SB,SC,SCA,SD,SF,SH,SI,SJ,SO,SST,SWG,SZ,ZZ,OT,WA,WB,WD,WF,WG,WH,WI,WJ,WK,WL
    	$in_check = ['FF', 'SE', 'SL', 'WC', 'FH'];

    	if (in_array($v['ope_type'], $in_check)) {
    		switch ($v['ope_type']) {
    			// 2004 FF  审核了工单费用(待审核) 2005 FF  审核了工单费用(不通过) 2006 FF  审核了工单费用(通过)
    			case 'FF':
    				if (count(explode('不通过', $v['operation'])) > 1) {
    					$add['operation_type'] = 2005;
    				} elseif (count(explode('待审核', $v['operation'])) > 1) {
    					$add['operation_type'] = 2004;
    				} else {
    					$add['operation_type'] = 2006;
    				}
    				break;
    			// 3005 SE  删除工单产品 3006 SE  同意厂家查看技工联系方式 3007 SE  不同意厂家查看技工联系方式
    			case 'SE':
    				if ($v['operation'] == '不同意厂家查看技工联系方式') {
    					$add['operation_type'] = 1007;
    				} elseif ($v['operation'] == '同意厂家查看技工联系方式') {
    					$add['operation_type'] = 1006;
    				} else {
    					$add['operation_type'] = 1005;
    				}
    				break;
    			// 3012 SL  确认工单已完成并提交财务审核  3013 SL  确认工单未完成并将工单状态重置为待维修
    			case 'SL':
    				if (count(explode('并将工单状态重置为待维', $v['operation'])) > 1) {
    					$add['operation_type'] = 1013;
    				} else {
    					$add['operation_type'] = 1012;
    				}
    				break;
					// 4002 WC  修改预约,预约时间为:（时间） 4003 WC  退回工单，(不会维修该产品 ，无法满足客户需求 ，及其他原因)
    			case 'WC':
    				if (count(explode('修改预约,预约时间为', $v['operation'])) > 1) {
    					$add['operation_type'] = 4002;
    				} else {
    					$add['operation_type'] = 4003;
    				}
    				break;
					// 2008 FH  创建工单，提交客服中心审核.产品信息为： 
					// 5000 FH  创建工单，提交厂家审核.产品信息为：(普通用户) 
					// 5001 FH  创建工单，提交厂家审核.产品信息为：(经销商) (新需求)	
    			case 'FH':
    				if (count(explode('创建工单，提交客服中心审核', $v['operation'])) > 1) {
    					$add['operation_type'] = 2008;
    				} else {
    					$add['operation_type'] = 5000;
    				}
    				break;
    		}

    	} else {
    		$add['operation_type'] = self::VAR_DATA[$v['ope_type']];
    	}

    	if ($old_role == 'factory_admin' && substr($v['ope_type'], 0, 1) == 2) {
    		$v['ope_type'] += 1000;
    	}
    	$ope_type = $v['ope_type'];
    	$value = $v['ope_type'];
    	return $value;
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
		$data = $b_model->getOne(['order' => 'id DESC', 'field' => 'id']);
		$nums = $data['id'] ? $data['id']+1 : 0;

		$sql = <<<MYSQL
		create table {$this->rule['db_name']}
		(
		   id                   int(11) not null auto_increment,
		   worker_order_product_id int(11) comment 'order_detail_id，工单详情id',
		   worker_order_id      int(11) not null comment 'order_id，维修工单id',
		   create_time          int not null comment 'add_time，数据添加时间（操作时间）',
		   operator_id          int not null comment 'ope_user_id，操作人id',
		   operation_type       smallint not null comment 'ope_type，操作类型（区间=角色）；1001~2000 平台客服；2001~3000 厂家客服；3001~4000 厂家子帐号；4001~5000 技工；5001~6000 微信用户（微信普通用户，经销商）',
		   content              varchar(255) not null comment 'operation，操作内容',
		   remark               text not null comment 'desc备注',
		   is_super_login       tinyint not null default 0 comment 'super_login，超级登录操作 0默认操作 1超级登录操作 (PS: 技工密码与通用密码重复)',
		   see_auth             int not null comment '查看权限：0全部、1 客服、2 厂家、4厂家子帐号、8 技工、16 微信普通用户、32 经销商。例 31 = 1 + 2 + 4 + 8 +16；12 = 4 + 8',
		   is_system_create     tinyint not null default 1 comment '是否是系统生成的数据，0 否，1 是。',
		   primary key (id)
		) ENGINE=INNODB AUTO_INCREMENT={$nums} DEFAULT CHARSET=utf8 comment='工单操作记录';
MYSQL;
		$this->resultRule[] = $this->sqlRunEnd($sql);
		$this->rule['is_db_name'] = $this->tableShowStatus($this->resultRule['db_name']);

	}
	
}
