<?php
/**
 * File: AdminConfigReceiveService.class.php
 * Function:
 * User: sakura
 * Date: 2018/2/6
 */

namespace Common\Common\Service;


class AdminConfigReceiveService
{

    //接单类型 1-按厂家 2-按品类 3-按地区 4-轮流 5-按厂家组别
    const TYPE_FACTORY       = 1;
    const TYPE_CATEGORY      = 2;
    const TYPE_AREA          = 3;
    const TYPE_TAKE_TURN     = 4;
    const TYPE_FACTORY_GROUP = 5;

    const TYPE_VALID_ARRAY
        = [
            self::TYPE_FACTORY,
            self::TYPE_CATEGORY,
            self::TYPE_AREA,
            self::TYPE_TAKE_TURN,
            self::TYPE_FACTORY_GROUP,
        ];

    //是否接单 0-否 1-是
    const IS_AUTO_RECEIVE_YES = 1;
    const IS_AUTO_RECEIVE_NO  = 0;

    const IS_AUTO_RECEIVE_VALID_ARRAY
        = [
            self::IS_AUTO_RECEIVE_YES,
            self::IS_AUTO_RECEIVE_NO,
        ];

}