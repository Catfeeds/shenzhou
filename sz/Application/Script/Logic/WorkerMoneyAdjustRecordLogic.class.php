<?php
/**
 * Created by Sublime Text
 * User: zjz
 * Date: 2017/11/25
 * PM 22:13
 */
namespace Script\Logic;

use Script\Logic\DbLogic;
use Script\Model\BaseModel;

class WorkerMoneyAdjustRecordLogic extends DbLogic
{
   protected $rule;
   protected $resultRule;
   protected $structureKey = 'worker_money_adjust_record';
   const RULE_NUMS = 1000;
   const ADMIN_TABLE          = 'admin';
   const WORKER_ORDER_TABLE   = 'worker_order';
 
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
         '0 as admin_id',
         'worker_id',
         'orno as worker_order_id',
         '0 as adjust_type',
         'add_time as create_time',
         'add_money as adjust_money',
         'last_money as worker_last_money',
         'add_money_desc as adjust_remark',
         'admin_name as cp_admin_name',
      ];

      $admins = $this->setOrderAtModel(self::ADMIN_TABLE)->getList([
         'field'  => 'id,user_name,nickout',
         'index' => 'nickout'
      ]);

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

         $orno = [];
         $pattern = '/[^\x00-\x80]/'; // bool: true 含有中文  false 不含中文
         foreach ($data as $k => $v) {
            !preg_match($pattern, $v['worker_order_id']) && $orno[$v['worker_order_id']] = trim($v['worker_order_id']);
         }

         $orno = implode(',', array_filter($orno));
         $ornos = $orno ? $this->setOrderAtModel(self::WORKER_ORDER_TABLE)->getList([
               'field' => 'orno,order_id',
               'where' => [
                  'orno' => ['in', $orno],
               ],
               'index' => 'orno',
            ]) : [];
         
         foreach ($data as $k => $v) {
            $v['admin_id'] = $admins[$v['cp_admin_name']]['id'] ?? 0;
            $v['worker_order_id'] = $ornos[$v['worker_order_id']] ? $ornos[$v['worker_order_id']]['order_id'] : 0;
            $data[$k] = $v;
         }

         $data && $db_model->insertAll($data);

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
            id                   int not null AUTO_INCREMENT,
            admin_id             int(11) not null comment '管理员id（平台客服id）',
            worker_id            int(11) not null comment 'worker_id,技工id',
            worker_order_id      int(11) comment 'order_id,维修工单id',
            adjust_type          tinyint not null default 0 comment '调整类型 0 客服手动调整；1 系统查账调整',
            create_time          int not null default 0 comment 'add_time,调整时间',
            adjust_money         decimal(10,2) not null default 0 comment 'add_money,添加金额',
            worker_last_money    decimal(10,2) not null default 0.00 comment 'last_money,调整之后的金额',
            adjust_remark        varchar(500) not null comment 'add_money_desc,备注',
            cp_admin_name        varchar(150) not null comment 'admin_name,操作员姓名',
            primary key (id)
         ) ENGINE=INNODB AUTO_INCREMENT={$nums} DEFAULT CHARSET=utf8 comment='技工资金调整日志记录';
MYSQL;

      $this->resultRule[] = $this->sqlRunEnd($sql);
      $this->rule['is_db_name'] = $this->tableShowStatus($this->resultRule['db_name']);
   }
   
}
