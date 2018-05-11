<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2018/2/7
 * Time: 14:39
 */

namespace Common\Common\CacheModel;

class FactoryAdminCacheModel extends CacheModel
{
    public static function getTableName()
    {
        return 'factory_admin';
    }

    public static function remove($id)
    {
        self::getCache()->delete(self::getCacheKey($id));
        self::getModel()->update($id, ['is_delete' => NOW_TIME]);
    }

}