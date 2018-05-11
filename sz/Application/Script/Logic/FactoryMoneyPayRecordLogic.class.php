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

class FactoryMoneyPayRecordLogic extends DbLogic
{
	
	protected $rule;
	protected $resultRule;
	protected $structureKey = 'factory_money_pay_record';
	const RULE_NUMS = 1000;

	function __construct($rule) {
       	$this->rule = $rule ? $rule : $this->isDbStructure($this->structureKey);
    }

    public function syncTimeCheckAndUpdateWhere(&$merge = [])
    {
    	$data = $this->setOrderAtModel($this->structureKey)->getList([
    			'field' => 'order_id',
    			'where' => [
    				C('SYNC_TIME') => ['gt', $this->syncTimeWhere(0, $this->structureKey)],
    			],
    		]);
    	
    	foreach ($data as $key => $value) {
    		$merge['order_id'][] = $value['order_id'];
    	}
    }

    public function sqlDataTransferWhere(&$where, $arr = [])
    {
    	$return = [];
    	foreach ($arr as $key => $value) {
    		switch ($key) {
    			case 'order_id':
    				$ids = implode(',', array_unique(array_filter($value)));
    					$ids 
    				&& 	$where['order_id'] = $return['worker_order_id'] = ['in', $ids];
    				break;
    		}
    	}
    	return $return;
    }

    public function sqlDataTransfer($arr)
    {
		set_time_limit(0);
		$where = [];
		
		if ($arr) {
			$delete = $this->sqlDataTransferWhere($where, $arr);
			$delete['id'] = false;
			$where['id'] = false;
			$this->setOrderAtModel($this->rule['db_name'], false)->remove($delete);
		}

		$rule_nums = self::RULE_NUMS;
		$return = [];
		do {
			$i += 1;
			$db_model = $this->setOrderAtModel($this->rule['db_name'], false);
			$ch_model = $this->setOrderAtModel($this->structureKey);  // 旧数据库的model

			$data = $ch_model->getList([
				'field' => 'id,factory_id,order_id as worker_order_id,orno,pay_money,last_money,add_time as create_time',
				'where' => $where,
				'limit' => $rule_nums,
				'order' => 'id asc',
			]);
			$data && $db_model->insertAll($data);

			$end = end($data);
			$end['id'] && $where['_string'] = ' id > '.$end['id'].' ';
			unset($data);
		} while ($end);

		$arr && $this->transferFactoryMoneyPayRecord($arr);

		return $return;
    }

    public function transferFactoryMoneyPayRecord($arr = [])
    {
    	$table = 'factory_money_change_record';
    	set_time_limit(0);
		$where = [];

		if ($arr) {
			$this->sqlDataTransferWhere($where, $arr);

			$delete = [
				'change_type' => 4,
			];
			$delete['out_trade_number'] = arrFieldForStr($this->setOrderAtModel($this->structureKey)->getList($where), 'orno');
			$delete['out_trade_number'] && $this->setOrderAtModel($table, false)->remove($delete);
		}

		$rule_nums = self::RULE_NUMS;
		$return = [];
		$last = [];
		do {
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
					'factory_id'			=> $v['factory_id'],
					'operator_id'			=> 0,
					'operator_type'			=> 2,
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
		   factory_id           int(11) not null,
		   worker_order_id      int(11) not null comment 'order_id，维修工单id',
		   orno                 varchar(255) not null comment '工单号',
		   pay_money            decimal(10,2) not null default 0.00 comment '支付金额',
		   last_money           decimal(10,2) not null comment '厂家余额',
		   create_time          int not null default 0 comment 'add_time，添加时间',
		   primary key (id)
		) ENGINE=INNODB AUTO_INCREMENT={$nums} DEFAULT CHARSET=utf8 comment='厂家-维修金-支付记录';
MYSQL;
		$this->resultRule[] = $this->sqlRunEnd($sql);
		$this->rule['is_db_name'] = $this->tableShowStatus($this->resultRule['db_name']);
	}
	
}
