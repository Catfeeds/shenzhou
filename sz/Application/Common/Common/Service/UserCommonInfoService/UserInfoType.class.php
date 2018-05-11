<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/16
 * Time: 09:53
 */

namespace Common\Common\Service\UserCommonInfoService;

use Common\Common\Service\UserCommonInfoService\User\Admin;
use Common\Common\Service\UserCommonInfoService\User\Factory;
use Common\Common\Service\UserCommonInfoService\User\FactoryAdmin;
use Common\Common\Service\UserCommonInfoService\User\System;
use Common\Common\Service\UserCommonInfoService\User\Worker;
use Common\Common\Service\UserCommonInfoService\User\WxUser;

class UserInfoType
{
    const USER_COMMON_TYPE = [
        0 => System::class,
        1 => Admin::class,
        2 => Factory::class,
        3 => FactoryAdmin::class,
        4 => Worker::class,
        5 => WxUser::class,
        6 => WxUser::class,
        7 => System::class,
    ];

    const USER_ORDER_TYPE = [
        0 => \Common\Common\Service\UserCommonInfoService\User\WorkerOrder\WorkerOrderList\System::class,
        1 => \Common\Common\Service\UserCommonInfoService\User\WorkerOrder\WorkerOrderList\Factory::class,
        2 => \Common\Common\Service\UserCommonInfoService\User\WorkerOrder\WorkerOrderList\FactoryAdmin::class,
        3 => \Common\Common\Service\UserCommonInfoService\User\WorkerOrder\WorkerOrderList\System::class,
        4 => \Common\Common\Service\UserCommonInfoService\User\WorkerOrder\WorkerOrderList\WxUser::class,
        5 => \Common\Common\Service\UserCommonInfoService\User\WorkerOrder\WorkerOrderList\WxUser::class,
    ];

    const USER_ORDER_MESSAGE_TYPE = [
        1 => User\WorkerOrder\WorkerOrderMessage\Admin::class,
        2 => User\WorkerOrder\WorkerOrderMessage\Factory::class,
        3 => User\WorkerOrder\WorkerOrderMessage\FactoryAdmin::class,
        4 => User\WorkerOrder\WorkerOrderMessage\Worker::class,
    ];

    //配件单
    const USER_ACCESSORY_TYPE = [
        1 => Admin::class,
        2 => Factory::class,
        3 => FactoryAdmin::class,
        4 => System::class,
        5 => Worker::class,
    ];

    const USER_COMPLAINT_FROM_TYPE = [
        0 => System::class,
        1 => Admin::class,
        2 => User\Complaint\Factory::class,
        3 => FactoryAdmin::class,
        4 => Worker::class,
        5 => WxUser::class,
        6 => WxUser::class,
    ];

}