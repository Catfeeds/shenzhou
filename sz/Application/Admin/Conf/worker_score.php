<?php

return [
    
    // 分数缓存配置地址前缀
    'S_KEY_PRE' => 'receiversOrderId',
    // 分数缓存配置前缀
    'S_SCORE_KEY_PRE' => 'orderWorkderSortStatistics',

    // 已完成工单量 的 s_key
//    'SKEY_COMPLETE_ORDER_NUMS' => 'completeOrderNumskey',
    'SKEY_COMPLETE_ORDER_NUMS' => 'workerReceivers:calculate:completeOrderScore',

    // 置顶分数
    'IS_TOP_SCORE' => 100000,
    // 按照目前算出来的分数*指数A  指数A=同服务类型产品完成量/10（如果指数＜1，则取同服务类型产品完成量/10；如果指数≥1，则按照1计算）
    'SAME_PRODUCT_ZHISHU_A' => 10,

    'CONTRACT_QUALIFICATION_A' => [
        2500,
        1500,
        1000,
        0,
    ],
    'CONTRACT_QUALIFICATION_B' => [
        1000,
        700,
        500,
        0,
    ],
    'DISTANCE_A' => [
        1000,
        800,
        600,
        400,
        200,
        0,
    ],
    'DISTANCE_B' => [
        2500,
        2000,
        1500,
        1000,
        500,
        0,
    ],
    'PRODUCT_MATCH' => [
        500,
        0,
    ],
    'PAID_ORDER' => [
        500,
        450,
        400,
        350,
        300,
        250,
        200,
        150,
        100,
        50,
        0,
    ],
    'SAME_PRODUCT' => [
        1500,
        1350,
        1200,
        1050,
        900,
        750,
        600,
        450,
        300,
        150,
    ],
    'NO_COMPLAINT' => [
        1000,
        900,
        800,
        700,
        600,
        500,
        400,
        300,
        200,
        100,
        0,
    ],
    'NO_CANCEL' => [
        1000,
        900,
        800,
        700,
        600,
        500,
        400,
        300,
        200,
        100,
        0,
    ],
    'ON_WORK_TIME' => 1000,
    'APPOINT_TIME' => 500,
    'RETURN_TIME' => 500,

    // 设计不合理 时间原因后续更改
    'WORKER_REPUTATION_CONFING_TIME' => [
        'work_appiont_lv1'          => [
            'score'         => 10,
            'score_field'   => 'appiont_fraction',
            'time_field'    => 'appiont_time',
        ],
        'work_appiont_lv2'          => [
            'score'         => 7,
            'score_field'   => 'appiont_fraction',
            'time_field'    => 'appiont_time',
        ],
        'work_appiont_lv3'          => [
            'score'         => 5,
            'score_field'   => 'appiont_fraction',
            'time_field'    => 'appiont_time',
        ],
        'work_appiont_lv4'          => [
            'score'         => 3,
            'score_field'   => 'appiont_fraction',
            'time_field'    => 'appiont_time',
        ],
        'work_arrive_lv1'           => [
            'score'         => 10,
            'score_field'   => 'arrive_fraction',
            'time_field'    => 'arrive_time',
        ],
        'work_arrive_lv2'           => [
            'score'         => 7,
            'score_field'   => 'arrive_fraction',
            'time_field'    => 'arrive_time',
        ],
        'work_arrive_lv3'           => [
            'score'         => 5,
            'score_field'   => 'arrive_fraction',
            'time_field'    => 'arrive_time',
        ],
        'work_arrive_lv4'           => [
            'score'         => 3,
            'score_field'   => 'arrive_fraction',
            'time_field'    => 'arrive_time',
        ],
        'work_ontime_lv1'           => [
            'score'         => 10,
            'score_field'   => 'ontime_fraction',
            'time_field'    => 'ontime_time',
        ],
        'work_ontime_lv2'           => [
            'score'         => 7,
            'score_field'   => 'ontime_fraction',
            'time_field'    => 'ontime_time',
        ],
        'work_ontime_lv3'           => [
            'score'         => 5,
            'score_field'   => 'ontime_fraction',
            'time_field'    => 'ontime_time',
        ],
        'work_ontime_lv4'           => [
            'score'         => 3,
            'score_field'   => 'ontime_fraction',
            'time_field'    => 'ontime_time',
        ],
        'work_return_lv1'           => [
            'score'         => 10,
            'score_field'   => 'return_fraction',
            'time_field'    => 'return_time',
        ],
        'work_return_lv2'           => [
            'score'         => 7,
            'score_field'   => 'return_fraction',
            'time_field'    => 'return_time',
        ],
        'work_return_lv3'           => [
            'score'         => 5,
            'score_field'   => 'return_fraction',
            'time_field'    => 'return_time',
        ],
        'work_return_lv4'           => [
            'score'         => 3,
            'score_field'   => 'return_fraction',
            'time_field'    => 'return_time',
        ],
    ],
];
