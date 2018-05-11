<?php
/**
 * File: WorkerWithdrawService.class.php
 * Function:
 * User: sakura
 * Date: 2017/11/26
 */

namespace Common\Common\Service;


class WorkerWithdrawService
{

    //对应status 状态
    const STATUS_WORKING = 0;
    const STATUS_SUCCESS = 1; // 提现成功
    const STATUS_FAIL = 2; // 提现失败

    public static function getStatusStr($status, $withdrawcash_excel_id)
    {
        if (self::STATUS_WORKING == $status) {
            if (0 == $withdrawcash_excel_id) {
                return '待处理';
            } elseif ($withdrawcash_excel_id > 0) {
                return '处理中';
            }
        } elseif (self::STATUS_SUCCESS == $status) {
            return '提现成功';
        } elseif (self::STATUS_FAIL == $status) {
            return '提现失败';
        }

        return '';
    }


}