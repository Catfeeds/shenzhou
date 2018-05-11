<?php
/**
 * Created by PhpStorm.
 * User: zjz
 * Date: 2018/2/2
 * Time: 14:20
 */

namespace Common\Common\CacheModel;

use Common\Common\Model\BaseModel;

class AdminRoleCacheModel extends CacheModel
{
    public static function getTableName()
    {
        return 'admin_roles';
    }

    public static function addOneFrontendRoutingIdRelation($role_id, $frontend_routing_id)
    {
        $table_name = 'rel_frontend_routing_admin_roles';

        BaseModel::getInstance($table_name)->insert([
            'admin_roles_id' => $role_id,
            'frontend_routing_id' => $frontend_routing_id
        ]);

        $datas = AdminRoleCacheModel::getRelation($role_id, $table_name, 'admin_roles_id', 'frontend_routing_id');
        $datas[] = $frontend_routing_id;
        AdminRoleCacheModel::removeRelation($role_id, $table_name);
        AdminRoleCacheModel::addRelationCache($role_id, $datas, $table_name);
    }

    public static function addFrontendRoutingRelation($id)
    {
        $table_name = 'rel_frontend_routing_admin_roles';
        $model = BaseModel::getInstance($table_name);

        $opts = [
            'field' => 'frontend_routing_id',
            'where' => [
                'admin_roles_id' => $id,
            ],
        ];
        $list = $model->getList($opts);

        self::removeRelation($id, $table_name);

        if (!empty($list)) {
            $admin_roles_ids = array_column($list, 'frontend_routing_id');
            self::addRelationCache($id, $admin_roles_ids, $table_name);
        }
    }

}