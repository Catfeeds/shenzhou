<?php
/**
 * File: WorkerOrderProductCacheModel.class.php
 * Function:
 * User: sakura
 * Date: 2018/3/2
 */

namespace Common\Common\CacheModel;


use Common\Common\Model\BaseModel;

class WorkerOrderProductCacheModel extends CacheModel
{

    public static function getTableName()
    {
        return 'worker_order_product';
    }

    public static function getWorkerOrderProductIds($worker_order_id)
    {
        $relation_table_name = 'worker_order';
        $key = self::getRelationCacheKey($worker_order_id, $relation_table_name);

        $redis = self::getCache();

        if ($redis->exists($key)) {
            return $redis->sMembers($key);
        }

        $product_ids = self::addWorkerOrderProductIds($worker_order_id);

        return $product_ids;

    }

    public static function addWorkerOrderProductIds($worker_order_id)
    {
        $relation_table_name = 'worker_order';
        $key = self::getRelationCacheKey($worker_order_id, $relation_table_name);

        $redis = self::getCache();

        $model = BaseModel::getInstance('worker_order_product');

        $where = [
            'worker_order_id' => $worker_order_id,
        ];
        $product_ids = $model->getFieldVal($where, 'id', true);

        $expire = 5 * 86400;

        $redis->delete($key);
        $redis->sAddArray($key, $product_ids);
        $redis->expire($key, $expire);

        return $product_ids;
    }

    public static function getWorkerOrderProduct($id, $fields)
    {
        $fields = explode(',', $fields);

        $key = self::getCacheKey($id);

        $redis = self::getCache();

        if ($redis->exists($key)) {
            return $redis->hMGet($key, $fields);
        }

        $info = self::addWorkerOrderProduct($id);

        return array_filter($info, function ($key) use ($fields) {
            return in_array($key, $fields);
        }, ARRAY_FILTER_USE_KEY);

    }

    public static function addWorkerOrderProduct($id)
    {
        $key = self::getCacheKey($id);

        $redis = self::getCache();

        $model = BaseModel::getInstance('worker_order_product');

        $info = $model->getOneOrFail($id);

        $redis->delete($key);
        $redis->hMSet($key, $info);
        $expire = 5 * 86400;
        $redis->expire($key, $expire);

        return $info;
    }


}