<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/3/30
 * Time: 10:44
 */

namespace Script\Controller;


use Common\Common\Service\AccessoryRecordService;
use Common\Common\Service\AccessoryService;
use Common\Common\Service\ApplyCostRecordService;
use Common\Common\Service\CostRecordService;
use Script\Model\BaseModel;

class WorkerOrderController extends BaseController
{

    public function workerOrderApplyAccessoryActionTime()
    {
        set_time_limit(0);
        try {
            $field_time = [
                'SUBSTRING_INDEX(group_concat(create_time order by create_time desc), ",", 1) as action_time',
                'type',
                'accessory_order_id',
            ];
            $type = [
                AccessoryRecordService::OPERATE_TYPE_CS_CHECKED,                    // 客服审核
                AccessoryRecordService::OPERATE_TYPE_CS_FORBIDDEN,                  // 客服审核不通过

                AccessoryRecordService::OPERATE_TYPE_FACTORY_CHECKED,               // 厂家审核
                AccessoryRecordService::OPERATE_TYPE_FACTORY_FORBIDDEN,             // 厂家审核不通过

                AccessoryRecordService::OPERATE_TYPE_WORKER_TAKE,                   // 技工签收配件
                AccessoryRecordService::OPERATE_TYPE_FACTORY_CONFIRM_SEND_BACK,     // 厂家确认收件

                AccessoryRecordService::OPERATE_TYPE_CS_STOP_APPLY,                 // 客服终止配件
                AccessoryRecordService::OPERATE_TYPE_FACTORY_STOP_APPLY,            // 厂家终止配件

                AccessoryRecordService::OPERATE_TYPE_SYSTEM_DEFAULT_SEND_BACK,      // 系统自动将配件单置为已完结(物流通知被动更新)
                AccessoryRecordService::OPERATE_TYPE_SYSTEM_DEFAULT_COMPLETED,      // 系统自动将配件单置为已完结(修复脚本)
//                AccessoryRecordService::OPERATE_TYPE_FACTORY_GIVE_UP_SEND_BACK,     // 放弃返件
            ];

            $key_value = [
                AccessoryRecordService::OPERATE_TYPE_CS_CHECKED         => 'admin_check_time',
                AccessoryRecordService::OPERATE_TYPE_CS_FORBIDDEN       => 'admin_check_time',

                AccessoryRecordService::OPERATE_TYPE_FACTORY_CHECKED    => 'factory_check_time',
                AccessoryRecordService::OPERATE_TYPE_FACTORY_FORBIDDEN    => 'factory_check_time',

                AccessoryRecordService::OPERATE_TYPE_WORKER_TAKE        => 'worker_receive_time',
                AccessoryRecordService::OPERATE_TYPE_FACTORY_CONFIRM_SEND_BACK => 'factory_confirm_receive_time',

                AccessoryRecordService::OPERATE_TYPE_CS_STOP_APPLY      => 'stop_time',
                AccessoryRecordService::OPERATE_TYPE_FACTORY_STOP_APPLY => 'stop_time',

                AccessoryRecordService::OPERATE_TYPE_SYSTEM_DEFAULT_SEND_BACK => 'complete_time',
                AccessoryRecordService::OPERATE_TYPE_SYSTEM_DEFAULT_COMPLETED => 'complete_time',
//                AccessoryRecordService::OPERATE_TYPE_FACTORY_GIVE_UP_SEND_BACK => 'cp_extend_time',

            ];

            $count = 0;
            $model = BaseModel::getInstance('worker_order_apply_accessory');
            $desc_id_str = '';
            $now_page = 1;
//            M()->execute("ALTER TABLE `worker_order_apply_accessory` ADD `cp_extend_time` INT(10)  UNSIGNED  NOT NULL  DEFAULT '0'  COMMENT '数据迁移辅助字段'  AFTER `complete_time`;");
            do {
                $count = 0;
                $sql = "select  ".implode(',', $field_time)." from worker_order_apply_accessory_record where `type` in (".implode(',', $type).") {$desc_id_str} group by `type`,accessory_order_id order by accessory_order_id asc limit 10000";
                $a = M()->query("$sql");
                $desc_id = end($a)['accessory_order_id'];
                ++$now_page;
                if ($desc_id) {
                    $desc_id_str = " and accessory_order_id > {$desc_id} ";
                    $next_id_str = " and accessory_order_id = {$desc_id} ";
                    $sql = "select  ".implode(',', $field_time)." from worker_order_apply_accessory_record where `type` in (".implode(',', $type).") {$next_id_str} group by `type`,accessory_order_id order by accessory_order_id asc limit 10000";
                    $b = (array)M()->query("$sql");
                    $a = array_merge($a, $b);
                }

//                var_dump($a);
//                var_dump(count($a).'  '.getPage($now_page-1, 10000));

                $update = [];
                foreach ($a as $k => $v) {
                    ++$count;
                    $data = $update[$v['accessory_order_id']];
                    if ($v['action_time'] && isset($key_value[$v['type']])) {
                        $field_name = $key_value[$v['type']];
                        if (isset($data[$field_name]) && $data[$field_name] >  $v['action_time']) {
                            continue;
                        }
                        $update[$v['accessory_order_id']][$key_value[$v['type']]] = $v['action_time'];
                    }
                    unset($a[$k]);
                }

                foreach ($update as $k => $v) {
                    M()->startTrans();
                    $model->update($k, $v);
                    M()->commit();
                }

            }  while ($count);

            M()->startTrans();
            $update_sql_01 = "update worker_order_apply_accessory set complete_time = worker_receive_time where complete_time = 0 and accessory_status = 9 and is_giveup_return != 0 and worker_receive_time != 0";
            M()->execute($update_sql_01);
            $update_sql_02 = "update worker_order_apply_accessory set complete_time = factory_confirm_receive_time where complete_time = 0 and accessory_status = 9 and is_giveup_return = 0 and factory_confirm_receive_time != 0";
            M()->execute($update_sql_02);
            $update_sql_03 = "update worker_order_apply_accessory set factory_confirm_receive_time = complete_time where factory_confirm_receive_time = 0 and accessory_status = 9 and is_giveup_return = 0 and complete_time != 0";
//            M()->execute($update_sql_03);
            M()->commit();
//            M()->execute("ALTER TABLE `worker_order_apply_accessory` drop `cp_extend_time`;");
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function workerOrderApplyCostActionTime()
    {
        try {
            set_time_limit(0);
            $field_time = [
                'SUBSTRING_INDEX(group_concat(create_time order by create_time desc), ",", 1) as action_time',
                'type',
                'worker_order_apply_cost_id',
            ];
            $type = [
                CostRecordService::TYPE_CS_CHECKED,                     // 客服审核通过
                CostRecordService::TYPE_CS_FORBIDDEN,                   // 客服审核不通过

                CostRecordService::TYPE_CS_ACT_FACTORY_CHECKED,         // 客服代厂家审核通过
                CostRecordService::TYPE_CS_ACT_FACTORY_FORBIDDEN,       // 客服代厂家审核不通过
                CostRecordService::TYPE_FACTORY_CHECKED,                // 厂家审核通过
                CostRecordService::TYPE_FACTORY_FORBIDDEN,              // 厂家审核不通过
                CostRecordService::TYPE_FACTORY_ADMIN_CHECKED,
                CostRecordService::TYPE_FACTORY_ADMIN_FORBIDDEN,
            ];

            $key_value = [
                CostRecordService::TYPE_CS_CHECKED                  => 'admin_check_time',      //
                CostRecordService::TYPE_CS_FORBIDDEN                => 'admin_check_time',      //

                CostRecordService::TYPE_CS_ACT_FACTORY_CHECKED      => 'factory_check_time',    //
                CostRecordService::TYPE_CS_ACT_FACTORY_FORBIDDEN    => 'factory_check_time',    //
                CostRecordService::TYPE_FACTORY_CHECKED             => 'factory_check_time',    //
                CostRecordService::TYPE_FACTORY_FORBIDDEN           => 'factory_check_time',    //
                CostRecordService::TYPE_FACTORY_ADMIN_CHECKED       => 'factory_check_time',
                CostRecordService::TYPE_FACTORY_ADMIN_FORBIDDEN     => 'factory_check_time',
            ];

            $sql = "select  ".implode(',', $field_time)." from worker_order_apply_cost_record where `type` in (".implode(',', $type).") group by `type`,worker_order_apply_cost_id";
            $a = M()->query($sql);

            $update = [];
            foreach ($a as $k => $v) {
                $data = $update[$v['worker_order_apply_cost_id']];
                if ($v['action_time'] && isset($key_value[$v['type']])) {
                    $field_name = $key_value[$v['type']];
                    if (isset($data[$field_name]) && $data[$field_name] >  $v['action_time']) {
                        continue;
                    }
                    $update[$v['worker_order_apply_cost_id']][$key_value[$v['type']]] = $v['action_time'];
                }
                unset($a[$k]);
            }
            $model = BaseModel::getInstance('worker_order_apply_cost');
            foreach ($update as $k => $v) {
                M()->startTrans();
                $model->update($k, $v);
                M()->commit();
            }

            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}
