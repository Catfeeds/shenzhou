<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2018/2/27
 * Time: 14:12
 */

namespace Admin\Logic;

use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Common\Common\CacheModel\AdminCacheModel;
use Common\Common\CacheModel\AdminGroupCacheModel;
use Common\Common\CacheModel\AdminRoleCacheModel;
use Common\Common\Service\AdminRoleService;
use Common\Common\Service\AuthService;
use Illuminate\Support\Arr;

class AdminGroupLogic extends BaseLogic
{

    public function ownGroup()
    {
        $admin_id = AuthService::getAuthModel()->getPrimaryValue();

        $admin_group_ids = AdminCacheModel::getRelation($admin_id, 'rel_admin_group', 'admin_id', 'admin_group_id');

        $group_list = [];

        foreach ($admin_group_ids as $admin_group_id) {
            $group = AdminGroupCacheModel::getOne($admin_group_id, 'id,name,is_disable');
            if ($group['is_disable'] == 0) {
                $group_list[] = Arr::only($group, ['id', 'name']);
            }
        }

        return $group_list;
    }

    public function groupMember()
    {
        $admin = AuthService::getAuthModel();

        $admin_group_id = I('admin_group_id');
        $name = I('name', '');

        if (!$admin_group_id) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        $is_manager = false;
        $admin_role_ids = AdminCacheModel::getRelation($admin['id'], 'rel_admin_roles', 'admin_id', 'admin_roles_id');
        foreach ($admin_role_ids as $admin_role_id) {
            $admin_role = AdminRoleCacheModel::getOne($admin_role_id, 'id,is_disable,level');
            if ($admin_role['is_disable'] == 0 && ($admin_role['level'] == AdminRoleService::LEVEL_CHARGE_ADMIN || $admin_role['level'] == AdminRoleService::LEVEL_GROUP_ADMIN)) {
                $is_manager = true;
                break;
            }
        }

        $members = [];
        if ($is_manager) {
            if (!BaseModel::getInstance('rel_admin_group')->getOne(['admin_id' => $admin['id'], 'admin_group_id' => $admin_group_id], 'admin_group_id')) {
                $this->throwException(ErrorCode::ADMIN_NO_PERMISSION, '您不在该分组内,无法查看该分组成员');
            }
            $where = ['admin_group_id' => $admin_group_id];
            if ($name) {
                $admin_ids = BaseModel::getInstance('admin')->getFieldVal(['nickout' => ['LIKE', "%{$name}%"]], 'id', true);
                $where['admin_id'] = $admin_ids ? ['IN', $admin_ids] : 0;
            }
            $admin_ids = array_column(BaseModel::getInstance('rel_admin_group')->getList(
                [
                    'where' => $where,
                    'field' => 'admin_id',
                    'order' => 'admin_id asc',
                    'limit' => getPage(),
                ]
            ), 'admin_id');
            $num = BaseModel::getInstance('rel_admin_group')->getNum($where);
            foreach ($admin_ids as $admin_id) {
                $item = AdminCacheModel::getOne($admin_id, 'nickout');
                $members[] = [
                    'id' => $admin_id,
                    'name' => $item['nickout'],
                ];
            }
        } else {
            $members[] = [
                'id' => $admin['id'],
                'name' => $admin['nickout'],
            ];
            $num = 1;
        }

        return [$members, $num];
    }

    public function getManageGroupIds($admin_id)
    {
        $is_manager = false;
        $admin_role_ids = AdminCacheModel::getRelation($admin_id, 'rel_admin_roles', 'admin_id', 'admin_roles_id');
        foreach ($admin_role_ids as $admin_role_id) {
            $admin_role = AdminRoleCacheModel::getOne($admin_role_id, 'id,is_disable,level');
            if ($admin_role['is_disable'] == 0 && ($admin_role['level'] == AdminRoleService::LEVEL_CHARGE_ADMIN || $admin_role['level'] == AdminRoleService::LEVEL_GROUP_ADMIN)) {
                $is_manager = true;
                break;
            }
        }

        if ($is_manager) {
            $group_ids = AdminCacheModel::getRelation($admin_id, 'rel_admin_group', 'admin_id', 'admin_group_id');
            return (array)$group_ids;
        } else {
            return [];
        }
    }

    public function getGroupAdmins($admin_group_ids)
    {
        return $admin_group_ids ? BaseModel::getInstance('rel_admin_group')->getFieldVal([
            'where' => ['admin_group_id' => ['IN', $admin_group_ids]]
        ], 'admin_id', true) : [];
    }

    public function getManageGroupAdmins($admin_group_ids)
    {
        $admin_group_ids = $admin_group_ids ? : $this->getManageGroupIds(AuthService::getAuthModel()->getPrimaryValue());
        $group_admin_ids = $this->getGroupAdmins($admin_group_ids);
        return $group_admin_ids;
    }

}