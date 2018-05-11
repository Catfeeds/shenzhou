<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/16
 * Time: 10:54
 */

namespace Common\Common\Service;

use Common\Common\ErrorCode;
use Common\Common\Service\UserCommonInfoService\UserCommonInfo;
use Common\Common\Service\UserCommonInfoService\UserInfoType;

class UserTypeService
{

    public static function getTypeData($user_type, $user_id, $user_type_map = UserInfoType::USER_COMMON_TYPE)
    {

        $class = $user_type_map[$user_type];
        if (!class_exists($class)) {
            throw new \Exception('用户类型不存在,请检查~', ErrorCode::SYS_SYSTEM_ERROR);
        }
        /**
         * @var UserCommonInfo $user_type
         */
        $user_type = new $class($user_id);
        return $user_type;
    }
}