<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/4/24
 * Time: 17:24
 */

return [

    //财务客服自动接单
    'AUTO_RECEIVE_AUDITOR_ORDER' => [
        'ADMIN_LIST_KEY_TPL'      => 'autoReceive:SystemReceiveOrder:%s:auditorAdmin', //财务客服列表,填充工作日[0,6]
        'ADMIN_LIST_REST_KEY_TPL' => 'autoReceive:SystemReceiveOrder:%s:auditorRestAdmin', //财务客服列表,填充工作日[0,6]
        'ADMIN_OFFSET'            => 'autoReceive:SystemReceiveOrder:%s:auditorAdminOffset', //财务客服偏移量 [0,6]
    ],

    //工单客服自动接单
    'AUTO_RECEIVE_WORKER_ORDER'  => [
        'ADMIN_LIST_CHECKER_KEY_TPL'     => 'autoReceive:SystemReceiveOrder:%s:checkAdmin', //核实客服,填充工作日[0,6]
        'ADMIN_LIST_DISTRIBUTOR_KEY_TPL' => 'autoReceive:SystemReceiveOrder:%s:distributeAdmin', //派单客服,填充工作日[0,6]
        'ADMIN_LIST_RETURNEE_KEY_TPL'    => 'autoReceive:SystemReceiveOrder:%s:returnAdmin', //回访客服,填充工作日[0,6]
        'ADMIN_LIST_FACTORY_KEY_TPL'     => 'autoReceive:SystemReceiveOrder:%s:factoryAdmin', //对接厂家,填充工作日[0,6]
        'ADMIN_LIST_FACTORY_GROUP_KEY_TPL'     => 'autoReceive:SystemReceiveOrder:%s:factoryGroupAdmin', //对接厂家组别,填充工作日[0,6]
        'ADMIN_LIST_CATEGORY_KEY_TPL'    => 'autoReceive:SystemReceiveOrder:%s:categoryAdmin', //对接品类,填充工作日[0,6]
        'ADMIN_LIST_AREA_KEY_TPL'        => 'autoReceive:SystemReceiveOrder:%s:areaAdmin', //对接地区,填充工作日[0,6]
    ],

    //客服表
    'AUTO_RECEIVE_ADMIN'         => [
        'LIST'          => 'autoReceive:SystemReceiveOrder:%s:admin', //列表,填充工作日[0,6]
        'FIELD_MAX'     => '%s:maxReceiveTimes', // 最大接单量,填充客服id
        'FIELD_CUR'     => '%s:curReceiveTimes', // 当前接单量,填充客服id
        'FIELD_WORKDAY' => '%s:workday', // 可接单日,逗号隔开,填充客服id
        'STATUS'        => 'autoReceive:SystemReceiveOrder:%s:adminStatus', // 工作日
    ],

    //没有合适接单人通知
    'AUTO_RECEIVE_NOBODY_NOTICE' => [
        'AUDITOR'     => 'autoReceive:SystemReceiveOrder:notice:auditor', // 财务
        'CHECKER'     => 'autoReceive:SystemReceiveOrder:notice:checker', // 核实
        'DISTRIBUTOR' => 'autoReceive:SystemReceiveOrder:notice:distributor', // 派单
        'RETURNEE'    => 'autoReceive:SystemReceiveOrder:notice:returnee', //回访
    ],

    //工单客服接单分类
    'AUTO_RECEIVE_ROLE_TYPE'     => [
        'CHECKER'     => 1, // 核实
        'DISTRIBUTOR' => 2, // 派单
        'RETURNEE'    => 3, // 回访
    ],

    //默认接单客服
    'AUTO_RECEIVE_DEFAULT_ADMIN' => [
        'AUDITOR'      => 243,
        'WORKER_ORDER' => 65,
    ],

    //自动接单队列名
    'AUTO_RECEIVE_QUEUE'         => 'autoReceive',

    'LOCK' => [
        'TPL' => 'autoReceive:SystemReceiveOrder:%s',
    ],

    'AUTO_RECEIVE_NO_DATA' => -1,

    'AUTO_RECEIVE_INIT' => [
        'REGISTER' => 'autoReceive:SystemReceiveOrder:admins:%s:init', // 初始化标记,填充工作日[0,6]
    ],

];
