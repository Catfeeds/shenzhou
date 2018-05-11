<?php
return array(
   'outFactoryId' => 839,

    'PRODUCT_IMAGES_DEFAULT' => '/Public/default_ban.png',

    // 默认未预约订单丢失时间
    'dropOrderTime' => 10800,
    
    // 签到提前时间
    'appointTimeExtend' => 3600,
    
    // 验证码有效时间(分钟)
    'effectiveTime' => 5,
    
    // 签到最小距离
    'signInDistance' => 2000,

    // B端后台 厂家申请易码记录 需要发送短信至指定手机号码通知
    'FACTORY_EXCEL_APPLY_SMS_PHONE' => 18818461566,

    // 下单默认冻结金额（厂家未设置默认冻结金额时才使用）
//    'ORDER_DEFAULT_FROZEN_MONEY' => 50,
    'ORDER_DEFAULT_FROZEN_MONEY' => 0,

    // 保内服务费
    'ORDER_INSURED_SERVICE_FEE' => 10,
);