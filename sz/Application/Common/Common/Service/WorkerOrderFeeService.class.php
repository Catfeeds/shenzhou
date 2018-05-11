<?php
/**
 * File: WorkerOrderFeeService.class.php
 * Function:
 * User: sakura
 * Date: 2017/12/5
 */

namespace Common\Common\Service;


class WorkerOrderFeeService
{
    //上门费计费模式
    const HOMEFEE_MODE_NONE   = 0; // 未设置
    const HOMEFEE_MODE_FIRST  = 1; // 第一次免基本里程费
    const HOMEFEE_MODE_SECOND = 2; // 第二次10元基本里程费

    public static function getHomeFeeModeStr($homefee_mode)
    {
        switch ($homefee_mode) {
            case self::HOMEFEE_MODE_NONE: return '未设置';
            case self::HOMEFEE_MODE_FIRST: return '首次上门免基本里程费';
            case self::HOMEFEE_MODE_SECOND: return '二次上门免基本里程费';
            default: return '';
        }
    }

}