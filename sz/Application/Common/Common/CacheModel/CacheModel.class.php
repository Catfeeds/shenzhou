<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2018/1/30
 * Time: 19:21
 */

namespace Common\Common\CacheModel;

use Common\Common\ErrorCode;
use Common\Common\Model\BaseModel;
use Common\Common\ResourcePool\RedisPool;
use Illuminate\Support\Arr;
use Library\Redis\RedLock;
use Think\Cache\Driver\Redis;
use Think\Exception;

abstract class CacheModel
{
    private static $cache = null;
    private static $model = [];

    private static $red_lock = null;

    private function __construct()
    {
    }

    public static function getPrimaryKeyField()
    {
        return 'id';
    }

    abstract public static function getTableName();

    /**
     * @param        $id
     * @param string $fields
     *
     * @return array|bool|null
     * @throws \Exception
     */
    public static function getOneOrFail($id, $fields)
    {
        $data = self::getOne($id, $fields);
        if (!$data) {
            throw new \Exception('', ErrorCode::SYS_DATA_NOT_EXISTS);
        }

        return $data;
    }

    public static function getOne($id, $fields)
    {
        if (empty($id)) {
            //异常的键,直接排除
            return null;
        }

        if (is_string($fields)) {
            $fields = explode(',', $fields);
        }

        //强制填充主键获取,用于判断数据是否存在
        $primary_key = self::getPrimaryKeyField();
        if (!in_array($primary_key, $fields)) {
            $fields[] = $primary_key;
        }
        $data = self::getCache()
            ->exists(self::getCacheKey($id)) ? self::getCache()
            ->hMGet(self::getCacheKey($id), $fields) : null;

        $no_data_flag = -1; // 数据不存在标识

        if (!$data) {
            //缓存不存在查数据库
            $item = self::getModel()->getOne($id);

            if ($item) {
                //数据存在
                self::addCache($id, $item);
                $data = Arr::only($item, $fields);
            } else {
                $temp_data = [
                    $primary_key => $no_data_flag,
                ];
                self::addTempCache($id, $temp_data);
                $data = null;
            }
        } else {
            //判断是否为数据不存在标识
            if ($no_data_flag == $data[$primary_key]) {
                $data = null;
            }
        }

        return $data;
    }

    public static function update($id, $data)
    {
        $exist_cache = self::getCache()->exists(self::getCacheKey($id));

        $exist_cache && self::getCache()->delete(self::getCacheKey($id));

        self::getModel()->update($id, $data);

        $new_data = self::getModel()->getOne($id);

        $exist_cache && $new_data && self::addCache($id, $new_data);
    }

    public static function removeCache($id)
    {
        self::getCache()->delete(self::getCacheKey($id));
    }

    public static function remove($id)
    {
        self::removeCache($id);
        self::getModel()->remove($id);
    }

    public static function insert($data)
    {
        $id = self::getModel()->insert($data);

        $m_data = self::getModel()->getOne($id);

        self::addCache($id, $m_data);

        return $id;
    }

    public static function addCache($id, $data)
    {
        $key = self::getCacheKey($id);
        //        self::getCache()->multi();
        self::getCache()->hMSet($key, $data);
        self::getCache()->expire($key, 864000);
        //        self::getCache()->exec();
    }

    /**
     * 存储临时数据,有效期10分钟
     *
     * @param string $id
     * @param array  $data
     */
    public static function addTempCache($id, $data)
    {
        $key = self::getCacheKey($id);
        self::getCache()->hMSet($key, $data);
        self::getCache()->expire($key, 600);
    }

    public static function getRelation($id, $relation_table_name, $id_key, $other_key)
    {
        $key = self::getRelationCacheKey($id, $relation_table_name);
        $data = self::getCache()->sGetMembers($key);
        if (!$data) {
            $data = BaseModel::getInstance($relation_table_name)
                ->getFieldVal([$id_key => $id], $other_key, true);
            self::addRelationCache($id, $data, $relation_table_name);
        }

        return $data;
    }

    public static function removeRelation($id, $relation_table_name)
    {
        self::getCache()
            ->delete(self::getRelationCacheKey($id, $relation_table_name));
    }

    public static function addRelationCache($id, $relation_list, $relation_table_name)
    {
        if (!$relation_list) {
            return;
        }
        $key = self::getRelationCacheKey($id, $relation_table_name);
        //        self::getCache()->multi();
        self::getCache()->sAddArray($key, $relation_list);
        self::getCache()->expire($key, 864000);
        //        self::getCache()->exec();
    }


    protected static function getCacheKey($id)
    {
        return 'sz:' . static::getTableName() . ':' . $id;
    }

    protected static function getRelationCacheKey($id, $relation_table_name)
    {
        return 'sz:rel:' . $relation_table_name . ':' . static::getTableName() . ':' . $id;
    }


    protected static function getCache()
    {
        if (!self::$cache) {
            self::$cache = RedisPool::getInstance();
        }

        return self::$cache;
    }

    protected static function getModel()
    {
        if (!isset(self::$model[static::getTableName()])) {
            self::$model[static::getTableName()] = BaseModel::getInstance(static::getTableName());
        }

        return self::$model[static::getTableName()];
    }

    /**
     * 设置锁名
     *
     * @param string $key 待加锁key名
     *
     * @return string
     */
    protected static function getLockKey($key)
    {
        return 'lock:' . $key;
    }

    /**
     * 加锁
     *
     * @param string $key     待加锁redis key名
     * @param int    $timeout 超时,单位:毫秒
     *
     * @return array
     */
    public static function lock($key, $timeout = 500)
    {
        $lock_key = self::getLockKey($key);
        if (!self::$red_lock) {
            self::$red_lock = new RedLock();
        }

        return self::$red_lock->lock($lock_key, $timeout);
    }

    /**
     * 解锁
     *
     * @param array $lock 锁信息,取自lock方法返回数组
     */
    public static function unlock($lock)
    {
        if (!self::$red_lock) {
            self::$red_lock = new RedLock();
        }

        self::$red_lock->unlock($lock);
    }

    /**
     * redis 事务开启 开启事务后,所有的redis操作将返回对象,直到提交后才返回值
     */
    public static function startTrans()
    {
        $redis = self::getCache();

        $redis->multi();
    }

    /**
     * redis 事务提交
     */
    public static function commit()
    {
        $redis = self::getCache();

        $redis->exec();
    }

}