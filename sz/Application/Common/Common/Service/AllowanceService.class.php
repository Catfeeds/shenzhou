<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/19
 * Time: 16:17
 */

namespace Common\Common\Service;

class AllowanceService
{
    const TYPE_ADJUST_APPOINT_FEE = 1;  // 调整上门费
    const TYPE_ADJUST_REPAIR_FEE  = 2;   // 调整维修费
    const TYPE_ORDER_AWARD        = 3;         // 工单奖励


    const STATUS_UNCHECKED  = 0; // 待审核
    const STATUS_PASS       = 1; // 审核通过
    const STATUS_NOT_PASS   = 2; // 审核不通过
    const STATUS_SYS_CANCEL = 3; // 系统取消

    public static function getTypeStr($type)
    {
        switch ($type) {
            case self::TYPE_ADJUST_APPOINT_FEE:
                return '调整上门费';
            case self::TYPE_ADJUST_REPAIR_FEE:
                return '调整维修费';
            case self::TYPE_ORDER_AWARD:
                return '工单奖励';
        }

        return '';
    }

    public static function getStatusStr($status)
    {
        switch ($status) {
            case self::STATUS_UNCHECKED:
                return '待审核';
            case self::STATUS_PASS:
                return '审核通过';
            case self::STATUS_NOT_PASS:
                return '审核不通过';
            case self::STATUS_SYS_CANCEL:
                return '系统取消';
        }

        return '';
    }

}