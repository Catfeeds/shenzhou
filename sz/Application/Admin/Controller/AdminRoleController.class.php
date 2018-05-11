<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/2/2
 * Time: 12:24
 */

namespace Admin\Controller;

use Admin\Common\ErrorCode;
use Admin\Logic\SystemReceiveOrderCacheLogic;
use Admin\Logic\SystemReceiveOrderLogic;
use Admin\Repositories\Events\SystemReceiveOrderEvent;
use Common\Common\CacheModel\AdminRoleCacheModel;
use Common\Common\CacheModel\CacheModel;
use Common\Common\CacheModel\FrontendRoutingCacheModel;
use Common\Common\Service\AdminRoleService;
use Common\Common\Service\AuthService;
use Admin\Model\BaseModel;

class AdminRoleController extends BaseController
{
    public function updateAdminRoleFrontendRouting()
    {
        $id = I('get.id', 0);
        $frontend_routing_ids = I('post.frontend_routing_ids', []);
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);

            $frontend_routing_ids = array_unique(array_filter($frontend_routing_ids));
            !$frontend_routing_ids && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '授权参数不能为空');
            foreach ($frontend_routing_ids as $v) {
                $data = FrontendRoutingCacheModel::getOne($v, 'id,is_delete');
                if (!$data) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '授权参数错误');
                } elseif ($data['is_delete']) {
                    AdminRoleCacheModel::removeRelation($v, 'rel_frontend_routing_admin_roles');
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '授权参数错误');
                }
            }

            $table_name = 'rel_frontend_routing_admin_roles';
            $model = BaseModel::getInstance($table_name);
            $list = $model->getList([
                'field' => 'frontend_routing_id',
                'where' => [
                    'admin_roles_id' => $id,
                ],
            ]);
            $old = array_column($list, 'frontend_routing_id');

            M()->startTrans();

            // 新加
            $add_all = [];
            foreach (array_diff($frontend_routing_ids, $old) as $v) {
                $add_all[] = [
                    'admin_roles_id'      => $id,
                    'frontend_routing_id' => $v,
                ];
            }
            $add_all && $model->insertAll($add_all);
            // 删除
            $del_ids = array_diff($old, $frontend_routing_ids);
            $del_ids && $model->remove([
                'admin_roles_id' => $id,
                'frontend_routing_id' => ['in', implode(',', $del_ids)],
            ]);

            AdminRoleCacheModel::addFrontendRoutingRelation($id);
            M()->commit();
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getAllList()
    {
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
            $where = [];
            $field = 'id,name,type';
            $list = BaseModel::getInstance(AdminRoleCacheModel::getTableName())
                ->getList([
                    'field' => $field,
                    'where' => $where,
                    'order' => 'create_time desc',
                ]);

            foreach ($list as $key => $val) {
                $type = $val['type'];

                $type_arr = [];
                foreach (AdminRoleService::AUTO_RECEIVE_TYPE_VALID_ARRAY as $receive_type) {
                    $is_active = $receive_type & $type;
                    if ($receive_type == $is_active) {
                        $type_arr[] = $is_active;
                    }
                }

                $val['type'] = $type_arr;

                $list[$key] = $val;
            }

            $this->responseList($list);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getList()
    {
        $search = I('get.');
        $name = I('get.name', '');
        $is_disable = I('get.is_disable', '');
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
            $where = [];

            isset($search['name']) && $where['name'] = ['like', "%{$name}%"];
            isset($search['is_disable']) && $where['is_disable'] = $is_disable;

            $model = BaseModel::getInstance(AdminRoleCacheModel::getTableName());
            $nums = $model->getNum($where);
            !$nums && $this->paginate();

            $field = 'id,name,is_disable,create_time,type';
            $list = $model->getList([
                'field' => $field,
                'where' => $where,
                'limit' => getPage(),
                'order' => 'create_time desc,id desc',
            ]);

            foreach ($list as $key => $val) {
                $type = $val['type'];

                $type_arr = [];
                foreach (AdminRoleService::AUTO_RECEIVE_TYPE_VALID_ARRAY as $receive_type) {
                    $is_active = $receive_type & $type;
                    if ($receive_type == $is_active) {
                        $type_arr[] = $is_active;
                    }
                }

                $val['type'] = $type_arr;

                $list[$key] = $val;
            }

            $this->paginate($list, $nums);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getOne()
    {
        $id = I('get.id', 0);
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
            $field = 'id,name,is_disable,create_time,update_time,level,type';
            $info = AdminRoleCacheModel::getOneOrFail($id, $field);
            $type = [];
            $admin_type = $info['type'];

            foreach (AdminRoleService::AUTO_RECEIVE_TYPE_VALID_ARRAY as $receive_type) {
                $is_active = $receive_type & $admin_type;
                if ($receive_type == $is_active) {
                    $type[] = $is_active;
                }
            }
            $info['type'] = empty($type)? '': implode(',', $type);

            $this->response($info);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function updateOne()
    {
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
            $id = I('get.id', 0);
            $name = I('put.name', '');
            $is_disable = I('put.is_disable', '');
            $level = I('put.level', '');
            $type = I('put.type', []);

            if ($id == C('SUPERADMINISTRATOR_ROLES_ID')) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '不允许编辑超级管理员角色');
            }

            $type = array_unique(array_filter($type));
            if (array_diff($type, AdminRoleService::AUTO_RECEIVE_TYPE_VALID_ARRAY)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '参数错误');
            }

            empty($name) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '参数角色名称不能为空');
            !in_array($is_disable, AdminRoleService::IS_DISABLE_VALID_ARRAY, true)
            && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '参数角色状态不存在');
            !in_array($level, AdminRoleService::LEVEL_ALL_ARR)
            && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '参数角色级别不存在');

            AdminRoleCacheModel::getOneOrFail($id, 'id');
            $nums = BaseModel::getInstance(AdminRoleCacheModel::getTableName())
                ->getNum([
                    'name' => $name,
                    'id'   => ['neq', $id],
                ]);
            $nums && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '角色名称已存在');

            $type_value = 0;
            foreach ($type as $v) {
                $type_value = $type_value | $v;
            }

            $auditor_type = AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR;
            $worker_order_type = AdminRoleService::AUTO_RECEIVE_TYPE_CHECKER | AdminRoleService::AUTO_RECEIVE_TYPE_DISTRIBUTOR | AdminRoleService::AUTO_RECEIVE_TYPE_RETURNEE;

            if ($type_value > $auditor_type) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '接单类型错误');
            }

            $update = [
                'name'        => $name,
                'is_disable'  => $is_disable,
                'level'       => $level,
                'update_time' => NOW_TIME,
                'type'        => $type_value,
            ];
            M()->startTrans();
            AdminRoleCacheModel::update($id, $update);
            M()->commit();

            event(new SystemReceiveOrderEvent([]));

            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function insertOne()
    {
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);

            $name = I('post.name', '');
            $is_disable = I('post.is_disable', '');
            $level = I('post.level', '');
            $type = I('post.type', []);

            $type = array_unique(array_filter($type));
            if (array_diff($type, AdminRoleService::AUTO_RECEIVE_TYPE_VALID_ARRAY)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '参数错误');
            }

            empty($name) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '参数角色名称不能为空');
            !in_array($is_disable, AdminRoleService::IS_DISABLE_VALID_ARRAY, true)
            && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '参数角色状态不存在');
            !in_array($level, AdminRoleService::LEVEL_ALL_ARR)
            && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '参数角色级别不存在');

            $nums = BaseModel::getInstance(AdminRoleCacheModel::getTableName())
                ->getNum(['name' => $name]);
            $nums && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '角色名称已存在');

            $type_value = 0;
            foreach ($type as $v) {
                $type_value = $type_value | $v;
            }

            $auditor_type = AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR;
            $worker_order_type = AdminRoleService::AUTO_RECEIVE_TYPE_CHECKER | AdminRoleService::AUTO_RECEIVE_TYPE_DISTRIBUTOR | AdminRoleService::AUTO_RECEIVE_TYPE_RETURNEE;

            if ($type_value > $auditor_type) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '接单类型错误');
            }

            $insert = [
                'name'        => $name,
                'is_disable'  => $is_disable,
                'level'       => $level,
                'create_time' => NOW_TIME,
                'update_time' => NOW_TIME,
                'type'        => $type_value,
            ];

            M()->startTrans();
            AdminRoleCacheModel::insert($insert);
            M()->commit();

            event(new SystemReceiveOrderEvent([]));

            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}