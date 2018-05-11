<?php
/**
 * File: WorkerQualityService.class.php
 * Function:
 * User: sakura
 * Date: 2017/11/27
 */

namespace Common\Common\Service;


class WorkerQualityService
{

    const TYPE_SYSTEM = 0;
    const TYPE_MANUAL = 1;

    const TYPE_SYSTEM_REMARK = '工单结算自动转入';

    public static function getTypeStr($type)
    {
        switch ($type) {
            case self::TYPE_MANUAL: return '财务手工调整';
            case self::TYPE_SYSTEM: return '工单自动转入';
        }

        return '';
    }

}