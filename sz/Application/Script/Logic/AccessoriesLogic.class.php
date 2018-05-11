<?php
/**
 * Created by PhpStorm.
 * User: huangyingjian
 * Date: 2017/10/24
 * Time: 上午9:30
 */

namespace Script\Logic;

class AccessoriesLogic extends DbLogic
{
    protected $rule;
    protected $resultRule;
    protected $structureKey = 'factory_acce_order';
    const RULE_NUMS = 2500;

    protected $tableOld = 'factory_acce_order';

    protected $tableNew = 'worker_order_apply_accessory';

    protected $error_msg = '';

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

    // 数据开始迁移
    public function sqlDataTransfer($increment = [])
    {
        set_time_limit(0);

        $new_model = $this->setOrderAtModel($this->tableNew, false);  // 新数据库的model
        $old_model = $this->setOrderAtModel($this->tableOld);  // 旧数据库的model

        $where = [];
        if ($increment) {
            //增量同步
            $delete = $this->sqlDataTransferWhere($where, $increment);
            $new_model->remove($delete);
        }

        $result = 0;
        $error = [];

        $first_id = 0; // 第一个配件单id
        $last_id = 0; // 最后一个配件单id

        $where['id'][] = ['gt', &$last_id];

        $opts = [
            'where' => $where,
            'limit' => self::RULE_NUMS,
            'order' => 'id',
        ];

        $record_where = [
            'acce_order_id' => [
                ['egt', &$first_id],
                ['elt', &$last_id],
            ],
        ];

        do {
            $accessory = $old_model->getList($opts);
            if (empty($accessory)) {
                break;
            }

            //获取当前批次第一个和最后一个配件单
            $first_accessory_info = $accessory[0];
            $first_id = $first_accessory_info['id'];
            $last_accessory_info = end($accessory);
            $last_id = $last_accessory_info['id'];

            $record_list = $this->getRecord($record_where); // 获取日志

            $insert_data = [];
            foreach ($accessory as $accessory_info) {
                $accessory_id = $accessory_info['id'];

                $log = $record_list[$accessory_id]?? [];

                $is_giveup_return = $this->getIsGiveUpReturn($accessory_info, $log);

                $accessory_info['is_giveup_return'] = $is_giveup_return;

                $factory_send_time = $this->getFactorySendTime($log);

                $accessory_status = $this->getAccessoryStatus($accessory_info);

                $last_update_time = $this->getLastUpdateTime($accessory_info, $log);

                $cancel_status = $this->getCancelStatus($accessory_info, $log);

                $insert_data[] = [
                    'id'                       => $accessory_id,
                    'factory_id'               => $accessory_info['factory_id'],
                    'worker_order_id'          => $accessory_info['worker_order_id'],
                    'worker_id'                => $accessory_info['worker_id'],
                    'worker_order_product_id'  => $accessory_info['worker_order_detail_id'],
                    'accessory_number'         => $accessory_info['acce_order_number'],
                    'addressee_name'           => $accessory_info['applicant'],
                    'addressee_phone'          => $accessory_info['applicant_tell'],
                    'addressee_area_ids'       => $accessory_info['receive_area'],
                    'cp_addressee_area_desc'   => $accessory_info['receive_area_desc'],
                    'addressee_address'        => $accessory_info['receiving_address'],
                    'accessory_imgs'           => $accessory_info['acce_photos'],
                    'apply_reason'             => $accessory_info['apply_reason'],
                    'factory_check_remark'     => $accessory_info['reply'],
                    'admin_check_remark'       => $accessory_info['cs_reply'],
                    'is_giveup_return'         => $is_giveup_return,
                    'factory_giveup_return'    => $accessory_info['giveup_sb_reason'],
                    'worker_transport_fee'     => $accessory_info['worker_sb_cost'],
                    'worker_return_pay_method' => $accessory_info['worker_sb_paytype'],
                    'worker_return_time'       => $accessory_info['worker_sb_time'],
                    'executive_type'           => 1,
                    'factory_estimate_time'    => $accessory_info['estimate_so_time'],
                    'create_time'              => $accessory_info['addtime'],
                    'cancel_status'            => $cancel_status,
                    'factory_send_time'        => $factory_send_time,
                    'accessory_status'         => $accessory_status,
                    'last_update_time'         => $last_update_time,
                ];
                unset($record, $log);
            }

            $new_model->insertAll($insert_data);
            unset($data, $insert_data, $record_list);

        } while (true);

        return [$result, $error];
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

    protected function getRecord($where)
    {
        $record_db = $this->setOrderAtModel('factory_acce_order_record');
        $field = 'id as log_id,acce_order_id,ope_type,add_time';
        $opts = [
            'where' => $where,
            'order' => 'add_time',
            'field' => $field,
        ];
        $data = $record_db->getList($opts);

        $record_list = [];
        foreach ($data as $val) {
            $acce_order_id = $val['acce_order_id'];
            $record_list[$acce_order_id][] = $val;
        }

        return $record_list;
    }

    protected function getRecordRange($range, &$exclude_list)
    {
        $record_db = $this->setOrderAtModel('factory_acce_order_record');
        $field = 'id as log_id,acce_order_id,ope_type,add_time';
        $where = ['acce_order_id' => [['in', $range]]];
        if (!empty($exclude_list)) {
            $where['acce_order_id'][] = ['not in', $exclude_list];
        }
        $opts = [
            'where' => $where,
            'order' => 'add_time',
            'field' => $field,
        ];
        $data = $record_db->getList($opts);
        $record_list = [];
        foreach ($data as $val) {
            $acce_order_id = $val['acce_order_id'];
            $record_list[$acce_order_id][] = $val;
        }

        return $record_list;
    }

    protected function getIsGiveUpReturn(&$record, &$log)
    {
        $is_giveup_return = $record['is_giveup_sendback'];

        //厂家放弃返件
        if (1 == $is_giveup_return) {
            $is_factory_cancel = $this->isFinishStep($log, 'FE');
            if ($is_factory_cancel) {
                $is_giveup_return = 2; //中途放弃返件
            }
        }

        return $is_giveup_return;
    }

    protected function getCancelStatus(&$record, &$log)
    {
        $is_cancel = $record['is_cancel'];
        //配件单关闭状态，0为未关闭，1为已关闭
        if (0 == $is_cancel) {
            return 0;
        }

        //1客服取消，2厂家取消
        if (1 == $is_cancel) {
            $is_admin = $this->isFinishStep($log, 'SZ');
            $is_factory = $this->isFinishStep($log, 'FZ');

            if ($is_admin) {
                //客服
                return 1;
            }
            if ($is_factory) {
                //厂家
                return 2;
            }
        }


        return 0;
    }

    protected function getFactorySendTime(&$log)
    {
        foreach ($log as $log_data) {
            $ope_type = $log_data['ope_type'];
            if ('FC' == $ope_type) {
                return $log_data['add_time'];
            }
        }

        return 0;
    }

    protected function getLastUpdateTime(&$record, &$log)
    {
        $create_time = $record['addtime'];
        if (empty($log)) {
            return $create_time;
        } else {
            $add_time_arr = array_column($log, 'add_time');

            $add_time_arr[] = $create_time;

            return max($add_time_arr);
        }
    }

    protected function getAccessoryStatus(&$record)
    {
        $status = 1; // 申请配件

        $is_cs_check = $record['is_cs_check'];
        $is_check = $record['is_check'];
        $is_cancel = $record['is_cancel'];
        $is_complete = $record['is_complete'];
        $is_fact_send = $record['is_fact_send'];
        $is_worker_get = $record['is_worker_get'];
        $is_worker_send = $record['is_worker_send'];

        //客服审核状态，0为未审核，1为审核通过，2为审核不通过
        if (2 == $is_cs_check) {
            //审核不通过
            return 2;
        } elseif (1 == $is_cs_check) {
            //审核通过
            $status = 3;
        }

        //厂家审核状态 0未审核，1审核通过，2审核不通过
        if (2 == $is_check) {
            return 4;
        } elseif (1 == $is_check) {
            $status = 5; // 厂家已审核
        }

        //已取消
        if (1 == $is_cancel) {
            //配件单关闭,找出关闭前一个状态

            //厂家发件
            if (1 == $is_fact_send) {
                $status = 6;
            }

            // 技工签收
            if (1 == $is_worker_get) {
                $status = 7;
            }

            //技工返件
            if (1 == $is_worker_send) {
                $status = 8;
            }

            return $status;
        }

        //已完成
        if (1 == $is_complete) {
            return 9;
        }

        //厂家发件
        if (1 == $is_fact_send) {
            $status = 6;
        }

        // 技工签收
        if (1 == $is_worker_get) {
            $status = 7;
        }

        //技工返件
        if (1 == $is_worker_send) {
            $status = 8;
        }

        return $status;
    }

    protected function isFinishStep(&$log, $step_type)
    {
        foreach ($log as $log_data) {
            $ope_type = $log_data['ope_type'];

            if ($step_type == $ope_type) {
                return true;
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
   factory_id           int(11) not null,
   worker_order_id      int(11) not null comment 'order_id 维修工单id',
   worker_id            int(11) not null comment 'worker_id 技工id',
   worker_order_product_id int(11) not null comment 'order_detail_id 工单详情id',
   accessory_number     varchar(32) not null comment 'acce_order_number 配件单号',
   addressee_name       varchar(16) not null comment 'applicant 申请人（收件人姓名）',
   addressee_phone      varchar(16) not null comment 'applicant_tell 申请人电话(收件人电话)',
   addressee_area_ids   varchar(47) not null comment 'receive_area 收件所属区域IDS，多个逗号分隔'',''',
   cp_addressee_area_desc varchar(255) not null comment 'receive_area_desc 地区描述',
   addressee_address    varchar(255) not null comment 'receiving_address 收件地址',
   accessory_imgs       text not null comment 'acce_photos 申请配件照片 (json)',
   apply_reason         text not null comment '申请配件原因',
   factory_check_remark varchar(255) not null comment 'reply 厂家审核回复',
   admin_check_remark   varchar(255) not null comment 'cs_reply 平台客服审核意见（回复）',
   is_giveup_return     tinyint not null default 0 comment 'is_giveup_sendback 0，默认需要返件；1，默认放弃返件；2，配件单申请后续放弃返件；',
   factory_giveup_return varchar(255) not null comment 'giveup_sb_reason 厂家放弃返件原因',
   worker_transport_fee decimal(10,2) not null default 0.00 comment 'worker_sb_cost 技工返件运费',
   worker_return_pay_method tinyint not null comment 'worker_sb_paytype 技工返件付款方式，1为现付，2为到付',
   worker_return_time   int not null comment 'worker_sb_time 技工返件时间',
   executive_type       tinyint not null comment 'exe_type 配单流程执行模式，A为先发后返，B为先返后发，C厂家默认先发，技工收件后返件',
   factory_estimate_time int not null comment 'estimate_so_time 预计厂家发件时间,  (厂家定的时间)',
   create_time          int not null comment 'addtime 配件单添加（申请）时间',
   cancel_status        int not null comment '0 正常 1客服取消，2厂家取消 3，技工取消（终止）',
   factory_send_time    int not null comment 'fact_so_time',
   accessory_status     tinyint not null comment '1.申请配件（创建配件信息，待客服审核）2.客服审核不通过（完结）3客服审核（待厂家审核）4厂家审核不通过（完结）5厂家审核（待厂家发件）6厂家发件（待技工收件）7技工签收（待技工返件）8技工返件（待厂家确认收件）9完结',
   last_update_time     int not null default 0,
   primary key (id)
) ENGINE=INNODB AUTO_INCREMENT={$num} DEFAULT CHARSET=utf8 comment='';
MYSQL;
        $this->resultRule[] = $this->sqlRunEnd($sql);
        $this->rule['is_db_name'] = $this->tableShowStatus($new_table);

    }


    protected function setErrorMsg($prompt)
    {
        $this->error_msg = $prompt;
    }

    protected function getErrorMsg()
    {
        return $this->error_msg;
    }

}

