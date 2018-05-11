<?php
/**
 * File: WorkerAnnouncementService.class.php
 * Function:
 * User: sakura
 * Date: 2018/4/9
 */

namespace Common\Common\Service;


class WorkerAnnouncementService
{

    //类型 1-业务通告 2-接单必读  3-活动消息
    const TYPE_BUSINESS_NOTICE             = 1;
    const TYPE_RECEIVE_ORDER_READ_REQUIRED = 2;
    const TYPE_ACTIVITY_NOTICE             = 3;

    //是否显示 0-不显示 1-显示
    const IS_SHOW_YES = 1;
    const IS_SHOW_NO  = 2;

}