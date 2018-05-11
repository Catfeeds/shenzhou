<?php
/**
 * File: WorkbenchCacheModel.class.php
 * Function:
 * User: sakura
 * Date: 2018/3/12
 */

namespace Common\Common\CacheModel;


use Common\Common\Model\BaseModel;

class WorkbenchCacheModel extends CacheModel
{

    public static function getTableName()
    {
        return 'worker_order_workbench_config';
    }

    protected static function getCacheKeyAll()
    {
        return 'sz:' . static::getTableName();
    }

    public static function getAll()
    {
        $redis = self::getCache();

        $key = self::getCacheKeyAll();

        if ($redis->exists($key)) {
            return $redis->hGetAll($key);
        }

        return self::addCacheAll();
    }

    public static function addCacheAll()
    {
        $redis = self::getCache();

        $key = self::getCacheKeyAll();

        $model = BaseModel::getInstance(self::getTableName());

        $list = $model->getFieldVal([], 'name,val', true);

        $redis->delete($key);
        if (!empty($list)) {
            $redis->hMSet($key, $list);
            $expire = 7 * 86400; // 设置有效期为一周
            $redis->expire($key, $expire);
        }

        return $list;
    }

}