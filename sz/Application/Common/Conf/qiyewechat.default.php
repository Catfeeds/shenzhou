<?php

return [
    'qiyewechat'                                       => [
        'appid'          => 'wx88ad996d641a6ad7',
        'secrect'        => '6x2VpwpOSsutE7ztAt2jWOHchIopG6hEFU874NPHec8', //支持jssdk授权的secrect
        'token'          => 'BiEFvEyfCHklPK',
        'encodingaeskey' => 'hjxRbqIu031Jb8nrTNQkGcrjwJ2r1Sb1CQ1JPe4HnVR',
    ],

    //企业号授权重定向 验证签名密钥
    //支持登录授权的secret
    'login_secrect'                                    => 'TE3ofTGM1df_XnL0nlehoftNnd41pOBjPoWJzc2fNMM',

    //SEND_NEWS_*配置值 对应 application_secret数组下标,而且对应企业微信 应用id
    'SEND_NEWS_MESSAGE_APPLICATION_ID'                 => 0,   //企业小助手
    'SEND_NEWS_MESSAGE_APPLICATION_PERSONAL_CENTER'    => 6,   //个人中心
    'SEND_NEWS_MESSAGE_APPLICATION_BY_MY_WORKER_ORDER' => 7,   //我的工单
    'SEND_NEWS_MESSAGE_APPLICATION_BY_ABOUT_SHENZHOU'  => 8,   //关于神舟联保
    'SEND_NEWS_MESSAGE_APPLICATION_BY_MY_WALLET'       => 9,   //我的钱包

    //企业号应用密钥
    'application_secret'                               => [
        '6x2VpwpOSsutE7ztAt2jWOHchIopG6hEFU874NPHec8', //企业小助手
        '',
        '',
        '',
        '',
        '',
        'HzUQsbbr2FnV4zDlMH7dqMMasGVFln-Ckgr5QpFDwPQ', //个人中心
        'TE3ofTGM1df_XnL0nlehoftNnd41pOBjPoWJzc2fNMM', //我的工单
        'PMzn3LuwDqRrn1NEvECox-kb9zC5JaOrLUNuHZIXros', //关于神舟联保
        'rU_FGOd84KGLHzbxMwB6Ua_u70VOL87t3fkUOoNxfPE', //我的钱包
    ],

    //通知落地页地址
    'application_url'                                  => [
        'worker_order_base_url' => '/app/order-details/',
        'accessory_base_url'    => '/app/part-details/',
        'cost_order_base_url'   => '/app/deal-flow-status/',
        'feedback_base_url'     => '/app/return-details/',
        'order_base_url'        => '/app/order-list/',
    ],

    //网页授权重定向域名
    'qiyewechat_host'                                  => 'http://qyh.szlb.cc',

    //前端入口链接
    'qy_base_path'                                     => '/index.html#',

    'WORKER_DEPARTMENT_ROOT_ID'      => 12,
    'WORKER_CHECKED_DEPARTMENT_ID'   => 2,
    'WORKER_UNCHECKED_DEPARTMENT_ID' => 4,

    'USER_CENTER_APPLICATION_ID'    => 6,
    'WORKER_ORDER_APPLICATION_ID'   => 7,
    'ABOUT_SHENZHOU_APPLICATION_ID' => 8,
    'WALLET_APPLICATION_ID'         => 5,

    'CALLBACK_TOKEN'   => 'BiEFvEyfCHklPK',
    'ENCODING_AES_KEY' => 'hjxRbqIu031Jb8nrTNQkGcrjwJ2r1Sb1CQ1JPe4HnVR',

    'WORKER_EXPORT_SAMPLE_FILE' => APP_PATH . '../data/batch_user_sample.csv',

];


