<?php
/**
 * File: WorkbenchListener.class.php
 * Function:
 * User: sakura
 * Date: 2018/3/29
 */

namespace Admin\Repositories\Listeners;

use Admin\Logic\WorkbenchLogic;
use Admin\Model\BaseModel;
use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;
use Common\Common\Service\OrderOperationRecordService;

class WorkbenchListener implements ListenerInterface
{

    public function handle(EventAbstract $event)
    {
        try {
            $event_type = $event->data['event_type'];
            $worker_order_id = $event->data['worker_order_id'];
            $is_transfer = $event->data['operation_type'] === OrderOperationRecordService::CS_TRANSFER_ORDER ? true : false;
            $receive_admin_id = 0;
            $is_transfer && $receive_admin_id = $event->data['receive_admin_id'][$worker_order_id] ?? 0;

            $worker_order = BaseModel::getInstance('worker_order')->getOneOrFail($worker_order_id, 'checker_id,distributor_id,auditor_id,returnee_id,return_time,audit_time,worker_repair_time');

            if (C('WORKBENCH_EVENT_TYPE.ADMIN_CHECKER_RECEIVE') == $event_type) {
                //核实接单
                WorkbenchLogic::incStatsDay($worker_order['checker_id'], C('WORKBENCH_REDIS_KEY.ADMIN_CHECKER_RECEIVE_DAY'));
                $receive_admin_id && WorkbenchLogic::decStatsDay($receive_admin_id, C('WORKBENCH_REDIS_KEY.ADMIN_CHECKER_RECEIVE_DAY'));
            } elseif (C('WORKBENCH_EVENT_TYPE.ADMIN_CHECKER_CHECK') == $event_type) {
                //核实客服核实
                WorkbenchLogic::incStatsDay($worker_order['checker_id'], C('WORKBENCH_REDIS_KEY.ADMIN_CHECKER_CHECK_DAY'));
                WorkbenchLogic::incStatsMonth($worker_order['checker_id'], C('WORKBENCH_REDIS_KEY.ADMIN_CHECKER_CHECK_MONTH'));
            } elseif (C('WORKBENCH_EVENT_TYPE.ADMIN_DISTRIBUTOR_RECEIVE') == $event_type) {
                //派单客服接单
                WorkbenchLogic::incStatsDay($worker_order['distributor_id'], C('WORKBENCH_REDIS_KEY.ADMIN_DISTRIBUTOR_RECEIVE_DAY'));
                $receive_admin_id && WorkbenchLogic::decStatsDay($receive_admin_id, C('WORKBENCH_REDIS_KEY.ADMIN_DISTRIBUTOR_RECEIVE_DAY'));
            } elseif (C('WORKBENCH_EVENT_TYPE.ADMIN_DISTRIBUTOR_DISTRIBUTE') == $event_type) {
                //派单客服派发
                WorkbenchLogic::incStatsDay($worker_order['distributor_id'], C('WORKBENCH_REDIS_KEY.ADMIN_DISTRIBUTOR_DISTRIBUTE_DAY'));
            } elseif (C('WORKBENCH_EVENT_TYPE.WORKER_FINISH') == $event_type) {
                //技工完成服务
                WorkbenchLogic::incStatsDay($worker_order['distributor_id'], C('WORKBENCH_REDIS_KEY.ADMIN_DISTRIBUTOR_FINISH_DAY'));
                WorkbenchLogic::incStatsMonth($worker_order['distributor_id'], C('WORKBENCH_REDIS_KEY.ADMIN_DISTRIBUTOR_FINISH_MONTH'));
            } elseif (C('WORKBENCH_EVENT_TYPE.ADMIN_RETURNEE_RETURN') == $event_type) {
                //回访退回
                list($year, $month, $day) = explode('|', date('Y|m|d', $worker_order['worker_repair_time']));
                list($year_cur, $month_cur, $day_cur) = explode('|', date('Y|m|d'));
                WorkbenchLogic::incStatsDay($worker_order['distributor_id'], C('WORKBENCH_REDIS_KEY.ADMIN_RETURNEE_RETURN_DAY'));
                //同一天执行减法
                if ($year == $year_cur && $month == $month_cur && $day == $day_cur) {
                    WorkbenchLogic::decStatsDay($worker_order['distributor_id'], C('WORKBENCH_REDIS_KEY.ADMIN_DISTRIBUTOR_FINISH_DAY'));
                }
                //同一个月执行减法
                if ($year == $year_cur && $month == $month_cur) {
                    WorkbenchLogic::decStatsMonth($worker_order['distributor_id'], C('WORKBENCH_REDIS_KEY.ADMIN_DISTRIBUTOR_FINISH_MONTH'));
                }
            } elseif (C('WORKBENCH_EVENT_TYPE.ADMIN_RETURNEE_RECEIVE') == $event_type) {
                //回访接单
                WorkbenchLogic::incStatsDay($worker_order['returnee_id'], C('WORKBENCH_REDIS_KEY.ADMIN_RETURNEE_RECEIVE_DAY'));
                $receive_admin_id && WorkbenchLogic::decStatsDay($receive_admin_id, C('WORKBENCH_REDIS_KEY.ADMIN_RETURNEE_RECEIVE_DAY'));
            } elseif (C('WORKBENCH_EVENT_TYPE.ADMIN_RETURNEE_FINISH') == $event_type) {
                //回访审核
                WorkbenchLogic::incStatsDay($worker_order['returnee_id'], C('WORKBENCH_REDIS_KEY.ADMIN_RETURNEE_FINISH_DAY'));
                WorkbenchLogic::incStatsMonth($worker_order['returnee_id'], C('WORKBENCH_REDIS_KEY.ADMIN_RETURNEE_FINISH_MONTH'));
            } elseif (C('WORKBENCH_EVENT_TYPE.ADMIN_AUDITOR_RETURN') == $event_type) {
                //财务退回
                list($year, $month, $day) = explode('|', date('Y|m|d', $worker_order['return_time']));
                list($year_cur, $month_cur, $day_cur) = explode('|', date('Y|m|d'));
                WorkbenchLogic::incStatsDay($worker_order['returnee_id'], C('WORKBENCH_REDIS_KEY.ADMIN_AUDITOR_RETURN_DAY'));
                if ($year == $year_cur && $month == $month_cur && $day == $day_cur) {
                    WorkbenchLogic::decStatsDay($worker_order['returnee_id'], C('WORKBENCH_REDIS_KEY.ADMIN_RETURNEE_FINISH_DAY'));
                }
                if ($year == $year_cur && $month == $month_cur) {
                    WorkbenchLogic::decStatsMonth($worker_order['returnee_id'], C('WORKBENCH_REDIS_KEY.ADMIN_RETURNEE_FINISH_MONTH'));
                }
            } elseif (C('WORKBENCH_EVENT_TYPE.ADMIN_AUDITOR_RECEIVE') == $event_type) {
                //财务接单
                WorkbenchLogic::incStatsDay($worker_order['auditor_id'], C('WORKBENCH_REDIS_KEY.ADMIN_AUDITOR_RECEIVE_DAY'));
                $receive_admin_id && WorkbenchLogic::decStatsDay($receive_admin_id, C('WORKBENCH_REDIS_KEY.ADMIN_AUDITOR_RECEIVE_DAY'));
            } elseif (C('WORKBENCH_EVENT_TYPE.ADMIN_AUDITOR_AUDIT') == $event_type) {
                //财务审核
                WorkbenchLogic::incStatsDay($worker_order['auditor_id'], C('WORKBENCH_REDIS_KEY.ADMIN_AUDITOR_AUDIT_DAY'));
                WorkbenchLogic::incStatsMonth($worker_order['auditor_id'], C('WORKBENCH_REDIS_KEY.ADMIN_AUDITOR_AUDIT_MONTH'));
            } elseif (C('WORKBENCH_EVENT_TYPE.FACTORY_AUDITOR_NOT_PASS') == $event_type) {
                //厂家财务审核不通过
                list($year, $month, $day) = explode('|', date('Y|m|d', $worker_order['audit_time']));
                list($year_cur, $month_cur, $day_cur) = explode('|', date('Y|m|d'));
                WorkbenchLogic::incStatsDay($worker_order['auditor_id'], C('WORKBENCH_REDIS_KEY.FACTORY_AUDITOR_NOT_PASS_DAY'));
                if ($year == $year_cur && $month == $month_cur && $day == $day_cur) {
                    WorkbenchLogic::decStatsDay($worker_order['auditor_id'], C('WORKBENCH_REDIS_KEY.ADMIN_AUDITOR_AUDIT_DAY'));
                }
                if ($year == $year_cur && $month == $month_cur) {
                    WorkbenchLogic::decStatsMonth($worker_order['auditor_id'], C('WORKBENCH_REDIS_KEY.ADMIN_AUDITOR_AUDIT_MONTH'));
                }
            }

        } catch (\Exception $e) {

        }
    }

}