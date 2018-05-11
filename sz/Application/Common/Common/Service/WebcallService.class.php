<?php
/**
 * File: WebcallService.class.php
 * Function:
 * User: sakura
 * Date: 2018/1/18
 */

namespace Common\Common\Service;


class WebcallService
{
    //呼出用户类型
    const CALL_USER_TYPE_ADMIN = 1; // 客服

    //呼入用户类型
    const CALLED_USER_TYPE_WORKER         = 1; //技工
    const CALLED_USER_TYPE_USER           = 2; //用户
    const CALLED_USER_TYPE_FACTORY_HELPER = 3; //技术支持人

    const CALLED_USER_TYPE_VALID_ARRAY = [
        self::CALLED_USER_TYPE_WORKER,
        self::CALLED_USER_TYPE_USER,
        self::CALLED_USER_TYPE_FACTORY_HELPER,
    ];

    //状态
    const STATUS_CREATED = 0;
    const STATUS_HANGUP  = 4;


    public static function getCalledUserTypeStr($called_user_type)
    {
        switch ($called_user_type) {
            case self::CALLED_USER_TYPE_WORKER:
                return '技工';
            case self::CALLED_USER_TYPE_USER:
                return '用户';
            case self::CALLED_USER_TYPE_FACTORY_HELPER:
                return '技术支持人';
        }
    }

}