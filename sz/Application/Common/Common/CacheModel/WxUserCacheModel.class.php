<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2018/2/7
 * Time: 11:12
 */

namespace Common\Common\CacheModel;

class WxUserCacheModel extends CacheModel
{
    public static function getTableName()
    {
        return 'wx_user';
    }

    public static function remove($id)
    {
        self::getCache()->delete(self::getCacheKey($id));
        self::getModel()->update($id, ['is_delete' => NOW_TIME]);
    }

}