<?php
/**
 * File: WorkerOrderAppointRecordService.class.php
 * Function:
 * User: sakura
 * Date: 2017/12/5
 */

namespace Common\Common\Service;


class WorkerOrderAppointRecordService
{

    //是否上传服务报告
    const IS_OVER_NO  = '0'; // 否
    const IS_OVER_YES = '1'; // 是

    const SIGN_IN_DEFAULT 	= '0';
    const SIGN_IN_SUCCESS 	= '1';
    const SIGN_IN_FAIL 		= '2';

    const STATUS_WAIT_WORKER_SIGN_IN 					= '1';
    const STATUS_EDIT_APPOINT_TIME 						= '2';
    const STATUS_SIGN_IN_FAIL 							= '3';
    const STATUS_SIGN_IN_SUCCESS 						= '4';
    const STATUS_APPOINT_AGAIN_AND_WAIT					= '5';
    const STATUS_CS_EDIT_NUMS_RECORD					= '6';

}