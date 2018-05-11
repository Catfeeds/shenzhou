<?php
return [

    'WORKBENCH_REDIS_KEY' => [
        'ADMIN_CHECKER_RECEIVE_DAY' => 'workbench:%s:admin_checker_receive_day', // 当天核实接单量,日期(ynj)填充
        'ADMIN_CHECKER_CHECK_DAY'   => 'workbench:%s:admin_checker_check_day', // 当天核实客服核实单数,日期(ynj)填充
        'ADMIN_CHECKER_CHECK_MONTH' => 'workbench:%s:admin_checker_receive_month', // 当月核实接单量,日期(ynj)填充

        'ADMIN_DISTRIBUTOR_RECEIVE_DAY'    => 'workbench:%s:admin_distributor_receive_day', // 当天派单接单量,日期(ynj)填充
        'ADMIN_DISTRIBUTOR_DISTRIBUTE_DAY' => 'workbench:%s:admin_distributor_distribute_day', //当天派单量,日期(ynj)填充
        'ADMIN_DISTRIBUTOR_FINISH_DAY'     => 'workbench:%s:admin_distributor_finish_day', // 当天已完成工单量,日期(ynj)填充
        'ADMIN_DISTRIBUTOR_FINISH_MONTH'   => 'workbench:%s:admin_distributor_finish_month',// 当月已完成工单量,日期(ynj)填充
        'ADMIN_RETURNEE_RETURN_DAY'        => 'workbench:%s:admin_returnee_return_day',    // 回访退回工单,日期(ynj)填充

        'ADMIN_RETURNEE_RECEIVE_DAY'  => 'workbench:%s:admin_returnee_receive_day', /// 当天回访接单量,日期(ynj)填充
        'ADMIN_RETURNEE_FINISH_DAY'   => 'workbench:%s:admin_returnee_finish_day', // 当天完成回访量,日期(ynj)填充
        'ADMIN_RETURNEE_FINISH_MONTH' => 'workbench:%s:admin_returnee_finish_month', // 当月完成回访量,日期(ynj)填充
        'ADMIN_AUDITOR_RETURN_DAY'    => 'workbench:%s:admin_auditor_return_day',    // 当天厂家财务不通过,日期(ynj)填充

        'ADMIN_AUDITOR_RECEIVE_DAY'    => 'workbench:%s:admin_auditor_receive_day',  // 当天财务接单量,日期(ynj)填充
        'ADMIN_AUDITOR_AUDIT_DAY'      => 'workbench:%s:admin_auditor_audit_day', // 当天财务审核量,日期(ynj)填充
        'ADMIN_AUDITOR_AUDIT_MONTH'    => 'workbench:%s:admin_auditor_audit_month', // 当月财务审核量,日期(ynj)填充
        'FACTORY_AUDITOR_NOT_PASS_DAY' => 'workbench:%s:factory_auditor_not_pass_day',// 厂家财务不通过,日期(ynj)填充
    ],

    'WORKBENCH_EVENT_TYPE' => [
        'ADMIN_CHECKER_RECEIVE'        => 1, // 核实接单
        'ADMIN_CHECKER_CHECK'          => 2, // 核实客服核实
        'ADMIN_DISTRIBUTOR_RECEIVE'    => 3, // 派单接单
        'ADMIN_DISTRIBUTOR_DISTRIBUTE' => 4, // 派单客服派发
        'WORKER_FINISH'                => 5, // 技工完成服务
        'ADMIN_RETURNEE_RETURN'        => 6, // 回访退回工单
        'ADMIN_RETURNEE_RECEIVE'       => 7, // 回访接单
        'ADMIN_RETURNEE_FINISH'        => 8, // 回访审核
        'ADMIN_AUDITOR_RETURN'         => 9, // 财务退回
        'ADMIN_AUDITOR_RECEIVE'        => 10,// 财务客服接单
        'ADMIN_AUDITOR_AUDIT'          => 11,// 财务审核
        'FACTORY_AUDITOR_NOT_PASS'     => 12,// 厂家财务不通过
    ],


];