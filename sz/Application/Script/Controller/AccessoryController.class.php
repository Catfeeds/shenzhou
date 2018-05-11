<?php
/**
 * File: AccessoryController.class.php
 * Function: 数据迁移遗留问题, 放弃返件 且 技工已收件的配件单 完结,并补充日志作为备注
 * User: sakura
 * Date: 2018/1/14
 */

namespace Script\Controller;


use Common\Common\Logic\ExpressTrackingLogic;
use Common\Common\Service\AccessoryRecordService;
use Common\Common\Service\AccessoryService;
use Script\Model\BaseModel;

class AccessoryController extends BaseController
{

    public function complete()
    {
        $begin = time();
        try {
            $model = BaseModel::getInstance('worker_order_apply_accessory');
            $opts = [
                'field' => 'id',
                'where' => [
                    'accessory_status' => AccessoryService::STATUS_WORKER_TAKE,
                    'is_giveup_return' => ['in', [AccessoryService::RETURN_ACCESSORY_FORBIDDEN, AccessoryService::RETURN_ACCESSORY_GIVE_UP]],
                    'cancel_status'    => AccessoryService::CANCEL_STATUS_NORMAL,
                ],
            ];
            $accessories = $model->getList($opts);

            M()->startTrans();

            $remark = '';
            $content = '系统自动将配件单置为已完结';
            $extra = [
                'operator_id'   => 0,
                'operator_type' => AccessoryRecordService::ROLE_SYSTEM,
            ];
            foreach ($accessories as $accessory) {
                $accessory_id = $accessory['id'];
                AccessoryRecordService::create($accessory_id, AccessoryRecordService::OPERATE_TYPE_SYSTEM_DEFAULT_COMPLETED, $content, $remark, $extra);
            }

            if (!empty($accessories)) {
                $ids = array_column($accessories, 'id');
                $where = [
                    'id' => ['in', $ids],
                ];
                $update_data = [
                    'accessory_status' => AccessoryService::STATUS_COMPLETE,
                    'last_update_time' => NOW_TIME,
                ];
                $model->update($where, $update_data);
            }

            $end = time();

            M()->commit();

            date_default_timezone_set('GMT');
            echo date('i:s', $end - $begin);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function express()
    {
        try {

            ini_set('memory_limit', '1024m');

            $begin = time();
            set_time_limit(0);

            $new_express_model = $this->setOrderAtModel('express_tracking', false);  // 新数据库的model
            $old_accessory_model = $this->setOrderAtModel('factory_acce_order');  // 旧数据库的model

            $last_id = 0;

            $where = [
                'id' => ['gt', &$last_id],
            ];
            $field = 'id,fact_so_express_num,fact_so_com,worker_sb_com,worker_sb_express_num,worker_sb_time,estimate_so_time';
            $opts = [
                'where' => $where,
                'field' => $field,
                'order' => 'id',
                'limit' => 5000,
            ];

            M()->startTrans();

            do {

                //1.循环读取旧配件单表,获取收件 返件物流号 物流公司 发件时间
                $accessories = $old_accessory_model->getList($opts);
                if (empty($accessories)) {
                    break;
                }

                //2.获取当前循环的所有物流号,查询新库物流表,找出已记录的物流号
                $express_numbers = [];
                $accessory_ids = [];
                foreach ($accessories as $accessory) {
                    $fact_so_express_num = $accessory['fact_so_express_num'];
                    $worker_sb_express_num = $accessory['worker_sb_express_num'];
                    $id = $accessory['id'];

                    if (strlen($fact_so_express_num) > 0) {
                        $express_numbers[] = $fact_so_express_num;
                    }

                    if (strlen($worker_sb_express_num) > 0) {
                        $express_numbers[] = $worker_sb_express_num;
                    }

                    $accessory_ids[] = $id;
                }

                $express = $this->getExpress($express_numbers);

                $records = $this->getRecords($accessory_ids);

                //3.对比获取未添加物流号,插入物流表
                $insert_data = [];
                foreach ($accessories as $accessory) {
                    $fact_so_express_num = $accessory['fact_so_express_num'];
                    $worker_sb_express_num = $accessory['worker_sb_express_num'];
                    $id = $accessory['id'];
                    $fact_so_com = $accessory['fact_so_com'];
                    $worker_sb_com = $accessory['worker_sb_com'];
                    $estimate_so_time = $accessory['estimate_so_time'];
                    $worker_sb_time = $accessory['worker_sb_time'];

                    if (strlen($fact_so_express_num) > 0) {
                        $express_number = $fact_so_express_num;
                        $express_code = $fact_so_com;
                        $type = ExpressTrackingLogic::TYPE_ACCESSORY_SEND;

                        $key = $express_number . '_' . $id . '_' . $express_code . '_' . $type;

                        if (!array_key_exists($key, $express)) {
                            $send_time = empty($records[$id]) ? $estimate_so_time : $records[$id]['create_time'];
                            $insert_data[] = [
                                'express_number'   => $express_number,
                                'express_code'     => $express_code,
                                'data_id'          => $id,
                                'state'            => -1,
                                'content'          => '[]',
                                'is_book'          => 0,
                                'type'             => $type,
                                'create_time'      => $send_time,
                                'last_update_time' => NOW_TIME,

                            ];
                        }
                    }

                    if (strlen($worker_sb_express_num) > 0) {
                        $express_number = $worker_sb_express_num;
                        $express_code = $worker_sb_com;
                        $type = ExpressTrackingLogic::TYPE_ACCESSORY_SEND_BACK;

                        $key = $express_number . '_' . $id . '_' . $express_code . '_' . $type;

                        if (!array_key_exists($key, $express)) {
                            $insert_data[] = [
                                'express_number'   => $express_number,
                                'express_code'     => $express_code,
                                'data_id'          => $id,
                                'state'            => -1,
                                'content'          => '[]',
                                'is_book'          => 0,
                                'type'             => $type,
                                'create_time'      => $worker_sb_time,
                                'last_update_time' => NOW_TIME,

                            ];
                        }
                    }

                }

                $new_express_model->insertAll($insert_data);

                $last = end($accessories);
                $last_id = $last['id'];

            } while (true);

            M()->commit();

            $end = time();

            date_default_timezone_set('GMT');
            echo date('i:s', $end - $begin);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    protected function getExpress($express_numbers)
    {
        if (empty($express_numbers)) {
            return [];
        }

        $new_express_model = $this->setOrderAtModel('express_tracking', false);

        $field = 'express_number,express_code,data_id,type';
        $express_opts = [
            'field' => $field,
            'where' => [
                'express_number' => ['in', $express_numbers],
                'type'           => ['in', [ExpressTrackingLogic::TYPE_ACCESSORY_SEND, ExpressTrackingLogic::TYPE_ACCESSORY_SEND_BACK]],
            ],
        ];
        $list = $new_express_model->getList($express_opts);

        $data = [];

        foreach ($list as $val) {
            $express_number = $val['express_number'];
            $express_code = $val['express_code'];
            $data_id = $val['data_id'];
            $type = $val['type'];

            $key = $express_number . '_' . $data_id . '_' . $express_code . '_' . $type;

            $data[$key] = $val;
        }

        return $data;

    }

    protected function getRecords($accessory_ids)
    {
        if (empty($accessory_ids)) {
            return [];
        }

        $new_accessory_model = $this->setOrderAtModel('worker_order_apply_accessory_record', false);

        $where = [
            'accessory_order_id' => ['in', $accessory_ids],
            'type'               => AccessoryRecordService::OPERATE_TYPE_FACTORY_CONFIRM_SEND,
        ];
        $field = 'accessory_order_id,create_time';

        $list = $new_accessory_model->getList($where, $field);

        $data = [];

        foreach ($list as $val) {
            $accessory_order_id = $val['accessory_order_id'];

            $data[$accessory_order_id] = $val;
        }

        return $data;

    }

    protected function setOrderAtModel($table = '', $is_old = true)
    {
        $conf = $is_old ? C('DB_CONFIG_OLD_V3') : '';

        return new BaseModel($table, '', $conf);
    }


    public function setTime()
    {
        try {
            $last_id = 0;

            $limit = 2000;

            $opts = [
                'field' => 'id,accessory_status,cancel_status,is_giveup_return',
                'where' => [
                    'id' => ['gt', &$last_id],
                ],
                'order' => 'id',
                'limit' => $limit,
            ];

            $model = BaseModel::getInstance('worker_order_apply_accessory');

            while (true) {
                $accessories = $model->getList($opts);
                if (empty($accessories)) {
                    break;
                }

                $ids = array_column($accessories, 'id');

                $logs = $this->getAccessoryLog($ids);

                foreach ($accessories as $accessory) {
                    $accessory_id = $accessory['id'];
                }
            }

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    protected function getAccessoryLog($accessory_ids)
    {
        if (empty($accessory_ids)) {
            return [];
        }

        $data = [];

        $list = BaseModel::getInstance('worker_order_apply_accessory_record')
            ->getList([
                'field' => 'accessory_order_id,create_time,type',
                'where' => [
                    'accessory_order_id' => ['in', $accessory_ids],
                ],
                'order' => 'create_time,id',
            ]);

        foreach ($list as $val) {
            $data[$val['accessory_order_id']][$val['type']] = $val['create_time'];
        }

        return $data;
    }

}