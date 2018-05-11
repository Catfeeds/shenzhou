<?php
/**
 * Created by Sublime Text
 * User: zjz
 * Date: 2017/12/28
 * PM 11:437
 */
namespace Script\Logic;

use Script\Logic\DbLogic;
use Script\Model\BaseModel;

class WorkerOrderHintLogic extends DbLogic
{
	protected $rule;
	protected $resultRule;
	protected $structureKey = 'worker_order_hint';
	const RULE_NUMS = 1000;

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

		// $db_model = $this->setOrderAtModel($this->rule['db_name'], false);  // 新数据库的model
		// $ch_model = $this->setOrderAtModel($this->structureKey);  // 旧数据库的model

		$where = [];

		if ($arr) {
    		$delete = $this->sqlDataTransferWhere($where, $arr);
    		$this->setOrderAtModel($this->rule['db_name'], false)->remove($delete);
    	}

		// $num = $ch_model->getNum($where);
		$rule_nums = self::RULE_NUMS;
		// $foreach_num = ceil($num/$rule_nums);

		$return = [];
		// for ($i=1; $i <= $foreach_num; $i++) {
		do {
			$i += 1;
			$db_model = $this->setOrderAtModel($this->rule['db_name'], false);
			$ch_model = $this->setOrderAtModel($this->structureKey);  // 旧数据库的model

			$data = $ch_model->getList([
				'field' => 'id,worker_id,order_id as data_id,title,title as `describe`,content,type,is_read as is_read,addtime as create_time',
				'where' => $where,
				'limit' => $rule_nums,
				'order' => 'id asc',
			]);

			$end = end($data);
			$end['id'] && $where['_string'] = ' id > '.$end['id'].' ';

			$add_data = [];
			foreach ($data as $k => &$v) {
				$this->setTypeRule($v);
				if ($v['type']) {
					$add_data[] = $v;
				}
			}

			$add_data && $db_model->insertAll($add_data);
			
			unset($data);
		} while ($end);
		// }
		return [];
	}

	public function setTypeRule(&$data)
	{
		$title = [
			// '工单产品修改' => '',
			// '您有一条留言'	=> '',
			'您有一条新工单' => 301,
			'您的工单结算完成' => 201,
			'您的配件申请审核不通过' => 406,
			'您的配件申请审核通过' => 401,
			'提现失败' => 206,
			'提现成功' => 205,
			'费用申请未通过' => 503,
			'费用申请通过' => 502,
			'配件发货通知' => 402,
			'配件延迟发货' => 407,
		];
		$data['type'] = $title[$data['title']] ?? null;
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
		create table {$this->rule['db_name']}
		(
		   id                   int(11) not null auto_increment,
		   worker_id            int(11) not null comment 'worker_id,技工id',
		   data_id              int not null default 0 comment 'order_id ',
		   title                varchar(60) not null comment '标题',
		   `describe`           varchar(225),
		   content              text not null comment '内容',
		   type                 smallint not null comment '类型
		            0=全部，201=结算消息，202=提现消息，203=其他；301=新工单，302=明天需上门，303=回访通过，304=其他；401=待发件，402=已发件，403=待返件，404=其他；501=待厂家审核，502=审核通过，503=审核不通过',
		   is_read              smallint not null comment '读过为1 未读为0',
		   create_time          int not null comment '添加时间 addtime',
		   primary key (id)
		)ENGINE=INNODB AUTO_INCREMENT={$nums} DEFAULT CHARSET=utf8 comment='worker_order_hint 工单通知表 (推送通知),v3.0 worker_notificatio';
MYSQL;

		$this->resultRule[] = $this->sqlRunEnd($sql);
		$this->rule['is_db_name'] = $this->tableShowStatus($this->resultRule['db_name']);
	}
	
}
