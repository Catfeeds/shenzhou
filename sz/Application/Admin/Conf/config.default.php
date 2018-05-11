<?php
return [

    'LOAD_EXT_CONFIG' => 'routers,worker_score,admin_backend_routing,draw,kefu_kpi,system_receive_order',

    'TOKEN_CRYPT_CODE' => 'ShenZhou_token',

    'URL_ROUTER_ON' => true,    // 开启路由

    'ORDER_IMPORT_MAX_NUM' => 1000,

    'SEND_COUPON_IMPORT_MAX_NUM' => 500,

    'MIN_HASH_LENGTH' => 6,

    'BACKEND_TOKEN_HASH_KEY' => 'shenzhou@2017',

    'YIMA_MIN_CODE' => 10000001,

    'CHECK_PWD_CODE' => 50000101,

    'ORDER_STATISTIC_PERMISSION_USER' => [
        97,
        116,
        175,
        54,
    ],

    'SHOW_WORKER_PHONE_FACTORY_IDS' => [
        927,
        1002,
        564,
        418,
        195,
        1331,
        1351,
        1391,
        1508,
    ],

    'ORDER_MESSAGE_THUMB' => [
        'FACTORY' => '/Public/images/thumb/factory_photo.png',
        'ADMIN'   => '/Public/images/thumb/cs_photo.png',
    ],

    'MASTER_SCAN_URL' => 'http://api.szlb.cc/index.php/workercode/',

    'MIN_HASH_LENGTH' => 6,

    'YIMA_DETAIL_ROUTE' => '/index.php/api/yima/detail/',


    'PINGPP' => [
        'APP_KEY'                 => 'sk_test_10OGyHyj5uD4rLabv9rPyvr9',
        'APP_RSA_PRI_KEY_PATH'    => APP_PATH . '/Admin/Conf/app_pingpp_rsa_pri.pem',
        'APP_ID'                  => 'app_H0Gir55OerjDHqH8',
        'PINGPP_RSA_PUB_KEY_PATH' => APP_PATH . '/Admin/Conf/pingpp_rsa_pub.pem',
    ],

    'WEBCALL' => [
        'SALT'    => 'shenZhou_webcall', //电信云加密盐
        'ACCOUNT' => 'N00000000628',
        'SECRET'  => 'ae40e076-08ce-434e-a9c5-3d1350caaa58',
    ],

    'GROUP_NO_START' => 122331,   // 初始默认群号

    'ORDER_CAN_REWORK_TIME' => 30 * 86400,
];