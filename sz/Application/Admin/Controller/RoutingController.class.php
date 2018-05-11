<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/2/12
 * Time: 17:29
 */

namespace Admin\Controller;

use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Common\Common\CacheModel\AdminCacheModel;
use Common\Common\CacheModel\AdminRoleCacheModel;
use Common\Common\CacheModel\BackendRoutingCacheModel;
use Common\Common\CacheModel\CacheModel;
use Common\Common\CacheModel\FrontendRoutingCacheModel;
use Common\Common\Service\AuthService;
use Common\Common\Service\BackendRoutingService;
use Common\Common\Service\FrontendRoutingService;

class RoutingController extends BaseController
{
    public function getBackendRoutings()
    {
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);

            $model = BaseModel::getInstance(BackendRoutingCacheModel::getTableName());

            $where = [
                'is_delete' => BackendRoutingService::IS_DELETE_NO,
            ];
            $count = $model->getNum($where);
            !$count && $this->paginate();
            $list = $model->getList([
                'field' => 'id,name,routing,description,create_time',
                'limit' => getPage(),
                'where' => $where,
            ]);

            $this->paginate($list, $count);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getBackendRoutingById()
    {
        $id = I('get.id', 0);
        $table_name = 'rel_backend_frontend_routing';
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
            $data = BackendRoutingCacheModel::getOneOrFail($id, 'id,name,description,routing');
            $data['is_delete'] && $this->fail(ErrorCode::SYS_DATA_NOT_EXISTS);

            $list = BaseModel::getInstance($table_name)->getList([
                'backend_routing_id' => $id,
            ]);
            $data['frontend_routing_ids'] = array_column($list, 'frontend_routing_id');
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function updateBackendRouting()
    {
        $put = I('put.');
        $id = I('get.id', 0);
        $name = htmlEntityDecode(I('put.name', ''));
        $routing = htmlEntityDecode(I('put.routing', ''));
        $description = htmlEntityDecode(I('put.description', ''));
        $frontend_routing_ids = I('put.frontend_routing_ids', []);
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);

            empty($name) && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '名称不能为空');
            empty($routing) && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '路由不能为空');

            $frontend_routing_ids = array_unique(array_filter($frontend_routing_ids));
            !$frontend_routing_ids && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '关联所属的前端权限参数不能为空');
            foreach ($frontend_routing_ids as $v) {
                $data = FrontendRoutingCacheModel::getOne($v, 'id,is_delete');
                if (!$data) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '关联所属的前端权限参数错误');
                } elseif ($data['is_delete']) {
                    FrontendRoutingCacheModel::removeRelation($v, 'rel_backend_frontend_routing');
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '关联所属的前端权限参数错误');
                }
            }

            $update = [
                'name' => $name,
                'routing' => $routing,
            ];
            isset($put['description']) && $update['description'] = $description;
            $data = BackendRoutingCacheModel::getOneOrFail($id, implode(',', array_keys($update)).',is_delete');
            $data['is_delete'] && $this->fail(ErrorCode::SYS_DATA_NOT_EXISTS);

            $or = [
                " name = '{$name}' ",
                " routing = '{$routing}' ",
            ];
            $field = [
                '1 as group_by_type',
                "SUM(IF(name='{$name}',1,0)) as name_nums",
                "SUM(IF(routing='{$routing}',1,0)) as routing_nums",
            ];
            $search = reset(BaseModel::getInstance(BackendRoutingCacheModel::getTableName())->getList([
                'field' => implode(',', $field),
                'where' => [
                    '_string' => implode('or', $or),
                    'is_delete' => BackendRoutingService::IS_DELETE_NO,
                    'id' => ['neq', $id],
                ],
                'group' => 'group_by_type',
            ]));

            $search['name_nums'] && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '名称已存在');
            $search['routing_nums'] && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '路由已存在');

            foreach ($update as $k => $v) {
                if ($data[$k] != $v) {
                    continue;
                }
                unset($update[$k]);
            }

            M()->startTrans();
            $update && BackendRoutingCacheModel::update($id, $update);
            BackendRoutingCacheModel::updateFrontendRoutingRelation($id, $frontend_routing_ids);
            M()->commit();
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function addBackendRouting()
    {
        $name = htmlEntityDecode(I('post.name', ''));
        $routing = htmlEntityDecode(I('post.routing', ''));
        $description = htmlEntityDecode(I('post.description', ''));
        $frontend_routing_ids = I('post.frontend_routing_ids', []);
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);

            empty($name) && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '名称不能为空');
            empty($routing) && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '路由不能为空');

            $frontend_routing_ids = array_unique(array_filter($frontend_routing_ids));
            !$frontend_routing_ids && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '关联所属的前端权限参数不能为空');
            foreach ($frontend_routing_ids as $v) {
                $data = FrontendRoutingCacheModel::getOne($v, 'id,is_delete');
                if (!$data) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '关联所属的前端权限参数错误');
                } elseif ($data['is_delete']) {
                    FrontendRoutingCacheModel::removeRelation($v, 'rel_backend_frontend_routing');
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '关联所属的前端权限参数错误');
                }
            }

            $or = [
                " name = '{$name}' ",
                " routing = '{$routing}' ",
            ];
            $field = [
                '1 as group_by_type',
                "SUM(IF(name='{$name}',1,0)) as name_nums",
                "SUM(IF(routing='{$routing}',1,0)) as routing_nums",
            ];
            $search = reset(BaseModel::getInstance(BackendRoutingCacheModel::getTableName())->getList([
                'field' => implode(',', $field),
                'where' => [
                    '_string' => implode('or', $or),
                    'is_delete' => BackendRoutingService::IS_DELETE_NO,
                ],
                'group' => 'group_by_type',
            ]));

            $search['name_nums'] && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '名称已存在');
            $search['routing_nums'] && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '路由已存在');

            $insert = [
                'name' => $name,
                'routing' => $routing,
                'description' => $description,
                'create_time' => NOW_TIME,
            ];

            M()->startTrans();
            $id = BackendRoutingCacheModel::insert($insert);

            $rel_data = [];
            foreach ($frontend_routing_ids as $v) {
                $rel_data[] = [
                    'backend_routing_id' => $id,
                    'frontend_routing_id' => $v,
                ];
            }
            BaseModel::getInstance('rel_backend_frontend_routing')->insertAll($rel_data);
            BackendRoutingCacheModel::addFrontendRoutingRelation($id);

            M()->commit();
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function removeBackendRouting()
    {
        $id = I('get.id', 0);
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
            M()->startTrans();
            $data = BackendRoutingCacheModel::getOneOrFail($id, 'id,is_delete');
            $data['is_delete'] && $this->fail(ErrorCode::SYS_DATA_NOT_EXISTS);

            BackendRoutingCacheModel::remove($id);
            M()->commit();
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getFrontendRoutings()
    {
        $is_show_all = (string)I('get.is_show_all', 0, 'intval');
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);

            $where = [
                'is_delete' => FrontendRoutingService::IS_DELETE_NO,
            ];
            if ($is_show_all !== '1') {
                $where['is_show'] = FrontendRoutingService::IS_SHOW_YES;
            }

            $model = BaseModel::getInstance(FrontendRoutingCacheModel::getTableName());

            $count = $model->getNum($where);

            $list = $count ? $model->getList([
                'field' => 'id,name,is_menu,serial,routing',
                'limit' => getPage(),
                'where' => $where,
            ]) : [];
            $this->paginate($list, $count);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getFrontendRoutingsTree()
    {
        $is_show_all = (string)I('get.is_show_all', 0, 'intval');
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);

            $where = [
                'is_delete' => FrontendRoutingService::IS_DELETE_NO,
            ];
            if ($is_show_all !== '1') {
                $where['is_show'] = FrontendRoutingService::IS_SHOW_YES;
            }

            $list = BaseModel::getInstance(FrontendRoutingCacheModel::getTableName())->getList([
                'field' => 'id,name,is_menu,serial,parent_id,routing',
                'where' => $where,
            ]);

//            // keyArrTreeData 方法效率测试（ $i <= 99999） mac 测试：time-2206ms；size-4.29M
//            $list = [];
//            for ($i = 0;$i <= 99999;$i++) {
//                $nums = count($list);
//                $list[] = [
//                    'id' => $i + 1,
//                    'parent_id' => rand(0, $nums),
//                ];
//            }

            $key_arr = [];
            foreach ($list as $v) {
                $data = $v;
                unset($data['parent_id']);
                $key_arr[$v['parent_id']][] = $data;
            }
            $return = $key_arr[0];
            keyArrTreeData($key_arr, $return);
            $this->responseList($return);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getRoleFrontendRoutings()
    {
        $role_id = I('id', 0);
        $is_show_all = (string)I('get.is_show_all', 0, 'intval');
        try {
//            $this->requireAuth(AuthService::ROLE_ADMIN);
//
//            $role = AdminRoleCacheModel::getOneOrFail($role_id, 'id');
//            $role['is_delete'] && $this->fail(ErrorCode::SYS_DATA_NOT_EXISTS);
//
//            $datas = AdminRoleCacheModel::getRelation($role_id, 'rel_frontend_routing_admin_roles', 'admin_roles_id', 'frontend_routing_id');
//
//            $list = [];
//            foreach ($datas as $v) {
//                $data = FrontendRoutingCacheModel::getOne($v, 'id,name,is_menu,serial,parent_id,is_delete');
//                if (!$data['is_delete']) {
//                    unset($data['is_delete']);
//                    $list[] = $data;
//                }
//            }
//
//            $key_arr = [];
//            foreach ($list as $v) {
//                $data = $v;
//                unset($data['parent_id']);
//                $key_arr[$v['parent_id']][] = $data;
//            }
//            $return = $key_arr[0];
//            keyArrTreeData($key_arr, $return);
//
//            $this->responseList($return);

            $this->requireAuth(AuthService::ROLE_ADMIN);

            $role = AdminRoleCacheModel::getOneOrFail($role_id, 'id');
            $role['is_delete'] && $this->fail(ErrorCode::SYS_DATA_NOT_EXISTS);

            $datas = AdminRoleCacheModel::getRelation($role_id, 'rel_frontend_routing_admin_roles', 'admin_roles_id', 'frontend_routing_id');

            $where = [
                'is_delete' => FrontendRoutingService::IS_DELETE_NO,
            ];
            if ($is_show_all !== '1') {
                $where['is_show'] = FrontendRoutingService::IS_SHOW_YES;
            }

            $list = BaseModel::getInstance(FrontendRoutingCacheModel::getTableName())->getList([
                'field' => 'id,name,is_menu,serial,parent_id,routing',
                'where' => $where,
            ]);

            $key_arr = [];
            foreach ($list as $v) {
                $v['is_check'] = in_array($v['id'], $datas) ? '1' : '0';
                $data = $v;
                unset($data['parent_id']);
                $key_arr[$v['parent_id']][] = $data;
            }
            $return = $key_arr[0];
            keyArrTreeData($key_arr, $return);
            $this->responseList($return);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getFrontendRoutingById()
    {
        $id = I('get.id', 0);
        try {
            $data = FrontendRoutingCacheModel::getOneOrFail($id, 'id,name,is_menu,serial,routing,parent_id,is_show,is_delete');
            $data['is_delete'] && $this->fail(ErrorCode::SYS_DATA_NOT_EXISTS);

            unset($data['is_delete']);
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function addFrontendRouting()
    {
        $name = htmlEntityDecode(I('post.name', ''));
        $routing = htmlEntityDecode(I('post.routing', ''));
        $is_menu = I('post.is_menu', '');
        $serial = I('post.serial', '');
        $parent_id = I('post.parent_id', 0);
        $is_show = I('post.is_show', '');
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);

            empty($name) && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '名称不能为空');
            empty($routing) && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '路由不能为空');

                !in_array($is_menu, FrontendRoutingService::IS_MENU_VALID_ARRAY, true)
            && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择是否是菜单');
                !in_array($is_show, FrontendRoutingService::IS_SHOW_VALID_ARRAY, true)
            && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择是否显示');
            empty($serial) && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '编号不能为空');

            // 名称与编号是必传、父级ID可不选时为顶级
            $or = [
                " name = '{$name}' ",
                " serial = '{$serial}' ",
            ];
            $field = [
                '1 as group_by_type',
                "SUM(IF(name='{$name}',1,0)) as name_nums",
                "SUM(IF(serial='{$serial}',1,0)) as serial_nums",
            ];
            if ($parent_id) {
                $or[] = " id = {$parent_id} ";
                $field[] = "SUM(IF(id={$parent_id},1,0)) as parent_id_nums";
            }
            $search = reset(BaseModel::getInstance(FrontendRoutingCacheModel::getTableName())->getList([
                'field' => implode(',', $field),
                'where' => [
                    '_string' => implode('or', $or),
                    'is_delete' => FrontendRoutingService::IS_DELETE_NO,
                ],
                'group' => 'group_by_type',
            ]));

            $search['name_nums'] && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '名称已存在');
            $search['serial_nums'] && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '编号已存在');
            $parent_id && !$search['parent_id_nums'] && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '父级不存在');

            $insert = [
                'name' => $name,
                'routing' => $routing,
                'is_menu' => $is_menu,
                'serial' => $serial,
                'parent_id' => $parent_id,
                'is_show' => $is_show,
                'create_time' => NOW_TIME,
            ];
            M()->startTrans();
            $frontend_routing_id = FrontendRoutingCacheModel::insert($insert);
            // 给超级管理权限
            if (C('SUPERADMINISTRATOR_ROLES_ID')) {
                AdminRoleCacheModel::addOneFrontendRoutingIdRelation(C('SUPERADMINISTRATOR_ROLES_ID'), $frontend_routing_id);
            }
            M()->commit();
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function updateFrontendRouting()
    {
        $id = I('get.id', 0);
        $name = htmlEntityDecode(I('put.name', ''));
        $routing = htmlEntityDecode(I('put.routing', ''));
        $is_menu = I('put.is_menu', '');
        $serial = I('put.serial', '');
        $parent_id = I('put.parent_id', 0);
        $is_show = I('put.is_show', '');
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);

            empty($name) && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '名称不能为空');
            empty($routing) && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '路由不能为空');

                !in_array($is_menu, FrontendRoutingService::IS_MENU_VALID_ARRAY, true)
            && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择是否是菜单');
                !in_array($is_show, FrontendRoutingService::IS_SHOW_VALID_ARRAY, true)
            && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请选择是否显示');
            empty($serial) && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '编号不能为空');


            $update = [
                'name' => $name,
                'routing' => $routing,
                'is_menu' => $is_menu,
                'serial' => $serial,
                'parent_id' => $parent_id,
                'is_show' => $is_show,
            ];
            $data = FrontendRoutingCacheModel::getOneOrFail($id, implode(',', array_keys($update)).',is_delete');
            $data['is_delete'] && $this->fail(ErrorCode::SYS_DATA_NOT_EXISTS);

            // 名称与编号是必传、父级ID可不选时为顶级
            $or = [
                " name = '{$name}' ",
                " serial = '{$serial}' ",
            ];
            $field = [
                '1 as group_by_type',
                "SUM(IF(name='{$name}',1,0)) as name_nums",
                "SUM(IF(serial='{$serial}',1,0)) as serial_nums",
            ];
            if ($parent_id) {
                $or[] = " id = {$parent_id} ";
                $field[] = "SUM(IF(id={$parent_id},1,0)) as parent_id_nums";
            }
            $search = reset(BaseModel::getInstance(FrontendRoutingCacheModel::getTableName())->getList([
                'field' => implode(',', $field),
                'where' => [
                    '_string' => implode('or', $or),
                    'is_delete' => FrontendRoutingService::IS_DELETE_NO,
                    'id' => ['neq', $id],
                ],
                'group' => 'group_by_type',
            ]));

            $search['name_nums'] && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '名称已存在');
            $search['serial_nums'] && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '编号已存在');
            $parent_id && !$search['parent_id_nums'] && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '父级不存在');

            foreach ($update as $k => $v) {
                if ($data[$k] != $v) {
                    continue;
                }
                unset($update[$k]);
            }

            M()->startTrans();
            $update && FrontendRoutingCacheModel::update($id, $update);
            M()->commit();
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function removeFrontendRouting()
    {
        $id = I('get.id', 0);
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);

            $data = FrontendRoutingCacheModel::getOneOrFail($id, 'id,is_delete');
            $data['is_delete'] && $this->fail(ErrorCode::SYS_DATA_NOT_EXISTS);

            M()->startTrans();
            FrontendRoutingCacheModel::remove($id);
            M()->commit();
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}
