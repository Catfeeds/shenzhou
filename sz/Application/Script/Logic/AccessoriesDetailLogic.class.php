<?php
/**
 * Created by PhpStorm.
 * User: huangyingjian
 * Date: 2017/10/26
 * Time: 下午2:07
 */

namespace Script\Logic;


class AccessoriesDetailLogic extends DbLogic
{
    protected $rule;
    protected $resultRule;
    protected $structureKey = 'factory_acce_order_detail';
    const RULE_NUMS = 2500;

    protected $tableOld = 'factory_acce_order_detail';

    protected $tableNew = 'worker_order_apply_accessory_item';

    public function __construct($rule)
    {
        $this->rule = $rule ? $rule : $this->isDbStructure($this->structureKey);
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

    // 数据开始迁移
    public function sqlDataTransfer($increment = [])
    {
        set_time_limit(0);

        $new_model = $this->setOrderAtModel($this->tableNew, false);
        $old_model = $this->setOrderAtModel($this->tableOld);

        $lose_efficacy_ids = $this->getLoseEfficacy(); // 失效配件单

        $result = 0;
        $error = [];
        $where = [];
        if ($increment) {
            //增量同步
            $delete = $this->sqlDataTransferWhere($where, $increment);
            $new_model->remove($delete);
        }

        $last_id = 0;

        $where['id'][] = ['gt', &$last_id];
        $opts = [
            'where' => $where,
            'limit' => self::RULE_NUMS,
            'order' => 'id',
        ];

        do {
            $data = $old_model->getList($opts);
            if (empty($data)) {
                break;
            }

            $insert_data = [];
            foreach ($data as $val) {

                $acce_order_id = $val['acce_order_id'];
                if (in_array($acce_order_id, $lose_efficacy_ids)) {
                    continue;
                }

                $insert_data[] = [
                    'id'                      => $val['id'],
                    'accessory_order_id'      => $acce_order_id,
                    'worker_id'               => $val['worker_id'],
                    'accessory_id'            => $val['acce_id'],
                    'acce_type_id'            => $val['acce_type'],
                    'is_normal'               => $val['is_normal'],
                    'name'                    => $val['acce_name'],
                    'cp_accessory_type_desc'  => $val['acce_type_desc'],
                    'brand'                   => $val['acce_brand'],
                    'cp_accessory_brand_desc' => $val['acce_brand_desc'],
                    'mode'                    => $val['acce_model'],
                    'thumb'                   => $val['acce_thumb'],
                    'nums'                    => $val['nums'],
                    'code'                    => $val['acce_code'],
                    'remark'                  => $val['remarks'],
                ];
            }

            $new_model->insertAll($insert_data);

            $last = end($data);
            $last_id = $last['id'];

            unset($insert_data, $data);

        } while (true);

        unset($lose_efficacy_ids);

        return [$result, $error];
    }

    protected function getLoseEfficacy()
    {
        $opts = [
            'field' => 'distinct faod.acce_order_id',
            'join'  => [
                'left join factory_acce_order as fao on faod.acce_order_id = fao.id',
            ],
            'alias' => 'faod',
            'where' => ['_string' => 'fao.id IS NULL'],
        ];
        $old_model = $this->setOrderAtModel($this->tableOld);
        $lose = $old_model->getList($opts);

        return array_column($lose, 'acce_order_id');
    }

    public function structureResult()
    {
        // 删除
        $this->createNewStructures(true);
        // 新增
        $this->createNewStructures();

        return $this->resultRule;
    }

    protected function createNewStructures($is_delete = false)
    {
        $old_table = $this->tableOld;
        $new_table = $this->tableNew;
        $result = $this->deleteTable($new_table);
        $this->resultRule[] = $result['sql'];
        if ($is_delete) {
            $this->rule['is_db_name'] = $this->tableShowStatus($new_table);

            return $result;
        }
        $old_model = $this->setOrderAtModel($old_table);
        $data = $old_model->getOne(['order' => 'id DESC'], 'id');
        $num = $data['id'] ? $data['id'] : 0;
        $num++;

        $sql
            = <<<MYSQL
create table {$new_table}
(
   id                   int(11) not null auto_increment,
   accessory_order_id   int(11) not null,
   worker_id            int(11) not null comment 'worker_id 技工id',
   accessory_id         int(11),
   acce_type_id         int(11) comment 'acce_type',
   is_normal            tinyint not null default 0 comment '是否非标配件，0是，1否',
   name                 varchar(128) not null comment 'acce_name 配件名称',
   cp_accessory_type_desc varchar(255) not null comment 'acce_type_desc 配件类型，描述',
   brand                int not null comment 'acce_brand 配件品牌ID  (外)',
   cp_accessory_brand_desc varchar(255) not null comment 'acce_brand_desc 配件品牌描述',
   mode                 varchar(255) not null comment 'acce_model 配件型号',
   thumb                text comment 'acce_thumb 配件缩略图',
   nums                 int not null comment 'nums 数量',
   code                 varchar(255) not null comment 'acce_code 配件编码',
   remark               varchar(255) not null comment 'remarks 配件的申请备注',
   primary key (id)
)ENGINE=INNODB AUTO_INCREMENT={$num} DEFAULT CHARSET=utf8 comment='';
MYSQL;
        $this->resultRule[] = $this->sqlRunEnd($sql);
        $this->rule['is_db_name'] = $this->tableShowStatus($new_table);

        $this->resultRule[] = $this->sqlRunEnd($sql);
    }

}