<?php
/**
 * File: FactoryCacheModel.class.php
 * Function:
 * User: sakura
 * Date: 2018/3/7
 */

namespace Common\Common\CacheModel;


use Library\Common\Arr;

class FactoryCacheModel extends CacheModel
{

    public static function getTableName()
    {
        return 'factory';
    }

    //todo 临时修改方法,暂时增删改没有同步缓存,缩短缓存时间
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
                $key = self::getCacheKey($id);
                self::getCache()->hMSet($key, $data);
                self::getCache()->expire($key, 86400);
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

}