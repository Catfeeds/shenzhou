<?php
/**
 * Created by PhpStorm.
 * User: huangyingjian
 * Date: 2017/10/26
 * Time: 下午6:21
 */

namespace Script\Logic;


class ExpressTrackingLogic extends DbLogic
{
    protected $rule;
    protected $resultRule;
    protected $structureKey = 'express_track';
    const RULE_NUMS = 2500;

    protected $tableOld = 'express_track';

    protected $tableNew = 'express_tracking';

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

        $new_model = $this->setOrderAtModel($this->tableNew, false);
        $old_model = $this->setOrderAtModel($this->tableOld);

        $error = [];
        $result = 0;
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

                $id = $val['id'];
                $record = $val;

                $type = $this->getType($record);

                if (false === $type) {
                    continue;
                }

                $insert_data[] = [
                    'id'               => $id,
                    'express_number'   => $val['number'],
                    'express_code'     => $val['comcode'],
                    'data_id'          => $val['acor_id'],
                    'state'            => $val['state'],
                    'content'          => $val['content'],
                    'is_book'          => $val['is_book'],
                    'type'             => $type,
                    'create_time'      => $val['addtime'],
                    'last_update_time' => $val['last_uptime'],
                ];
                unset($record);
            }
            $new_model->insertAll($insert_data);

            $last = end($data);
            $last_id = $last['id'];

            unset($insert_data, $data);
        } while (true);

        return [$result, $error];

    }

    protected function getType(&$record)
    {
        $type = $record['type'];
        $category = $record['category'];
        if ('1' == $category) {
            if ('SO' == $type) {
                return 1;
            } elseif ('SB' == $type) {
                return 2;
            }
        } elseif ('2' == $category) {
            if ('WSO' == $type) {
                return 3;
            }
        }

        return false;
    }

    /*
     * 根据新表，旧表，备份表存在情况处理
     * @param $rule  根据新表，旧表，备份表存在情况
     */
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
        $b_model = $this->setOrderAtModel($old_table);
        $data = $b_model->getOne(['order' => 'id DESC'], 'id');
        $num = $data['id'] ? $data['id'] : 0;
        $num++;

        $sql
            = <<<MYSQL
create table {$new_table}
(
   id                   int(11) not null auto_increment,
   express_number       varchar(50) not null comment 'number 快递单号',
   express_code         varchar(128) not null comment 'comcode 快递公司代号',
   data_id              int not null default 0 comment '物流信息所属的目的数据id,结合 type使用',
   state                tinyint not null default -1 comment '运单状态：默认：-1，0在途中、1已揽收、2疑难、3已签收',
   content              text not null comment '运单详细描述',
   is_book              tinyint not null default 0 comment '是否订阅成功，0否，1是  （快递平台是否主动返回运单信息）',
   type                 tinyint not null comment '同一运单关联单号内标识，配件单：发件SO，返件：SB  ;工单预发件安装单发件:WSO so-1 sb-2 wso-3',
   create_time          int not null comment 'addtime 添加时间',
   last_update_time     int not null default 0 comment 'last_uptime 最后更新时间',
   primary key (id)
)ENGINE=INNODB AUTO_INCREMENT={$num} DEFAULT CHARSET=utf8 comment='';
MYSQL;
        $this->resultRule[] = $this->sqlRunEnd($sql);
        $this->rule['is_db_name'] = $this->tableShowStatus($new_table);

    }


}
