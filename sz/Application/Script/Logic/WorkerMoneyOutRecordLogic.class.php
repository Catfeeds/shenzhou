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

class WorkerMoneyOutRecordLogic extends DbLogic
{
	
	protected $rule;
	protected $resultRule;
	protected $structureKey = 'worker_money_out_record';
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
			'city_level1 as province_id',
			'city_level2 as city_id',
			'worker_id',
			'mono as withdraw_cash_number',
			'add_time as create_time',
			'out_money',
			'IF(status=4,0,status) as status',
			'IF(status!=0,1,0) as is_in_excel',
			'`desc` as fail_reason',
			'0 as completer_id',
			'complete_time',
			'card_user_name as real_name',
			'bank_id',
			'bank_name',
			'other_bank_name',
			'card as card_number',
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
		   bank_id              int(11) not null comment '选项id',
		   province_id          int(11) not null comment '选项id',
		   city_id              int(11) not null comment '选项id',
		   completer_id         int(11) not null comment '管理员id（平台客服id）',
		   worker_id            int(11) not null comment 'worker_id,技工id',
		   withdrawcash_excel_id int,
		   withdraw_cash_number varchar(18) not null comment 'mono,提现单号',
		   create_time          int not null default 0 comment 'add_time,添加时间',
		   out_money            decimal(10,2) not null comment '提现的金额',
		   status               tinyint not null default 0 comment '0进行中 1提现成功 2提现失败',
		   fail_reason          varchar(255) not null comment 'desc,提现失败原因',
		   complete_time        int not null default 0 comment '完结时间',
		   real_name            varchar(32) not null comment 'card_user_name,开户人名称',
		   bank_name            varchar(150) not null comment '银行名称',
		   other_bank_name      varchar(150) not null comment '其他银行的信息',
		   card_number          varchar(32) not null comment 'card银行账号',
		   primary key (id)
		) ENGINE=INNODB AUTO_INCREMENT={$nums} DEFAULT CHARSET=utf8 comment='技工提现记录';
MYSQL;
	
		$this->resultRule[] = $this->sqlRunEnd($sql);
		$this->rule['is_db_name'] = $this->tableShowStatus($this->resultRule['db_name']);
	}

}
