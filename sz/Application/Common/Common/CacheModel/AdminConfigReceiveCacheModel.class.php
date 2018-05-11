<?php
/**
 * File: AdminConfigReceiveCacheModel.class.php
 * Function:
 * User: sakura
 * Date: 2018/3/20
 */

namespace Common\Common\CacheModel;


use Common\Common\Service\AdminConfigReceiveService;
use Common\Common\Service\AdminConfigReceiveWorkdayService;
use Common\Common\Service\AdminService;

class AdminConfigReceiveCacheModel extends CacheModel
{

    public static function getTableName()
    {
        return 'admin_config_receive';
    }

}