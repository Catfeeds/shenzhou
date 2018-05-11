<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/3/29
 * Time: 10:20
 */

namespace Common\Common\Service\PayPlatformService\PingPayConfig;


use Common\Common\Service\PayService;

class KeyConfigService
{

    const CHANNEL_TYPE_ALIPAY           = 'alipay';             // 支付宝App支付
    const CHANNEL_TYPE_ALIPAY_WAP       = 'alipay_wap';         // 支付宝手机网站支付
    const CHANNEL_TYPE_ALIPAY_QR        = 'alipay_qr';          // 支付宝当面付
    const CHANNEL_TYPE_ALIPAY_SCAN      = 'alipay_scan';        // 支付宝条码支付
    const CHANNEL_TYPE_ALIPAY_PC_DIRECT = 'alipay_pc_direct';   // 支付宝电脑网站支付
    const CHANNEL_TYPE_WX               = 'wx';                 // 微信App支付
    const CHANNEL_TYPE_WX_PUB           = 'wx_pub';             // 微信公众号支付
    const CHANNEL_TYPE_WX_WAP           = 'wx_wap';             // 微信H5支付
    const CHANNEL_TYPE_WX_LITE          = 'wx_lite';            // 微信小程序支付
    const CHANNEL_TYPE_WX_PUB_QR        = 'wx_pub_qr';          // 微信公众号扫码支付
    const CHANNEL_TYPE_WX_PUB_SCAN      = 'wx_pub_scan';        // 微信公众号刷卡支付
    const CHANNEL_TYPE_QPAY             = 'qpay';               // QQ钱包App支付
    const CHANNEL_TYPE_QPAY_PUB         = 'qpay_pub';           // QQ钱包公众号支付
    const CHANNEL_TYPE_UPACP            = 'upacp';              // 银联支付;即银联 App 支付（2015 年 1 月 1 日后的银联新商户使用。若有疑问，请与 Ping++ 或者相关的收单行联系）
    const CHANNEL_TYPE_UPACP_PC         = 'upacp_pc';           // 银联网关支付;即银联 PC 网页支付
    const CHANNEL_TYPE_UPACP_WAP        = 'upacp_wap';          // 银联手机网页支;（2015 年 1 月 1 日后的银联新商户使用。若有疑问，请与 Ping++ 或者相关的收单行联系）
    const CHANNEL_TYPE_CP_B2B           = 'cp_b2b';             // 银联企业网银支付;即 B2B 银联 PC 网页支付
    const CHANNEL_TYPE_APPLEPAY_UPACP   = 'applepay_upacp';     // ApplePay
    const CHANNEL_TYPE_BFB              = 'bfb';                // 百度钱包移动快捷支付;即百度钱包 App 支付
    const CHANNEL_TYPE_BFB_WAP          = 'bfb_wap';            // 百度钱包手机网页支付
    const CHANNEL_TYPE_JDPAY_WAP        = 'jdpay_wap';          // 京东手机网页支付
    const CHANNEL_TYPE_YEEPAY_WAP       = 'yeepay_wap';         // 易宝手机网页支付
    const CHANNEL_TYPE_CMB_WALLET       = 'cmb_wallet';         // 招行一网通
    const CHANNEL_TYPE_ISV_QR           = 'isv_qr';             // 线下扫码;（主扫）
    const CHANNEL_TYPE_ISV_SACN         = 'isv_scan';           // 线下扫码;（被扫）
    const CHANNEL_TYPE_ISV_WAP          = 'isv_wap';            // 线下扫码;（固定码）
    const CHANNEL_TYPE_BALANCE          = 'balance';            // 余额
    const CHANNEL_TYPE_NAME_KEY_VALUE = [
        self::CHANNEL_TYPE_ALIPAY           => '支付宝App支付',
        self::CHANNEL_TYPE_ALIPAY_WAP       => '支付宝手机网站支付',
        self::CHANNEL_TYPE_ALIPAY_QR        => '支付宝当面付',
        self::CHANNEL_TYPE_ALIPAY_SCAN      => '支付宝条码支付',
        self::CHANNEL_TYPE_ALIPAY_PC_DIRECT => '支付宝电脑网站支付',
        self::CHANNEL_TYPE_WX               => '微信App支付',
        self::CHANNEL_TYPE_WX_PUB           => '微信公众号支付',
        self::CHANNEL_TYPE_WX_WAP           => '微信H5支付',
        self::CHANNEL_TYPE_WX_LITE          => '微信小程序支付',
        self::CHANNEL_TYPE_WX_PUB_QR        => '微信公众号扫码支付',
        self::CHANNEL_TYPE_WX_PUB_SCAN      => '微信公众号刷卡支付',
        self::CHANNEL_TYPE_QPAY             => 'QQ钱包App支付',
        self::CHANNEL_TYPE_QPAY_PUB         => 'QQ钱包公众号支付',
        self::CHANNEL_TYPE_UPACP            => '银联支付',
        self::CHANNEL_TYPE_UPACP_PC         => '银联网关支付',
        self::CHANNEL_TYPE_UPACP_WAP        => '银联手机网页支',
        self::CHANNEL_TYPE_CP_B2B           => '银联企业网银支付',
        self::CHANNEL_TYPE_APPLEPAY_UPACP   => 'ApplePay',
        self::CHANNEL_TYPE_BFB              => '百度钱包移动快捷支付',
        self::CHANNEL_TYPE_BFB_WAP          => '百度钱包手机网页支付',
        self::CHANNEL_TYPE_JDPAY_WAP        => '京东手机网页支付',
        self::CHANNEL_TYPE_YEEPAY_WAP       => '易宝手机网页支付',
        self::CHANNEL_TYPE_CMB_WALLET       => '招行一网通',
        self::CHANNEL_TYPE_ISV_QR           => '线下扫码',
        self::CHANNEL_TYPE_ISV_SACN         => '线下扫码',
        self::CHANNEL_TYPE_ISV_WAP          => '线下扫码',
        self::CHANNEL_TYPE_BALANCE          => '余额',
    ];
    const PLATFORM_CHANNEL_TO_PAYMENT_KEY_VALUE = [
        self::CHANNEL_TYPE_ALIPAY_PC_DIRECT => 1,
        self::CHANNEL_TYPE_WX_PUB_QR        => 2,
        self::CHANNEL_TYPE_UPACP_PC         => 3,
        self::CHANNEL_TYPE_ALIPAY           => 4,
        self::CHANNEL_TYPE_ALIPAY_WAP       => 5,
        self::CHANNEL_TYPE_ALIPAY_QR        => 6,
        self::CHANNEL_TYPE_ALIPAY_SCAN      => 7,
        self::CHANNEL_TYPE_WX               => 8,
        self::CHANNEL_TYPE_WX_PUB           => 9,
        self::CHANNEL_TYPE_WX_WAP           => 10,
        self::CHANNEL_TYPE_WX_LITE          => 11,
        self::CHANNEL_TYPE_WX_PUB_SCAN      => 12,
        self::CHANNEL_TYPE_QPAY             => 13,
        self::CHANNEL_TYPE_QPAY_PUB         => 14,
        self::CHANNEL_TYPE_UPACP            => 15,
        self::CHANNEL_TYPE_UPACP_WAP        => 16,
        self::CHANNEL_TYPE_CP_B2B           => 17,
        self::CHANNEL_TYPE_APPLEPAY_UPACP   => 18,
        self::CHANNEL_TYPE_BFB              => 19,
        self::CHANNEL_TYPE_BFB_WAP          => 20,
        self::CHANNEL_TYPE_JDPAY_WAP        => 21,
        self::CHANNEL_TYPE_YEEPAY_WAP       => 22,
        self::CHANNEL_TYPE_CMB_WALLET       => 23,
        self::CHANNEL_TYPE_ISV_QR           => 24,
        self::CHANNEL_TYPE_ISV_SACN         => 25,
        self::CHANNEL_TYPE_ISV_WAP          => 26,
        self::CHANNEL_TYPE_BALANCE          => 27,
    ];
    // 暂时只用到三种支付
    const PLATFORM_CHANNEL_TO_SYSTEM_PAYMENT_KEY_VALUE = [
        self::CHANNEL_TYPE_ALIPAY_PC_DIRECT => PayService::PAYMENT_ALIPAY,      // 支付宝支付
        self::CHANNEL_TYPE_WX_PUB_QR        => PayService::PAYMENT_WXPAY,       // 微信支付
        self::CHANNEL_TYPE_UPACP_PC         => PayService::PAYMENT_UNIONPAY,    // 银联支付
    ];
}
