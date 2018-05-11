<?php
/**
 * File: AdminConfigReceivePartnerModel.class.php
 * Function:
 * User: sakura
 * Date: 2018/3/20
 */

namespace Common\Common\CacheModel;


use Common\Common\Model\BaseModel;

class AdminConfigReceivePartnerModel extends CacheModel
{

    public static function getTableName()
    {
        return 'admin_config_receive_partner';
    }

    public static function getPartnerIds($admin_id)
    {
        $relation_table_name = 'admin';
        $key = self::getRelationCacheKey($admin_id, $relation_table_name);

        $redis = self::getCache();
        if ($redis->exists($key)) {
            $admin_ids = $redis->sMembers($key);
            return array_filter($admin_ids, function($admin){
                return (-1 != $admin);
            });
        }

        $model = BaseModel::getInstance('admin_config_receive_partner');
        $opts = [
            'where' => [
                'admin_id' => $admin_id,
            ],
        ];
        $admin_ids = $model->getFieldVal($opts, 'partner_admin_id', true);

        if (empty($admin_ids)) {
            $admin_ids = [-1]; // 保证键存在,避免null的时候经常查数据库
            $expire = 5 * 60; // 5分钟,没有数据时间相对短些
        } else {
            $expire = 10 * 60; // 10分钟
        }

        $redis->sAddArray($key, $admin_ids);
        $redis->expire($key, $expire);

        return array_filter($admin_ids, function($admin){
            return (-1 != $admin);
        });

    }

}