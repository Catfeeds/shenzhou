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

class FactoryMoneySetRecordLogic extends DbLogic
{
	
	protected $rule;
	protected $resultRule;
	protected $structureKey = 'factory_money_set_record';
	const RULE_NUMS = 1000;
	const FACTORY_MONEY_CHANGE_RECORD  = 'factory_money_change_record';
	
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
    	foreach ($arr as $key => $value) {
    		switch ($key) {
    			case 'id':
    				$ids = implode(',', array_unique(array_filter($value)));
    				$ids && $where['id'] = $return['id'] = ['in', $ids];
    				break;
    		}
    	}
    	return $return;
    }

    public function sqlDataTransfer($arr)
    {
    	set_time_limit(0);
		$where = [
			'type' => '',
			'add_money' => 0,
		];

		if ($arr) {
			$delete = $this->sqlDataTransferWhere($where, $arr);
			$this->setOrderAtModel($this->rule['db_name'], false)->remove($delete);
		}

		$num = $this->setOrderAtModel($this->structureKey)->getNum($where);
		$rule_nums = self::RULE_NUMS;
		$foreach_num = ceil($num/$rule_nums);

		$return = [];
		$last = [];
		for ($i=1; $i <= $foreach_num; $i++) {
			$db_model = $this->setOrderAtModel($this->rule['db_name'], false);
			$ch_model = $this->setOrderAtModel($this->structureKey);  // 旧数据库的model

			$data = $ch_model->getList([
				'field' => '*',
				'where' => $where,
				'limit' => getPage($i, $rule_nums),
				'order' => 'add_time asc',
			]);
			$adds = [];
			foreach ($data as $k => $v) {
				$pre = $last[$v['factory_id']];
				// $service_charge = number_format($last['service_charge'][$v['factory_id']], 2, '.', '');
				// $v['service_charge'] = number_format($v['service_charge'], 2, '.', '');

				$add = [
					'id' 							=> $v['id'],
					'admin_id' 						=> $v['admin_id'],
					'create_time'					=> $v['add_time'],
					'service_fee'					=> number_format($pre['service_charge'], 2, '.', ''),
					'service_fee_modify'			=> $v['service_charge'],
					'date_from'						=> $pre['date_from'] ?? 0,
					'date_from_modify'				=> $v['date_from'],
					'date_to'						=> $pre['date_to'] ?? 0,
					'date_to_modify'				=> $v['date_to'],
					'base_distance'					=> number_format($pre['base_distance'], 2, '.', ''),
					'base_distance_modify'			=> $v['base_distance'],
					'base_distance_fee'				=> number_format($pre['base_distance_cost'], 2, '.', ''),
					'base_distance_fee_modify'		=> $v['base_distance_cost'],
					'overrun_distance_fee'			=> number_format($pre['exceed_cost'], 2, '.', ''),
					'overrun_distance_fee_modify'	=> $v['exceed_cost'],
					'overrun_distance_fee'			=> number_format($pre['exceed_cost'], 2, '.', ''),
					'overrun_distance_fee_modify'	=> $v['exceed_cost'],
					'worker_order_frozen'			=> '0.00',
					'worker_order_frozen_modify'	=> '0.00',
					'remark'						=> $v['desc'],
				];
				$adds[] = $add;

				$last[$v['factory_id']] = $v;
			}

			$adds && $db_model->insertAll($adds);

			unset($data);
		};

		$this->transferFactoryMoneyChangeRecord($arr);

		return $return;
    }

    public function transferFactoryMoneyChangeRecord($arr = [])
    {
    	$table = self::FACTORY_MONEY_CHANGE_RECORD;
    	set_time_limit(0);
    	$end_id = 0;
		$where = [
			'add_money' => ['neq', 0],
		];

		if ($arr) {
			$delete = $this->sqlDataTransferWhere($where, $arr);
			$this->setOrderAtModel($table, false)->remove($delete);
		}

		$rule_nums = self::RULE_NUMS;
		$return = [];
		$last = [];
		do {
			// $where['_string'] = " id > {$end_id} and add_money <> 0 and ( (status = 0 and type != '') OR type = '')";
			$i += 1;
			$db_model = $this->setOrderAtModel($table, false);
			$ch_model = $this->setOrderAtModel($this->structureKey);  // 旧数据库的model

			$data = $ch_model->getList([
				'field' => '*',
				'where' => $where,
				'limit' => $rule_nums,
				'order' => 'id asc',
			]);

			$change_type = [
				'upacp_pc' => 1,
				'alipay_pc_direct' => 2,
				'wx_pub_qr' => 3,
			];
			$adds = [];
			foreach ($data as $k => $v) {

				$add = [
					'id'					=> $v['id'],
					'factory_id'			=> $v['factory_id'],
					'operator_id'			=> $v['admin_id'],
					'operator_type'			=> $v['type'] ? 2 : 1,
					'operation_remark'		=> $v['desc'],
					'change_type'			=> $change_type[$v['type']] ?? 5,
					'money'					=> $v['money'],
					'change_money'			=> $v['add_money'],
					'last_money'			=> $v['current_money'],
					'out_trade_number'		=> $v['out_trade_no'] ?? '',
					'status'				=> $v['status'] == 1 ? 2 : 1,
					'create_time'			=> $v['add_time'],
				];
				$adds[] = $add;

			}
			
			$adds && $db_model->insertAll($adds);

			$end = end($data);
			$end['id'] && $where['_string'] = " id > {$end['id']} ";
			// $end['id'] && $end_id = $end['id'];
			unset($data);
		} while ($end);

		!$arr && $this->transferFactoryMoneyPayRecord($arr);

		return $return;
    }

    public function transferFactoryMoneyPayRecord($arr = [])
    {
    	//  厂家最终钱包与变动记录对比的检查语句
    	// select a.last_money,b.money from factory_money_change_record a left join factory b on a.factory_id = b.factory_id where a.last_money != b.money and a.id in (select substring_index(max(concat(c.create_time,'.',c.id)), '.', '-1') as id from factory_money_change_record c group by c.factory_id);

    	$table = self::FACTORY_MONEY_CHANGE_RECORD;
    	$old_table = 'factory_money_pay_record';
    	set_time_limit(0);
		$where = [];

		if ($arr) {
			$check = $this->sqlDataTransferWhere($where, $arr);

			$delete = [
				'change_type' => 4,
			];
			$delete['out_trade_number'] = arrFieldForStr($this->setOrderAtModel($old_table)->getList($check), 'orno');
			$delete['out_trade_number'] && $this->setOrderAtModel($table, false)->remove($delete);
		}

		$rule_nums = self::RULE_NUMS;
		$return = [];
		$last = [];
		do {
			$i += 1;
			$db_model = $this->setOrderAtModel($table, false);
			$ch_model = $this->setOrderAtModel($old_table);  // 旧数据库的model

			$data = $ch_model->getList([
				'field' => '*',
				'where' => $where,
				'limit' => $rule_nums,
				'order' => 'id asc',
			]);

			$change_type = [
				'upacp_pc' => 1,
				'alipay_pc_direct' => 2,
				'wx_pub_qr' => 3,
			];
			$adds = [];
			foreach ($data as $k => $v) {

				$add = [
					'factory_id'			=> $v['factory_id'],
					'operator_id'			=> 0,
					'operator_type'			=> 0,
					'operation_remark'		=> '',
					'change_type'			=> 4,
					// 'money'					=> $v['last_money'] - (- $v['pay_money']),
					'money'					=> $v['last_money'] + $v['pay_money'],
					'change_money'			=> - $v['pay_money'],
					'last_money'			=> $v['last_money'],
					'out_trade_number'		=> $v['orno'] ?? '',
					'status'				=> 1,
					'create_time'			=> $v['add_time'],
				];
				$adds[] = $add;

			}
			
			$adds && $db_model->insertAll($adds);

			$end = end($data);
			$end['id'] && $where['_string'] = ' id > '.$end['id'].' ';
			unset($data);
		} while ($end);

		return $return;
    }

    public function structureResult()
    {
    	$this->createNewStructures(true);
		$this->factoryMoneyChangeRecord(true);

    	$this->createNewStructures();
		$this->factoryMoneyChangeRecord();
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
		$nums = $data['id'] ? $data['id']+ 1: 0;

		$sql = <<<MYSQL
		create table {$this->rule['db_name']}
		(
		   id                   int(11) not null auto_increment,
		   factory_id           int(11) not null,
		   admin_id             int(11) not null,
		   create_time          int not null comment 'add_time，添加时间',
		   service_fee          decimal(10,2) not null default 0.00 comment 'service_charge，服务费',
		   service_fee_modify   decimal(10,2) not null default 0.00 comment 'service_charge_modify',
		   date_from            int not null default 0 comment '有限期开始',
		   date_from_modify     int not null,
		   date_to              int not null default 0 comment '有限期结束',
		   date_to_modify       int not null,
		   base_distance        decimal(10,2) not null default 0.00 comment '基本里程',
		   base_distance_modify decimal(10,2) not null default 0.00,
		   base_distance_fee    decimal(10,2) not null default 0.00 comment 'base_distance_cost，基本里程费',
		   base_distance_fee_modify decimal(10,2) not null default 0.00 comment 'base_distance_cost_modify，修改后的基本里程费',
		   overrun_distance_fee decimal(10,2) not null default 0.00 comment 'exceed_cost，超程单价',
		   overrun_distance_fee_modify decimal(10,2) not null default 0.00 comment 'exceed_cost_modify，修改后的超程单价',
		   worker_order_frozen  decimal(10,2) not null default 0.00,
		   worker_order_frozen_modify decimal(10,2) not null default 0.00,
		   remark               text not null comment 'desc，资费调整备注',
		   primary key (id)
		) ENGINE=INNODB AUTO_INCREMENT={$nums} DEFAULT CHARSET=utf8 comment='factory_money_set_record，厂家不可自己修改， 工厂费用配置修改记录';
MYSQL;
		$this->resultRule[] = $this->sqlRunEnd($sql);
		$this->rule['is_db_name'] = $this->tableShowStatus($this->resultRule['db_name']);
	}

	public function factoryMoneyChangeRecord($is_delete = false)
	{
		$table = self::FACTORY_MONEY_CHANGE_RECORD;
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
			id                   int(11) not null auto_increment,
			factory_id           int(11) not null,
			operator_id          int not null default 0 comment '操作人id',
			operator_type        tinyint default 0 comment '操作人帐号类型；0 系统主动调整；0 系统主动调整；1 平台客服;2 厂家客服;3 厂家子账号',
			operation_remark     text comment '操作备注',
			change_type          tinyint not null comment '变动类型：1 （厂家）银联在线支付；2 （厂家）支付宝支付；3 （厂家）微信支付； 4 工单结算资金变动；5 （客服）手动调整；',
			money                decimal(10,2) not null default 0.00 comment '变动前的资金',
			change_money         decimal(10,2) not null default 0.00 comment '变动的资金',
			last_money           decimal(10,2) not null default 0.00 comment '变动后的资金',
			out_trade_number     varchar(50) default null comment '（外部订单）交易号',
			status               tinyint not null default 0 comment '是否入账 0 创建；1 入账（操作、充值成功）；2 失败；',
			create_time          int not null comment '变动时间',
			primary key (id)
		) ENGINE=INNODB DEFAULT CHARSET=utf8 comment='厂家资金变动记录';
MYSQL;
		$this->resultRule[] = $this->sqlRunEnd($sql);
	}


}
