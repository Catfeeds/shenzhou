<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/3/29
 * Time: 10:13
 */

namespace Common\Common\Service\PayPlatformService;


use Common\Common\Service\PayPlatformService\PingPayConfig\KeyConfigService;

class PingPayService
{
    private static $payresult;

    public function getPlatformOrderNo()
    {
        return '';
    }

    public function getOrderStatus($pay_status = '')
    {
        return $pay_status;
    }

    public function getSystemPayMent($pay_ment = '')
    {
        $arr = array_flip(KeyConfigService::PLATFORM_CHANNEL_TO_PAYMENT_KEY_VALUE);
        $channel_type = $arr[$pay_ment];
        return KeyConfigService::PLATFORM_CHANNEL_TO_SYSTEM_PAYMENT_KEY_VALUE[$channel_type] ?? PayService::PAYMENT_UNIONPAY;

    }

    public function xmlDecrypt($response_text = '')
    {

    }

}
