<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/4/11
 * Time: 10:38
 */

namespace Common\Common\Service;


class WorkerOrderOutWorkerAddFeeService
{
    const IS_PAY_NO  = '0'; // 未支付
    const IS_PAY_YES = '1'; // 已支付


    const PAY_TYPE_NO                       = '0';                            // 未支付
    const PAY_TYPE_WORKER_WX                = '1';                     // 技工代微信用户支付通道支付
    const PAY_TYPE_USER_CASH                = '2';                     // 客服确认用户现金支付
    const PAY_TYPE_WOKRER_COMFIRM_USER_CASH = '3';      // 技工确认用户现金支付
    const PAY_TYPE_USER_WX                  = '4';                       // 微信用户支付通道支付

    // 平台可以获得限制支付的资金的支付类型
    const PAY_TYPE_PLATFORM_GET_MONEY_LIST
        = [
            self::PAY_TYPE_WORKER_WX,
            self::PAY_TYPE_USER_WX,
        ];

    // 现金支付类型
    const PAY_TYPE_CASH_PAY_LIST
        = [
            self::PAY_TYPE_USER_CASH,
            self::PAY_TYPE_WOKRER_COMFIRM_USER_CASH,
        ];

    // 已支付类型
    const PAY_TYPE_IS_PAY_LIST
        = [
            self::PAY_TYPE_WORKER_WX,
            self::PAY_TYPE_USER_CASH,
            self::PAY_TYPE_WOKRER_COMFIRM_USER_CASH,
            self::PAY_TYPE_USER_WX,
        ];

    const IS_ADD_FEE_NO  = '0';      // 不是加收费用
    const IS_ADD_FEE_YES = '1';     // 是加收费用

    const PAY_TYPE_ONLINE_VALID_ARRAY
        = [
            self::PAY_TYPE_WORKER_WX,
            self::PAY_TYPE_USER_WX,
        ];


    public static function getPayType($pay_type)
    {
        switch ($pay_type) {
            case self::PAY_TYPE_NO:
                return '未支付';
            case self::PAY_TYPE_WORKER_WX:
                return '技工代微信用户支付通道支付';
            case self::PAY_TYPE_USER_CASH:
                return '客服确认用户现金支付';
            case self::PAY_TYPE_WOKRER_COMFIRM_USER_CASH:
                return '技工确认用户现金支付';
            case self::PAY_TYPE_USER_WX:
                return '微信用户支付通道支付';

        }

        return '';
    }

}
