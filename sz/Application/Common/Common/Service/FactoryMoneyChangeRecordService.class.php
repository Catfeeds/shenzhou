<?php
/**
 * File: FactoryMoneyChangeRecordService.class.php
 * Function:
 * User: sakura
 * Date: 2017/12/4
 */

namespace Common\Common\Service;


class FactoryMoneyChangeRecordService
{

    //操作人帐号类型
    const OPERATOR_TYPE_SYSTEM        = '0'; // 系统主动调整
    const OPERATOR_TYPE_ADMIN         = '1'; // 平台客服
    const OPERATOR_TYPE_FACTORY       = '2'; // 厂家主账号
    const OPERATOR_TYPE_FACTORY_ADMIN = '3'; // 厂家子账号

    //变动类型
    const CHANGE_TYPE_FACTORY_UNIONPAY = '1';     // 银联在线支付
    const CHANGE_TYPE_FACTORY_ALIPAY   = '2';     // 支付宝支付
    const CHANGE_TYPE_FACTORY_WXPAY    = '3';     // 微信支付
    const CHANGE_TYPE_SYSTEM_SETTLE    = '4';     // 系统工单结算资金变动
    const CHANGE_TYPE_ADMIN_MANUAL     = '5';     // 手动调整
    const CHANGE_TYPE_ADMIN_SETTLE     = '6';     // 客服手动调整工单结算资金变动
    const CHANGE_TYPE_ADMIN_OTHER      = '7';     // 其他

    //是否入账
    const STATUS_APPLY   = '0'; // 创建
    const STATUS_SUCCESS = '1'; // 入账(操作 充值成功)
    const STATUS_FAIL    = '2'; // 失败

    public static function getChangeTypeStr($change_type)
    {
        switch ($change_type) {
            case self::CHANGE_TYPE_FACTORY_UNIONPAY:
                return '银联在线支付';
            case self::CHANGE_TYPE_FACTORY_ALIPAY:
                return '支付宝支付';
            case self::CHANGE_TYPE_FACTORY_WXPAY:
                return '微信支付';
            case self::CHANGE_TYPE_SYSTEM_SETTLE:
                return '系统自动结算';
            case self::CHANGE_TYPE_ADMIN_MANUAL:
                return '神州财务手动充值';
            case self::CHANGE_TYPE_ADMIN_SETTLE:
                return '工单费用调整';
            case self::CHANGE_TYPE_ADMIN_OTHER:
                return '其他';
            default :
                return '';
        }

    }

}