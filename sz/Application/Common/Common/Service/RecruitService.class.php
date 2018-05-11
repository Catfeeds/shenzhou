<?php
/**
 * File: RecruitService.class.php
 * User: sakura
 * Date: 2017/11/21
 */

namespace Common\Common\Service;


class RecruitService
{

    const STATUS_APPLY     = 0; // 待处理
    const STATUS_COMPLETE  = 1; // 已完成,开点成功
    const STATUS_FORBIDDEN = 2; // 不能开点
    const STATUS_CANCEL    = 3; // 已取消
    const STATUS_WORKING   = 4; // 处理中
    const STATUS_FOLLOWING = 5; // 跟进中


    const RESULT_NULL    = 0;
    const RESULT_VALID   = 1; // 开点结果有效
    const RESULT_INVALID = 2; // 开点结果无效


}