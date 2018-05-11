<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2018/2/12
 * Time: 15:28
 */

namespace Common\Common\CacheModel;

use Common\Common\Model\BaseModel;

class BackendRoutingCacheModel extends CacheModel
{
    public static function getTableName()
    {
        return 'backend_routing';
    }

    // 单次只增加一个主键的关系
    public static function addFrontendRoutingRelation($id)
    {
        $table_name = 'rel_backend_frontend_routing';
        $model = BaseModel::getInstance($table_name);
        $list = $model->getList([
            'where' => [
                'backend_routing_id' => $id,
            ],
        ]);

        foreach ($list as $v) {
            $datas = FrontendRoutingCacheModel::getRelation($v['frontend_routing_id'], $table_name, 'frontend_routing_id', 'backend_routing_id');
            $datas[] = $v['backend_routing_id'];
            FrontendRoutingCacheModel::removeRelation($v['frontend_routing_id'], $table_name);
            FrontendRoutingCacheModel::addRelationCache($v['frontend_routing_id'], $datas, $table_name);
        }
    }

    public static function updateFrontendRoutingRelation($id, array $frontend_routing_ids)
    {
        $table_name = 'rel_backend_frontend_routing';
        $model = BaseModel::getInstance($table_name);
        $list = $model->getList([
            'where' => [
                'backend_routing_id' => $id,
            ],
        ]);
        $old = array_column($list, 'frontend_routing_id');

        // 新加
        $add_all = [];
        foreach (array_diff($frontend_routing_ids, $old) as $v) {
            $add_all[] = [
                'backend_routing_id' => $id,
                'frontend_routing_id' => $v,
            ];
        }
        $add_all && $model->insertAll($add_all);
        // 删除
        $del_ids = array_diff($old, $frontend_routing_ids);
        $del_ids && $model->remove([
            'backend_routing_id' => $id,
            'frontend_routing_id' => ['in', implode(',', $del_ids)],
        ]);

        foreach (array_diff($frontend_routing_ids, $old) as $v) {
            $datas = FrontendRoutingCacheModel::getRelation($v, $table_name, 'frontend_routing_id', 'backend_routing_id');
            $datas[] = $id;
            FrontendRoutingCacheModel::removeRelation($v, $table_name);
            FrontendRoutingCacheModel::addRelationCache($v, $datas, $table_name);
        }

        foreach (array_diff($old, $frontend_routing_ids) as $v) {
            $datas = FrontendRoutingCacheModel::getRelation($v, $table_name, 'frontend_routing_id', 'backend_routing_id');
            $unkey = array_search($id, $datas);
            if ($unkey === false) {
                continue;
            }
            unset($datas[$unkey]);
            FrontendRoutingCacheModel::removeRelation($v, $table_name);
            if (!empty($datas)) {
                FrontendRoutingCacheModel::addRelationCache($v, $datas, $table_name);
            }
        }
    }

    public static function removeFrontendRoutingRelation($id)
    {
        $table_name = 'rel_backend_frontend_routing';
        $model = BaseModel::getInstance($table_name);
        $list = $model->getList([
            'where' => [
                'backend_routing_id' => $id,
            ],
        ]);
        $model->remove([
            'backend_routing_id' => $id,
        ]);
        foreach ($list as $v) {
            $datas = FrontendRoutingCacheModel::getRelation($v['frontend_routing_id'], $table_name, 'frontend_routing_id', 'backend_routing_id');
            $unkey = array_search($v['backend_routing_id'], $datas);
            if ($unkey === false) {
                continue;
            }
            unset($datas[$unkey]);
            FrontendRoutingCacheModel::removeRelation($v['frontend_routing_id'], $table_name);
            if (!empty($datas)) {
                FrontendRoutingCacheModel::addRelationCache($v['frontend_routing_id'], $datas, $table_name);
            }
        }
    }

    public static function remove($id)
    {
        self::getCache()->delete(self::getCacheKey($id));
        self::getModel()->update($id, ['is_delete' => NOW_TIME]);

        self::removeFrontendRoutingRelation($id);
    }

}