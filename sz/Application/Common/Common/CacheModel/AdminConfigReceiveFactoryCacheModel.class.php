<?php
/**
 * File: AdminConfigReceiveFactoryCacheModel.class.php
 * Function:
 * User: sakura
 * Date: 2018/3/2
 */

namespace Common\Common\CacheModel;


use Common\Common\Model\BaseModel;
use Common\Common\Service\AdminConfigReceiveService;
use Common\Common\Service\AdminService;

class AdminConfigReceiveFactoryCacheModel extends CacheModel
{

    public static function getTableName()
    {
        return 'admin_config_receive_factory';
    }


    public static function getAdminIds($factory_id)
    {
        $relation_table_name = 'admin';
        $key = self::getRelationCacheKey($factory_id, $relation_table_name);

        $redis = self::getCache();
        if ($redis->exists($key)) {
            $admin_ids = $redis->sMembers($key);

            return array_filter($admin_ids, function ($admin) {
                return (-1 != $admin);
            });
        }

        $available_admin_ids = BaseModel::getInstance('admin')->getFieldVal([
            'state' => AdminService::STATE_ENABLED,
        ], 'id', true);
        $available_admin_ids = empty($available_admin_ids) ? '-1' : $available_admin_ids;

        $available_admin_ids = BaseModel::getInstance('admin_config_receive')
            ->getFieldVal([
                'type'            => AdminConfigReceiveService::TYPE_FACTORY,
                'is_auto_receive' => AdminConfigReceiveService::IS_AUTO_RECEIVE_YES,
                'admin_id'        => ['in', $available_admin_ids],
            ], 'admin_id', true);
        $available_admin_ids = empty($available_admin_ids) ? '-1' : $available_admin_ids;

        $model = BaseModel::getInstance('admin_config_receive_factory');
        $opts = [
            'where' => [
                'factory_id' => $factory_id,
                'admin_id'   => ['in', $available_admin_ids],
            ],
        ];
        $admin_ids = $model->getFieldVal($opts, 'admin_id', true);

        if (empty($admin_ids)) {
            $admin_ids = [-1]; // 保证键存在,避免null的时候经常查数据库
            $expire = 5 * 60; // 5分钟,没有数据时间相对短些
        } else {
            $expire = 10 * 60; // 10分钟
        }

        $redis->sAddArray($key, $admin_ids);
        $redis->expire($key, $expire);

        return array_filter($admin_ids, function ($admin) {
            return (-1 != $admin);
        });

    }


}