<?php
/**
 * File: WorkerOrderProductService.class.php
 * Function:
 * User: sakura
 * Date: 2017/12/5
 */

namespace Common\Common\Service;


class WorkerOrderProductService
{
    //是否完成
    const IS_COMPLETE_NO  = 0; // 否
    const IS_COMPLETE_YES = 1; // 是
    const IS_COMPLETE_NOT_CAN = 2; // 不能完成维修

    public static function getIsCompleteStr($is_complete)
    {
        switch ($is_complete) {
            case self::IS_COMPLETE_NO:
                return '不能完成服务';
            case self::IS_COMPLETE_YES:
                return '完成服务';
            default:
                return '';
        }
    }

}