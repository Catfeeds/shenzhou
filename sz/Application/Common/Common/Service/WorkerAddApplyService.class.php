<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/10
 * Time: 11:12
 */

namespace Common\Common\Service;

class WorkerAddApplyService
{

    const STATUS_NEED_PROCESS = 0;
    const STATUS_HAD_ADDED = 1;
    const STATUS_CAN_NOT_ADDED = 2;
    const STATUS_CANCELED = 3;
    const STATUS_PROCESSING = 4;
    const STATUS_FOLLOW_UP  = 5;

}