<?php
/**
 * File: WorkerOrderProductCacheModel.class.php
 * Function:
 * User: sakura
 * Date: 2018/3/2
 */

namespace Common\Common\CacheModel;


use Common\Common\Model\BaseModel;

class WorkerOrderUserInfoCacheModel extends CacheModel
{

    public static function getTableName()
    {
        return 'worker_order_user_info';
    }

    public static function getWorkerOrderUserInfo($worker_order_id, $fields)
    {
        $key = self::getCacheKey($worker_order_id);

        $fields = explode(',', $fields);

        $redis = self::getCache();

        if ($redis->exists($key)) {
            return $redis->hMGet($key, $fields);
        }

        $info = self::addWorkerOrderUserInfo($worker_order_id);

        return array_filter($info, function($key) use ($fields) {
            return in_array($key, $fields);
        }, ARRAY_FILTER_USE_KEY);

    }

    public static function addWorkerOrderUserInfo($worker_order_id)
    {
        $model = BaseModel::getInstance('worker_order_user_info');

        $key = self::getCacheKey($worker_order_id);

        $redis = self::getCache();

        $info = $model->getOneOrFail($worker_order_id);

        $expire = 5 * 86400;

        $redis->delete($key);
        $redis->hMSet($key, $info);
        $redis->expire($key, $expire);

        return $info;
    }


}