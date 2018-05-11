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

class WorkerOrderFeeLogic extends DbLogic
{
	
	protected $rule;
	protected $resultRule;
	protected $structureKey = 'worker_order';
	const RULE_NUMS = 1000;
	public $test = [];

	function __construct($rule) {
       	$this->rule = $rule ? $rule : $this->isDbStructure($this->structureKey);
    }

    public function syncTimeCheckAndUpdateWhere(&$merge = [])
    {
    	// 不用处理
    }

    public function sqlDataTransferWhere(&$where, $arr = [])
    {
    	$return = [];
    	foreach ($arr as $key => $value) {
    		switch ($key) {
    			case parent::WORKER_ORDER_P_STRING:
    				$ids = implode(',', array_unique(array_filter($value)));
    				$ids && $where['id'] = $return['worker_order_id'] = ['in', $ids];
    				break;
    		}
    	}
    	return $return;
    }

    public function sqlDataTransfer($arr = [])
    {
		set_time_limit(0);
		if (!$this->tableShowStatus($this->rule['db_name'])) {
			return [];
		}

		$order_id = [];
		
		$where = [
			// 'id' => '9868',
		];

		if ($arr) {
			$delete = $this->sqlDataTransferWhere($where, $arr);
			$this->setOrderAtModel($this->rule['db_name'], false)->remove($delete);
		}

		$rule_nums = self::RULE_NUMS;
		$return = [];
		do {
			$i += 1;
			$db_model = $this->setOrderAtModel($this->rule['db_name'], false);
			$wo_model = $this->setOrderAtModel($this->structureKey, false);

			$this->test = $list = $wo_model->getList([
				'field' => 'id,worker_order_status',
				'where' => $where,
				'limit' => $rule_nums,
				'order' => 'id asc',
				'index' => 'id',
			]);
			
			$order_ids = arrFieldForStr($list, 'id');
			
			$data = $this->getWorkerOrderFee($order_ids);

			$data && $db_model->insertAll($data);

			$end = end($list);
			$end['id'] && $where['_string'] = ' id > '.$end['id'].' ';
			unset($data);
		} while ($end);

		return $return;
    }

    protected function getWorkerOrderFee($order_ids = '')
    {
    	if (!$order_ids) {
    		return [];
    	}
    	$wmir_model  = $this->setOrderAtModel('worker_money_in_record');
		$wo_model  = $this->setOrderAtModel('worker_order');  // 旧数据库的model
		$wod_model = $this->setOrderAtModel('worker_order_detail');  // 旧数据库的model
		$woa_model = $this->setOrderAtModel('worker_order_appoint');  // 旧数据库的model
    	$fao_model = $this->setOrderAtModel('factory_acce_order');
    	$fco_model = $this->setOrderAtModel('factory_cost_order');
    	$woi_model = $this->setOrderAtModel('worker_order_incost');

    	$wos = $wo_model->getList([
    		'alias' => 'wo',
    		// 'join'  => 'left join worker_money_in_record wmir on wo.order_id = wmir.order_id',
    		// 'field' => 'order_id as worker_order_id,homefee_model as homefee_mode,cs_appoint_num as cs_appoint_nums,is_insurance_cost as insurance_fee,IF(is_quality,quality_money,0) as quality_fee,area_price_work as worker_area_diff_fee,area_price_work_modify as worker_area_diff_fee_modify,area_price_fact as factory_area_diff_fee,area_price_fact_modify as factory_area_diff_fee_modify',
    		'field' => 'order_id as worker_order_id,homefee_model as homefee_mode,cs_appoint_num as cs_appoint_nums,is_insurance_cost as insurance_fee,IF(is_quality,quality_money,0) as quality_fee,work_netreceipts',
    		'where' => [
    			'wo.order_id' => ['in', $order_ids],
    		],
    	]);
    	$wmirs = $wmir_model->getList([
    		'alias' => 'wo',
    		'field' => 'order_id,order_money as is_order_money,netreceipts_money as is_netreceipts_money',
    		'where' => [
    			'wo.order_id' => ['in', $order_ids],
    		],
    		'index' => 'order_id',
    	]);


    	$wod_field = [
    		'worker_order_id',
    		'SUM(work_cost) as worker_repair_fee',
    		'SUM(work_cost_modify) as worker_repair_fee_modify',
    		'SUM(fact_cost) as factory_repair_fee',
    		'SUM(fact_cost_modify) as factory_repair_fee_modify',
			'SUM(service_fee) as service_fee',
			'SUM(service_fee_modify) as service_fee_modify'
    	];
    	$wods = $wod_model->getList([
    		'field' => implode(',', $wod_field),
    		'group' => 'worker_order_id',
    		'index' => 'worker_order_id',
    		'where' => [
    			'worker_order_id' => ['in', $order_ids],
    		],
    	]);

    	$woa_field = [
    		'worker_order_id',
    		'SUM(hf_fact) as factory_appoint_fee',
    		'SUM(hf_fact_modify) as factory_appoint_fee_modify',
    		'SUM(hf_work) as worker_appoint_fee',
    		'SUM(hf_work_modify) as worker_appoint_fee_modify',
    	];
    	$woas = $woa_model->getList([
    		'field' => implode(',', $woa_field),
    		'group' => 'worker_order_id',
    		'index'	=> 'worker_order_id',
    		'where' => [
    			'is_over' => 1,
    			'worker_order_id' => ['in', $order_ids],
    		],
    	]);

    	$fcos = $fco_model->getList([
    		'field' => 'worker_order_id,SUM(money) as cost_fee',
    		'group' => 'worker_order_id',
    		'index'	=> 'worker_order_id',
    		'where' => [
    			'is_check'	=> 1,
    			'is_cs_check'	=> 1,
    			'worker_order_id' => ['in', $order_ids],
    		],
    	]);

    	$faos = $fao_model->getList([
    		'field' => 'worker_order_id,SUM(worker_sb_cost) as accessory_return_fee',
    		'group' => 'worker_order_id',
    		'index'	=> 'worker_order_id',
    		'where' => [
    			'worker_sb_paytype' => 1,
    			'worker_order_id' => ['in', $order_ids],
    		],
    	]);

    	$wois = $woi_model->getList([
    		'field' => 'worker_order_id,SUM(amount) as worker_allowance_fee,SUM(amount_modify) as worker_allowance_fee_modify',
    		'group' => 'worker_order_id',
    		'index'	=> 'worker_order_id',
    		'where' => [
    			'is_check' => 1,
    			'worker_order_id' => ['in', $order_ids],
    		],
    	]);

    	$datas = [];
    	foreach ($wos as $k => $v) {
    		$wod = $wods[$v['worker_order_id']];
			$woa = $woas[$v['worker_order_id']];
			$fco = $fcos[$v['worker_order_id']];
			$fao = $faos[$v['worker_order_id']];
			$woi = $wois[$v['worker_order_id']];

			$data = [
				'worker_order_id' 				=> $v['worker_order_id'],
				'homefee_mode' 					=> $v['homefee_mode'],
				'cs_appoint_nums'				=> $v['cs_appoint_nums'],
				'insurance_fee'					=> number_format($v['insurance_fee'], 2, '.', ''),
				'quality_fee'					=> number_format($v['quality_fee'], 2, '.', ''),
				// 'worker_area_diff_fee'			=> number_format($v['worker_area_diff_fee'], 2, '.', ''),
				// 'worker_area_diff_fee_modify'	=> number_format($v['worker_area_diff_fee_modify'], 2, '.', ''),
				// 'factory_area_diff_fee'			=> number_format($v['worker_area_diff_fee'], 2, '.', ''),
				// 'factory_area_diff_fee_modify'	=> number_format($v['factory_area_diff_fee_modify'], 2, '.', ''),

				'worker_repair_fee'				=> number_format($wod['worker_repair_fee'], 2, '.', ''),
				'worker_repair_fee_modify'		=> number_format($wod['worker_repair_fee_modify'], 2, '.', ''),
				'factory_repair_fee'			=> number_format($wod['factory_repair_fee'], 2, '.', ''),
				'factory_repair_fee_modify'		=> number_format($wod['factory_repair_fee_modify'], 2, '.', ''),
				'service_fee'					=> number_format($wod['service_fee'], 2, '.', ''),
				'service_fee_modify'			=> number_format($wod['service_fee_modify'], 2, '.', ''),

				'factory_appoint_fee'			=> number_format($woa['factory_appoint_fee'], 2, '.', ''),
				'factory_appoint_fee_modify'	=> number_format($woa['factory_appoint_fee_modify'], 2, '.', ''),
				'worker_appoint_fee'			=> number_format($woa['worker_appoint_fee'], 2, '.', ''),
				'worker_appoint_fee_modify'		=> number_format($woa['worker_appoint_fee_modify'], 2, '.', ''),

				'worker_cost_fee'				=> number_format($fco['cost_fee'], 2, '.', ''),
				'factory_cost_fee'				=> number_format($fco['cost_fee'], 2, '.', ''),

				'accessory_return_fee'			=> number_format($fao['accessory_return_fee'], 2, '.', ''),

				'worker_allowance_fee'			=> number_format($woi['worker_allowance_fee'], 2, '.', ''),
				'worker_allowance_fee_modify'	=> number_format($woi['worker_allowance_fee_modify'], 2, '.', ''),
			];
			
			//总金额 = 维修金 + 上门费 + 配件单邮费（返件） + 费用单 + 服务费  // + 地区价差
			$data['factory_total_fee']		 	= 	$data['factory_appoint_fee'] 
												  +	$data['factory_repair_fee'] 
												  +	$data['accessory_return_fee'] 
												  +	$data['factory_cost_fee'] 
												  +	$data['service_fee'];
			$data['factory_total_fee_modify']	= 	$data['factory_appoint_fee_modify'] 
												  +	$data['factory_repair_fee_modify'] 
												  +	$data['accessory_return_fee'] 
												  +	$data['factory_cost_fee'] 
												  +	$data['service_fee_modify'];

			//总金额 = 维修金 + 上门费 + 配件单邮费（返件） + 费用单 + 补贴单（内部费用）- 订单保险费
			$data['worker_total_fee']			= 	$data['worker_appoint_fee'] 
												  +	$data['worker_repair_fee'] 
												  +	$data['accessory_return_fee'] 
												  +	$data['worker_cost_fee'] 
												  + $data['worker_allowance_fee'] 
												  - $data['insurance_fee'];
			$data['worker_total_fee_modify']	= 	$data['worker_appoint_fee_modify'] 
												  +	$data['worker_repair_fee_modify'] 
												  +	$data['accessory_return_fee'] 
												  +	$data['worker_cost_fee'] 
												  + $data['worker_allowance_fee_modify'] 
												  - $data['insurance_fee'];
			
			$data['worker_net_receipts']		= $data['worker_total_fee_modify'] - $data['quality_fee'];


			$wmir = $wmirs[$v['worker_order_id']];
			// $wmir['is_netreceipts_money'] != $data['worker_net_receipts'] && $data['worker_net_receipts'] = $wmir['is_netreceipts_money'];
			// if ($v['work_netreceipts'] != $data['worker_net_receipts'] && $this->test[$v['worker_order_id']]['worker_order_status'] > 15) {
			// 	echo $v['worker_order_id']."原：{$v['work_netreceipts']}, 计：{$data['worker_net_receipts']}, {$this->test[$v['worker_order_id']]['worker_order_status']}".'<br />';
			// }


			$datas[] = $data;
    	}

    	return $datas;
    }

    public function structureResult()
    {
		$this->createNewStructures(true);
		$this->createNewStructures();
    }

    protected function createNewStructures($is_delete = false)
	{
		$table = 'worker_order_fee';
		if ($is_delete) {
			$result = $this->deleteTable($table);
			$this->resultRule[] = $result['sql'];
			return $result;
		} elseif ($this->tableShowStatus($table)) {
			return false;
		}

		$sql = <<<MYSQL
		create table {$table}
		(
		   worker_order_id      int(11) not null comment 'order_id,维修工单id',
		   homefee_mode         smallint not null default 0 comment 'homefee_model上门费计费模式，0未设置，1第一次免基本里程费，2：第2次10元基本里程费,(0 未派单状态)',
		   factory_appoint_fee  decimal(10,2) not null comment 'hf_fact_total,厂家上门费用',
		   factory_appoint_fee_modify decimal(10,2) not null comment 'hf_fact_total_modify,上门费，修改后',
		   worker_appoint_fee   decimal(10,2) not null default 0.00 comment 'hf_work_total,技工上门费',
		   worker_appoint_fee_modify decimal(10,2) not null default 0.00 comment 'hf_work_total_modify,技工上门费(修改后)',
		   cs_appoint_nums      smallint not null default 0 comment 'cs_appoint_num,客服修改的技工上门次数，为0时表示没有修改.(新增的上门次数为按时上门)',
		   worker_repair_fee    decimal(10,2) not null default 0.00 comment '技工维修总金（修改前）',
		   worker_repair_fee_modify decimal(10,2) not null default 0.00 comment '技工维修总金（修改后）',
		   factory_repair_fee   decimal(10,2) not null default 0.00 comment '厂家维修总金（修改前）',
		   factory_repair_fee_modify decimal(10,2) not null default 0.00 comment '厂家维修总金（修改后）',
		   service_fee          decimal(10,2) not null default 0.00 comment '平台服务总费（修改前）',
		   service_fee_modify   decimal(10,2) not null default 0.00 comment '平台服务总费（修改后）',
		   worker_cost_fee      decimal(10,2) not null default 0.00 comment '技工费用单总费用',
		   factory_cost_fee     decimal(10,2) not null default 0.00 comment '厂家费用单总费用',
		   accessory_return_fee decimal(10,2) not null default 0.00 comment '配件返件总费',
		   worker_allowance_fee decimal(10,2) not null default 0.00 comment '补贴单总费',
		   worker_allowance_fee_modify decimal(10,2) not null default 0.00 comment '修改后的补贴单费用',
		   factory_total_fee    decimal(10,2) not null default 0.00 comment '厂家工单总价（旧值）',
		   factory_total_fee_modify decimal(10,2) not null default 0.00 comment '工单总金额（实收，厂家）',
		   worker_total_fee     decimal(10,2) not null default 0.00 comment '技工工单总价（旧值）',
		   worker_total_fee_modify decimal(10,2) not null default 0.00 comment '工单总价（实收，技工） 修改后',
		   insurance_fee        decimal(10,2) not null default 0.00 comment 'is_insurance_cost,需要缴纳的保险费， 单位元（RMB）',
		   quality_fee          decimal(10,2) not null default 0.00 comment 'quality_money,工单缴纳的质保金额',
		   worker_net_receipts  decimal(10,2) not null default 0.00 comment 'work_netreceipts,技工实收（工单总额-质保金）',
		   accessory_out_fee    decimal(10,2) not null default 0.00 comment '保外单配件总费',
		   user_discount_out_fee decimal(10,2) not null default 0.00 comment '保外单 用户（支付）优惠的金额',
		   primary key (worker_order_id)
		) ENGINE=INNODB DEFAULT CHARSET=utf8 comment='工单费用详情表';
MYSQL;

		$this->resultRule[] = $this->sqlRunEnd($sql);
		$this->rule['is_db_name'] = $this->tableShowStatus($this->resultRule['db_name']);
	}
}
