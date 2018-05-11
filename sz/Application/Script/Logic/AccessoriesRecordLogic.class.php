<?php
/**
 * Created by PhpStorm.
 * User: huangyingjian
 * Date: 2017/10/26
 * Time: 下午3:25
 */

namespace Script\Logic;


class AccessoriesRecordLogic extends DbLogic
{
    protected $rule;
    protected $resultRule;
    protected $structureKey = 'factory_acce_order_record';
    const RULE_NUMS = 2500;

    protected $tableOld = 'factory_acce_order_record';

    protected $tableNew = 'worker_order_apply_accessory_record';

    public function __construct($rule)
    {
        $this->rule = $rule ? $rule : $this->isDbStructure($this->structureKey);
    }

    public function syncTimeCheckAndUpdateWhere(&$merge)
    {
        $model = $this->setOrderAtModel($this->structureKey);
        $opts = [
            'field' => 'id',
            'where' => [
                C('SYNC_TIME') => ['gt', $this->syncTimeWhere(0, $this->structureKey)],
            ],
        ];
        $data = $model->getList($opts);

        foreach ($data as $value) {
            $merge['id'][] = $value['id'];
        }
    }

    protected function sqlDataTransferWhere(&$where, $arr = [])
    {
        $return = [];
        foreach ($arr as $key => $value) {
            switch ($key) {
                case 'id':
                    $ids = implode(',', array_unique(array_filter($value)));
                    $where['id'][] = $return['id'][] = ['in', $ids];
                    break;
            }
        }

        return $return;
    }

    // 数据开始迁移
    public function sqlDataTransfer($increment = [])
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024m');

        $new_model = $this->setOrderAtModel($this->tableNew, false);
        $old_model = $this->setOrderAtModel($this->tableOld);

        $where = [];
        if ($increment) {
            //增量同步
            $delete = $this->sqlDataTransferWhere($where, $increment);
            $new_model->remove($delete);
        }

        $last_id = 0;

        $result = 0;
        $error = [];
        $where['id'][] = ['gt', &$last_id];

        $opts = [
            'where' => $where,
            'limit' => self::RULE_NUMS,
            'order' => 'id',
        ];

        $lose_efficacy = $this->getLoseEfficacy();

        $factory = $this->getFactory(); // 厂家
        $factory_admin = $this->getFactoryAdmin(); // 厂家子账号

        do {
            $data = $old_model->getList($opts);
            if (empty($data)) {
                break;
            }

            $insert_data = [];
            foreach ($data as $val) {
                $acce_order_id = $val['acce_order_id'];
                if (in_array($acce_order_id, $lose_efficacy)) {
                    continue;
                }

                $record = $val;

                $user_type = $this->getUserType($record, $factory, $factory_admin);
                if (false === $user_type) {
                    continue;
                }

                $type = $this->getType($record);
                if (false === $type) {
                    continue;
                }

                $insert_data[] = [
                    'id'                 => $val['id'],
                    'accessory_order_id' => $acce_order_id,
                    'create_time'        => $val['add_time'],
                    'user_id'            => $val['ope_user_id'],
                    'user_type'          => $user_type,
                    'type'               => $type,
                    'content'            => $val['operation'],
                    'remark'             => $val['desc'],
                ];
            }
            $new_model->insertAll($insert_data);

            $last = end($data);
            $last_id = $last['id'];

            unset($insert_data, $data);
        } while (true);

        unset($lose_efficacy, $factory, $factory_admin);

        return [$result, $error];
    }

    protected function getLoseEfficacy()
    {
        $opts = [
            'field' => 'distinct faor.acce_order_id',
            'join'  => [
                'left join factory_acce_order as fao on faor.acce_order_id = fao.id'
            ],
            'alias' => 'faor',
            'where' => ['_string' => 'fao.id IS NULL']
        ];
        $old_model = $this->setOrderAtModel($this->tableOld);
        $lose_efficacy = $old_model->getList($opts);

        return array_column($lose_efficacy, 'acce_order_id');
    }

    protected function getFactory()
    {
        $factory_db = $this->setOrderAtModel('factory');
        $opts = [
            'field' => 'factory_id'
        ];
        $data = $factory_db->getList($opts);

        return array_column($data, 'factory_id');
    }

    protected function getFactoryAdmin()
    {
        $factory_admin_db = $this->setOrderAtModel('factory_admin');
        $opts = [
            'field' => 'id'
        ];
        $data = $factory_admin_db->getList($opts);

        return array_column($data, 'id');
    }

    protected function getUserType(&$record, &$factory, &$factory_admin)
    {
        //1-客服[管理员] 2-厂家 3-厂家子账号 4-系统 5-技工
        $ope_type = trim($record['ope_role']);
        if ('admin' == $ope_type) {
            //客服
            return 1;
        } elseif ('factory' == $ope_type) {
            //厂家
            $user_id = $record['ope_user_id'];

            if (in_array($user_id, $factory)) {
                return 2;
            }

            //厂家子账号
            if (in_array($user_id, $factory_admin)) {
                return 3;
            }

        } elseif ('system' == $ope_type) {
            //系统
            return 4;
        } elseif ('worker' == $ope_type) {
            //技工
            return 5;
        }

        return false;
    }

    protected function getType(&$record)
    {
        $ope_type = trim($record['ope_type']);
        if (preg_match('#^W#', $ope_type)) {
            //技工
            $valid_type = ['WH' => 101, 'WW' => 102, 'WX' => 103, 'WV' => 104, 'WZ' => 105, 'WY' => 106];
            if (array_key_exists($ope_type, $valid_type)) {
                return $valid_type[$ope_type];
            }
        } elseif (preg_match('#^S#', $ope_type)) {
            //客服 201-审核通过 202-审核不通过
            $valid_type = ['SA' => 0, 'SG' => 203, 'SZ' => 204];
            if (array_key_exists($ope_type, $valid_type)) {
                if ('SA' == $ope_type) {
                    $operation = $record['operation'];
                    if (preg_match('#不通过#', $operation)) {
                        return 202;
                    } else {
                        return 201;
                    }
                }
                return $valid_type[$ope_type];
            }
        } elseif (preg_match('#^F#', $ope_type)) {
            //厂家 301-审核通过 302-审核不通过
            $valid_type = ['FA' => 0, 'FM' => 303, 'FB' => 304, 'FC' => 305, 'FE' => 306, 'FF' => 307, 'FD' => 308, 'FZ' => 309, 'FG' => 310];
            if (array_key_exists($ope_type, $valid_type)) {
                if ('FA' == $ope_type) {
                    $operation = $record['operation'];
                    if (preg_match('#不通过#', $operation)) {
                        return 302;
                    } else {
                        return 301;
                    }
                }

                return $valid_type[$ope_type];
            }

        } elseif (preg_match('#^A#', $ope_type)) {
            //系统
            $valid_type = ['AA' => 401, 'AB' => 402,];
            if (array_key_exists($ope_type, $valid_type)) {
                return $valid_type[$ope_type];
            }
        }

        return false;
    }

    protected function workerOrderApplyAccessoryRecord($is_delete = false)
    {
        $old_table = 'factory_acce_order_record';
        $new_table = 'worker_order_apply_accessory_record';
        if ($is_delete) {
            $result = $this->deleteTable($new_table);
            $this->resultRule[] = $result['sql'];

            return $result;
        } elseif ($this->tableShowStatus($new_table)) {
            return false;
        }

        $b_model = $this->setOrderAtModel($old_table);
        $data = $b_model->getOne(['order' => 'id DESC'], 'id');
        $num = $data['id'] ? $data['id'] : 0;
        $num++;

        $sql
            = <<<MYSQL
create table {$new_table}
(
   id                   int(11) not null auto_increment,
   accessory_order_id   int(11) not null,
   create_time          int not null comment 'add_time 添加时间',
   user_id              int not null comment 'ope_user_id 操作人ID',
   user_type            tinyint not null comment 'ope_role 操作人角色 客服[管理员]-1 厂家-2 厂家子账号-3 系统-4 技工-5',
   type                 smallint not null comment '操作类型（区间=角色）
            技工 WH 101 申请配件
            技工 WW 102 修改配件申请
            技工 WX 103 取消配件申请
            技工 WV 104 提醒厂家发货
            技工 WZ 105 签收配件
            技工 WY 106 回寄配件
            
            客服 SA 201 客服内审核配件单通过
            客服 SA 202 客服内审核配件单不通过
            客服 SG 203 厂家修改返件物流信息
            客服 SZ 204 客服终止配件单
            
            厂家 FA 301 审核配件单通过
            厂家 FA 302 审核配件单不通过
            厂家 FM 303 厂家提交配件单
            厂家 FB 304 修改预计发件时间
            厂家 FC 305 厂家确认发件
            厂家 FE 306 放弃配件返还
            厂家 FF 307 厂家修改发件物流信息
            厂家 FD 308 厂家确认返件
            厂家 FZ 309 厂家终止配件单
            厂家 FG 310 修改返件物流信息 
            
            系统 AA 401 技工已签收快件，配件单已完结
            系统 AB 402 厂家已确认返件，配件单已完结',
   content              varchar(255) not null comment 'operation 具体操作',
   remark               text not null comment 'desc 操作备注',
   primary key (id)
)ENGINE=INNODB AUTO_INCREMENT={$num} DEFAULT CHARSET=utf8 comment='';
MYSQL;

        $this->resultRule[] = $this->sqlRunEnd($sql);
    }

    /*
     * 根据新表，旧表，备份表存在情况处理
     * @param $rule  根据新表，旧表，备份表存在情况
     */
    public function structureResult()
    {
        // 删除
        $this->workerOrderApplyAccessoryRecord(true);
        // 新增
        $this->workerOrderApplyAccessoryRecord();

        return $this->resultRule;
    }



}