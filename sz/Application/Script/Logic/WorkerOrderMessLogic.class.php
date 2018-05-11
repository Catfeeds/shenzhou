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

class WorkerOrderMessLogic extends DbLogic
{
	
	protected $rule;
	protected $resultRule;
	protected $structureKey = 'worker_order_mess';
	const RULE_NUMS 		= 1000;
	const ADMIN_TABLE 		= 'admin';
	const WORKER_ORDER_ACCESS = 'worker_order_access';

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
    				$where['wom.id'] = $return['id'] = ['in', $ids];
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

		$rule_nums = self::RULE_NUMS;
		$return = [];
		$field_arr = [
			'wom.id',
			'wom.worker_order_id',
			'wom.type as add_type',
			'0 as add_id',
			'wom.content',
			'1 as content_type',
			'wom.addtime as create_time',
			'wom.mess_role as receive_type',
			'wo.worker_id',
			'wo.factory_id',
			'wom.name',
			'wom.thumb',
		];

		$admins = $this->setOrderAtModel(self::ADMIN_TABLE)->getList([
	            'field'  => 'id,user_name',
	            'index' => 'user_name'
	        ]);
		
		$string = ' wo.order_id is not null ';
		$where['_string'] = $string;

		if ($arr) {
			$delete = $this->sqlDataTransferWhere($where, $arr);
			$this->setOrderAtModel($this->rule['db_name'], false)->remove($delete);
		}

		$access = [];
		do {
			$i += 1;
			$db_model = $this->setOrderAtModel($this->rule['db_name'], false);
			$ch_model = $this->setOrderAtModel($this->structureKey);  // 旧数据库的model

			$data = $ch_model->getList([
				'alias' => 'wom',
				'join'  => 'left join worker_order wo on wom.worker_order_id = wo.order_id',
				'field' => implode(',', $field_arr),
				'where' => $where,
				'limit' => $rule_nums,
				'order' => 'wom.id asc',
				'index' => 'id',
			]);
			$order_id = $change = [];
			foreach ($data as $k => $v) {
				switch ($v['receive_type']) {
                    case 'A' :
                        if ($v['add_type'] == 'W') {
                            $v['add_type'] = 4;
                            $v['receive_type'] = 1;
                            $v['add_id'] = $v['worker_id'];
                        } else {
                            $v['add_type'] = 1;
                            $v['receive_type'] = 4;
                        }
                        break;
                    case 'B' :
                        if ($v['add_type'] == 'F') {
                            $v['add_type'] = 2;
                            $v['receive_type'] = 1;
                            $v['add_id'] = $v['factory_id'];
                        } else {
                            $v['add_type'] = 1;
                            $v['receive_type'] = 2;
                        }
                        break;
                }
                // if ($v['add_type'] == 'S') {
                if (in_array($v['add_type'], [1, 'S'])) {
                    $v['add_id'] = $admins[$v['name']]['id'];
                    if (empty($v['add_id'])) {
                        !isset($access[$v['worker_order_id']]) && $order_id[$v['worker_order_id']] =  $v['worker_order_id'];
                        $change[$v['worker_order_id']][] = $v['id'];
                    }
                }
                if ($v['thumb']) {
                 	$v['content_type'] = 2;
                 	$v['content'] = $v['thumb'];
                 }

                unset($v['worker_id'], $v['factory_id'], $v['name'], $v['thumb']);
                $data[$k] = $v;
			}

			// 权限
			$order_ids = implode(',', array_filter($order_id));
            $access += $order_ids ? $this->setOrderAtModel(self::WORKER_ORDER_ACCESS)->getList([
            	'field' => 'admin_id,link_order_id',
                'where' => [
                    'role_id' => 5,
                    'link_order_id' => ['in', $order_ids]
                ],
                'index' => 'link_order_id',
            ]) : [];

            foreach ($change as $k => $v) {
                foreach ($v as $value) {
                    $data[$value]['add_id'] = intval($access[$k]['admin_id']);
                }
            }

			$data && $db_model->insertAll(array_values($data));

			$end = end($data);
			$end['id'] && $where['_string'] = " wom.id > {$end['id']} AND {$string} ";
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
		   worker_order_id      int(11) not null comment 'order_id,维修工单id',
		   add_type             tinyint not null comment 'type # 发起留言角色，A客服，B厂家，C维修商，D客户..1 客服，2 厂家，3厂家子帐号，4 技工（维修商），5 工单客户 6，经销商',
		   add_id               int not null,
		   content              text not null comment '留言内容',
		   content_type         tinyint not null default 0 comment '内容类型 0 其他；1文字；2图片',
		   create_time          int not null comment 'addtime,留言时间',
		   receive_type         tinyint not null comment 'mess_role,# 接收角色:A客服，B厂家，C维修商，D客户..1 客服，2 厂家，3厂家子帐号，4 技工（维修商），5 工单客户 6，经销商',
		   is_read              tinyint not null default 0 comment '是否已读 0 未读  1 已读',
		   read_time            int not null default 0 comment '已读时间',
		   primary key (id)
		) ENGINE=INNODB AUTO_INCREMENT={$nums} DEFAULT CHARSET=utf8 comment='工单留言记录';
MYSQL;
	
		$this->resultRule[] = $this->sqlRunEnd($sql);
		$this->rule['is_db_name'] = $this->tableShowStatus($this->resultRule['db_name']);
	}

}
