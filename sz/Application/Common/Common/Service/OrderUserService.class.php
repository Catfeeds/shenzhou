<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/12/25
 * Time: 16:59
 */

namespace Common\Common\Service;

class OrderUserService
{
    // 保外单支付类型
    const PAY_TYPE_NULL = '0';          // 默认值，暂无支付
    const PAY_TYPE_WX = '1';            // 微信支付
    const PAY_TYPE_CASH = '2';          // 现金支付

    const PAY_TYPE_NAME_KEY_VALUE = [
        self::PAY_TYPE_WX => '微信支付',
        self::PAY_TYPE_CASH => '代用户在线支付',
    ];

    const PAY_TYPE_LIST = [
        self::PAY_TYPE_WX,
        self::PAY_TYPE_CASH,
    ];

    // 保外单用户支付状态
    const IS_USER_PAY_DEFAULT = '0';    // 未支付
    const IS_USER_PAY_SUCCESS = '1';    // 已支付
    const IS_USER_PAY_HAD_PAY = '2';    // 存在加收费用未支付

    const PAY_TYPE_IS_WX_PAY_LIST = [
        self::PAY_TYPE_WX,
    ];

    // worker_order_user_info.pay_type 能做的操作
    const PAY_TYPE_CATEGORY_KEY_VALUE = [
        self::PAY_TYPE_WX => [
            WorkerOrderOutWorkerAddFeeService::PAY_TYPE_USER_WX,
        ],
        self::PAY_TYPE_CASH => [
            WorkerOrderOutWorkerAddFeeService::PAY_TYPE_WORKER_WX,
            WorkerOrderOutWorkerAddFeeService::PAY_TYPE_USER_CASH,
            WorkerOrderOutWorkerAddFeeService::PAY_TYPE_USER_WX,
        ],
    ];

}