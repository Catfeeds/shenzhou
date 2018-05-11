<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2018/1/30
 * Time: 19:20
 */

namespace Common\Common\CacheModel;

use Common\Common\Model\BaseModel;
use Common\Common\Service\FrontendRoutingService;

class AdminCacheModel extends CacheModel
{

    public static function getTableName()
    {
        return 'admin';
    }

    /**
     * @param $id
     *
     * @return array $frontend_routing_ids
     */
    public static function getAdminAllFrontendRoutingIds($id, &$is_super = false)
    {
        $admin_roles = AdminCacheModel::getRelation($id, 'rel_admin_roles', 'admin_id', 'admin_roles_id');
        $frontend_routing_ids = [];
        $field = 'id';
        if ($admin_roles && C('SUPERADMINISTRATOR_ROLES_ID') && in_array(C('SUPERADMINISTRATOR_ROLES_ID'), $admin_roles)) {
            $is_super = true;
            $list = BaseModel::getInstance('frontend_routing')->getList([
                'field' => $field,
                'where' => [
                    'is_delete' => FrontendRoutingService::IS_DELETE_NO,
                ],
            ]);
            $frontend_routing_ids = array_column($list, 'id');
        } else {
            foreach ($admin_roles as $v) {
                $datas = [];
                foreach (AdminRoleCacheModel::getRelation($v, 'rel_frontend_routing_admin_roles', 'admin_roles_id', 'frontend_routing_id') as $vv) {
                    $data = FrontendRoutingCacheModel::getOneOrFail($vv, $field . ',is_delete');
                    if (!$data['is_delete']) {
                        $datas[] = $data['id'];
                    }
                }
                $frontend_routing_ids = array_merge($frontend_routing_ids, $datas);
            }
        }

        return $frontend_routing_ids;
    }

    /**
     * @param $id
     *
     * @return array $frontend_routings
     */
    public static function getAdminAllFrontendRoutings($id, &$is_super = false)
    {
        $admin_roles = AdminCacheModel::getRelation($id, 'rel_admin_roles', 'admin_id', 'admin_roles_id');
        $frontend_routings = [];
        $field = 'id,routing,name,is_show,is_menu,parent_id,serial,create_time';
        if ($admin_roles && C('SUPERADMINISTRATOR_ROLES_ID') && in_array(C('SUPERADMINISTRATOR_ROLES_ID'), $admin_roles)) {
            $is_super = true;
            $frontend_routings = BaseModel::getInstance('frontend_routing')
                ->getList([
                    'field' => $field,
                    'where' => [
                        'is_delete' => FrontendRoutingService::IS_DELETE_NO,
                    ],
                ]);
        } else {
            foreach ($admin_roles as $v) {
                $datas = [];
                foreach (AdminRoleCacheModel::getRelation($v, 'rel_frontend_routing_admin_roles', 'admin_roles_id', 'frontend_routing_id') as $vv) {
                    $data = FrontendRoutingCacheModel::getOneOrFail($vv, $field . ',is_delete');
                    if (!$data['is_delete']) {
                        unset($data['is_delete']);
                        $datas[$data['id']] = $data;
                    }
                }
                $frontend_routings = array_merge($frontend_routings, $datas);
            }
        }

        return $frontend_routings;
    }

    // 单次只增加一个主键的关系
    public static function addAdminRoleRelation($id)
    {
        $table_name = 'rel_admin_roles';
        $model = BaseModel::getInstance($table_name);

        $opts = [
            'field' => 'admin_roles_id',
            'where' => [
                'admin_id' => $id,
            ],
        ];
        $list = $model->getList($opts);

        self::removeRelation($id, $table_name);

        if (!empty($list)) {
            $admin_roles_ids = array_column($list, 'admin_roles_id');
            self::addRelationCache($id, $admin_roles_ids, $table_name);

            return $admin_roles_ids;
        }

        return [];
    }

    // 单次只增加一个主键的关系
    public static function addAdminGroupRelation($id)
    {
        $table_name = 'rel_admin_group';
        $model = BaseModel::getInstance($table_name);

        $opts = [
            'field' => 'admin_group_id',
            'where' => [
                'admin_id' => $id,
            ],
        ];
        $list = $model->getList($opts);

        $admin_group_ids = array_column($list, 'admin_group_id');
        self::addRelationCache($id, $admin_group_ids, $table_name);
    }

    public static function removeAdminRoleRelation($id)
    {
        $table_name = 'rel_admin_roles';

        self::removeRelation($id, $table_name);
    }

    public static function removeAdminGroupRelation($id)
    {
        $table_name = 'rel_admin_group';

        self::removeRelation($id, $table_name);
        if (!empty($list)) {
            $admin_group_ids = array_column($list, 'admin_group_id');
            self::addRelationCache($id, $admin_group_ids, $table_name);

            return $admin_group_ids;
        }

        return [];
    }

    public static function getAdminRoleRelation($id)
    {
        $table_name = 'rel_admin_roles';

        $key = self::getRelationCacheKey($id, $table_name);
        $data = self::getCache()->sGetMembers($key);
        if (!$data) {
            $data = self::addAdminRoleRelation($id);
        }

        return $data;
    }

    public static function getAdminGroupRelation($id)
    {
        $table_name = 'rel_admin_group';

        $key = self::getRelationCacheKey($id, $table_name);
        $data = self::getCache()->sGetMembers($key);
        if (!$data) {
            $data = self::addAdminGroupRelation($id);
        }

        return $data;
    }

}