<?php
/**
 * File: CostService.class.php
 * User: sakura
 * Date: 2017/11/9
 */

namespace Common\Common\Service;


class CostService
{

    const STATUS_APPLY             = 0; // 创建费用单（待客服审核）
    const STATUS_ADMIN_FORBIDDEN   = 1; // 客服审核不通过
    const STATUS_ADMIN_PASS        = 2; // 客服审核（待厂家审核）
    const STATUS_FACTORY_FORBIDDEN = 3; // 厂家审核不通过
    const STATUS_FACTORY_PASS      = 4; // 厂家审核（完结）

    //申请费用类型
    const TYPE_REMOTE_APPOINT = 1; // 远程上门
    const TYPE_BUY_ACCESSORY  = 2; // 购买配件费用
    const TYPE_WRAP           = 3; // 旧机拆机合和打包费用
    const TYPE_SHIPPING       = 4; // 旧机返厂运费
    const TYPE_OTHER          = 5; // 其他

    public static function getTypeStr($type)
    {
        switch ($type) {
            case self::TYPE_REMOTE_APPOINT:
                return '远程上门';
            case self::TYPE_BUY_ACCESSORY:
                return '购买配件费用';
            case self::TYPE_WRAP:
                return '旧机拆机合和打包费用';
            case self::TYPE_SHIPPING:
                return '旧机返厂运费';
            case self::TYPE_OTHER:
                return '其他';
            default:
                return '';
        }
    }
}