<?php
/**
 * File: OrderController.class.php
 * User: xieguoqiu
 * Date: 2017/4/5 15:09
 */

namespace Admin\Controller;

use Admin\Common\ErrorCode;
use Common\Common\CacheModel\FactoryAdminCacheModel;
use Common\Common\Service\AuthService;
use Admin\Model\BaseModel;
use Admin\Logic\LoginServiceLogic;
use Common\Common\Service\FactoryService;
use Library\Crypt\AuthCode;
use Library\Common\Util;
use Think\Auth;

class FactoryAdminController extends BaseController
{
    public function addRole()
    {
        try {
            $factory_id = $this->requireAuthFactoryGetFid();
            $name   = empty(I('name')) ? '' : I('name');
            $status = empty(I('status')) ? 0 : I('status');
            if (empty($name)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '角色名称不能为空');
            }
            $data = [];
            $data['name'] = $name;
            $data['status'] = $status;
            $data['factory_id'] = $factory_id;

            $data['factory_id'] = $factory_id;
            BaseModel::getInstance('factory_adrole')->insert($data);
            $this->response();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function editRole()
    {
        try {
            $factory_id = $this->requireAuthFactoryGetFid();
            $name   = empty(I('put.name')) ? '' : I('put.name');
            $status = empty(I('put.status')) ? 0 : I('put.status');

            $data = [];
            $data['name']   = $name;
            $data['status'] = $status;
            $data['factory_id'] = $factory_id;
            $data['id'] = I('put.id', 0);
            if (empty($data['id'])) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }
            BaseModel::getInstance('factory_adrole')->update($data['id'], $data);
            $this->response();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getOneRole()
    {
        try {
            $this->requireAuth();
            $id = I('get.id', 0);
            if (empty($id)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }
            $role_info = BaseModel::getInstance('factory_adrole')->getOne([
                'where' => [
                    'id' => $id,
                    'is_delete' => 0,
                ],
                'field' => 'id, name, status',
            ]);

            $this->response($role_info);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function roleList()
    {
        try {
            $factory_id = $this->requireAuthFactoryGetFid();

            $condition = [];
            $condition['factory_id'] = $factory_id;
            $condition['is_delete']  = 0;

            //系统管理员
            $default_tags = [];
            $default_tags['id'] = -1;
            $default_tags['factory_id'] = $factory_id;
            $default_tags['name'] = '系统管理员';
            $role_list = BaseModel::getInstance('factory_adrole')->getList([
                'where' => $condition,
                'order' => 'id desc',
                'field' => 'id,factory_id,name,status,is_delete'
            ]);
            $role_list = empty($role_list) ? [] : $role_list;

            array_unshift($role_list, $default_tags);
            array_multisort(array_column($role_list,'id'),SORT_DESC, $role_list);
            $this->responseList($role_list);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }
    //角色权限
    public function roleAuth()
    {
        try {
            $role_id = I('role_id', 0);
            $roleRole_list = BaseModel::getInstance('factory_adcess')->getList([
                'where' => ['role_id' => $role_id],
                'field' => 'role_id,node_id,level'
            ]);
            $nodes = [];
            foreach ($roleRole_list as $val) {
                $nodes[]=$val['node_id'];
            }

            $data = [];
            $data['roleRole_list'] = $roleRole_list;
            $data['nodes'] = $nodes;

            $this->response($data);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    //厂家权限节点列表
    public function authList()
    {
        try {
            $auth_list = BaseModel::getInstance('factory_adnode')->getList([
                'field' => 'id,name,title,status,pid,level'
            ]);
            $node_list = getNodeTree($auth_list);

            $this->responseList($node_list);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function setAccess()
    {
        try {
            $role_id = empty(I('get.role_id')) ? 0 : I('get.role_id');
            $nodes   = empty(I('get.nodes')) ? 0 : I('get.nodes');
            $res = BaseModel::getInstance('factory_adcess')->getNum($role_id);
            if (!empty($res)) {
                $where['role_id'] = $role_id;
                BaseModel::getInstance('factory_adcess')->remove($where);
            } else {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }
            $nodes = array_filter(explode(',', $nodes));
            $dataAcc = $dataAccs = [];
            foreach ($nodes as $k => $v) {
                $dataAcc['role_id'] = $role_id;
                $dataAcc['node_id'] = $v;
                $dataAccs[] = $dataAcc;
            }
            if (!empty($nodes)) {
                BaseModel::getInstance('factory_adcess')->insertAll($dataAccs);
            }
            $this->response();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    //添加厂家组别
    public function addTags()
    {
        try {
            $factory_id = $this->requireAuthFactoryGetFid();
            $name = I('post.name', 0);
            $dataAdd = [];
            $dataAdd['factory_id'] = $factory_id;
            $dataAdd['name'] = $name;
            $dataAdd['addtime'] = time();
            $check = BaseModel::getInstance('factory_adtags')->getOne(['name' => $name, 'factory_id' => $factory_id ]);
            if (!empty($check)) {
                $this->fail(ErrorCode::CHECK_IS__EXIST, '该组别已经存在');
            }

            BaseModel::getInstance('factory_adtags')->insert($dataAdd);

            $this->response();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    //编辑厂家组别
    public function editTags()
    {
        try {
            $factory_id = $this->requireAuthFactoryGetFid();
            $id   = I('get.id', 0);
            $name = I('get.name', 0);
            $dataAdd = [];
            $dataAdd['factory_id'] = $factory_id;
            $dataAdd['name'] = $name;
            $dataAdd['addtime'] = time();
            $dataAdd['id'] = $id;
            $dataAdd['name'] = $name;
            if (empty($id)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }
            BaseModel::getInstance('factory_adtags')->update($id, $dataAdd);
            $this->response();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    //获取厂家一个组别信息
    public function getTag()
    {
        try {
            $this->requireAuth();
            $id = I('get.id');
            if (empty($id)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }
            $res = BaseModel::getInstance('factory_adtags')->getOne([
                'where' => ['id' => $id],
                'field' => 'id,factory_id,name,addtime'
            ]);
            $res['addtime'] = date('Y-m-d H:i', $res['addtime']);
            $this->response($res);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    //删除一个厂家组别
    public function deleteTag()
    {
        try {
            $id   = I('get.id', 0);
            $data = [];
            $data['is_delete'] = 1;
            if (empty($id)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }
            BaseModel::getInstance('factory_adtags')->update($id, $data);
            $this->response();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    //获取厂家标签  组别列表
    public function tagsLists()
    {
        try {
            $factory_id = $this->requireAuthFactoryGetFid();
            $condition = [];
            $condition['factory_id'] = $factory_id;
//            $condition['factory_id'] = '1';
            $condition['is_delete']  = 0;

            $count = BaseModel::getInstance('factory_adtags')->getNum($condition);
            $factory_tags = BaseModel::getInstance('factory_adtags')->getList([
                'where' => $condition,
                'limit' => getPage(),
                'field' => 'id,name',
                'order' => 'id DESC'
            ]);
            $factory_tags = empty($factory_tags) ? [] : $factory_tags;


            array_unshift($factory_tags, FactoryService::FACTORY_INTERNAL_GROUP);
            array_multisort(array_column($factory_tags,'id'),SORT_DESC, $factory_tags);
            $count++;
            $this->paginate($factory_tags, $count);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function getAllTags()
    {
        try {
            $allow_role = [AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN, AuthService::ROLE_ADMIN];

            $userId = $this->requireAuth($allow_role);
            $role = AuthService::getModel();
            $admin = AuthService::getAuthModel();

            $where = ['is_delete' => 0];
            if (AuthService::ROLE_ADMIN == $role) {
                $factory_id = I('factory_id', 0, 'intval');
                if ($factory_id <= 0) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
                }
                $where['factory_id'] = $factory_id;
            } elseif (AuthService::ROLE_FACTORY == $role) {
                $where['factory_id'] = $userId;
            } elseif (AuthService::ROLE_FACTORY_ADMIN == $role) {
                $where['factory_id'] = $admin['factory_id'];
            }

            $factory_tags = BaseModel::getInstance('factory_adtags')->getList([
                'where' => $where,
                'field' => 'id,name',
                'order' => 'id DESC'
            ]);

            array_unshift($factory_tags, FactoryService::FACTORY_INTERNAL_GROUP);

            $this->responseList($factory_tags);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    //添加厂家子账号
    public function addAdmin()
    {
        try {
            $this->requireAuth('factory');
            $factory_id = AuthService::getAuthModel()->getPrimaryValue();
            $data = [];
            $data['linkphone'] = trim(I('post.tell', 0));
            if (!Util::isPhone($data['linkphone'])) {
                $this->throwException(ErrorCode::PHONE_GS_IS_WRONG);
            }
            //查找厂家有没有同名 用户
            //编辑的时候查重  排除自己
            $is_exist = BaseModel::getInstance('factory')->getNum($data);
            if ($is_exist > 0) {
                $this->fail( ErrorCode::CHECK_IS__EXIST, '账号已存在');
            }

            //查找厂家子账号有没有同名 用户
            $data  =[];
            $data['tell'] = trim(I('post.tell', 0));
            $data['is_delete'] = 0;
            if (!empty(I('id'))) {
                $data['id'] = array('neq', trim(I('post.id', 0)));
            }

            $is_exist = BaseModel::getInstance('factory_admin')->getNum($data);
            if ($is_exist > 0) {
                $this->fail( ErrorCode::CHECK_IS__EXIST, '子账号已存在');
            }
            unset($data['id']);

            $data['thumb'] = trim(I('post.thumb', 0));
            $data['state'] = I('state');

            $data['role_id'] = empty(I('role_id')) ? 0 : I('role_id');
            $data['nickout'] = empty(I('nickout')) ? '' : I('nickout');
            $data['tell'] = empty(I('tell')) ? '' : I('tell');
            $data['tell_out'] = empty(I('tell_out')) ? '' : I('tell_out');
            $data['user_name'] = empty(I('user_name')) ? '' : I('user_name');
            $data['factory_id'] = $factory_id;
            $data['tags_id'] = empty(I('tags_id')) ? 0 : I('tags_id');
            $pass = substr(I('tell'), 5);
            $data['password'] = md5($pass);
            $data['add_time'] = time();
            BaseModel::getInstance('factory_admin')->insert($data);
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    //编辑厂家子账号
    public function editAdmin()
    {
        try {
            $this->requireAuth('factory');
            $factory_id = AuthService::getAuthModel()->getPrimaryValue();
            $data = [];
            $data['linkphone'] = trim(I('get.tell', 0));
            if (!Util::isPhone($data['linkphone'])) {
                $this->throwException(ErrorCode::PHONE_GS_IS_WRONG);
            }
            //查找厂家子账号有没有同名 用户
            $data =[];
            $data['tell'] = trim(I('get.tell', 0));
            $data['thumb'] = trim(I('get.thumb', 0));
            $data['state'] = I('get.state');

            $data['role_id'] = empty(I('get.role_id')) ? 0 : I('get.role_id');
            $data['nickout'] = empty(I('get.nickout')) ? '' : I('get.nickout');
            $data['tell'] = empty(I('get.tell')) ? '' : I('get.tell');
            $data['tell_out'] = empty(I('get.tell_out')) ? '' : I('get.tell_out');
            $data['user_name'] = empty(I('get.user_name')) ? '' : I('get.user_name');
            $data['factory_id'] = $factory_id;
            $data['tags_id'] = empty(I('get.tags_id')) ? 0 : I('get.tags_id');
            $data['id'] = I('get.id', 0);
            if (empty(I('get.id', 0))) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }

            FactoryAdminCacheModel::update(I('get.id'), $data);
//            BaseModel::getInstance('factory_admin')->update(I('get.id'), $data);
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    //删除厂家子账号
    public function delAdmin()
    {
        try {
            $this->requireAuth('factory');
            $id = intval(I('get.id', 0));
            if (empty($id)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }
            FactoryAdminCacheModel::remove($id);
//            $data['is_delete'] = 1;
//            BaseModel::getInstance('factory_admin')->update($id, $data);
            $this->response();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    //厂家子账号列表
    public function adminList()
    {
        try {
            $factory_id = $this->requireAuthFactoryGetFid();

            $condition = [];
            $condition['is_delete'] = 0;
            $condition['factory_id'] = $factory_id;
//            $condition['factory_id'] = 1;

            if (!empty(I('tell'))) {
                $condition['tell'] = array('like', '%'.trim(I('tell')).'%');
            }

            if (!empty(I('nickout'))) {
                $condition['nickout'] = array('like', '%'.trim(I('nickout')).'%');
            }

            if (!empty(I('role_id')) && I('role_id') != -1) {
                $condition['role_id'] = I('role_id', 0);
            }

            if (I('role_id') == -1) {
                $condition['role_id'] = 0;
            }
            $count = BaseModel::getInstance('factory_admin')->getNum($condition);
            $list  = BaseModel::getInstance('factory_admin')->getList([
                'where' => $condition,
                'limit' => getPage(),
                'order' => 'id desc',
                'field' => 'id, nickout, tell, role_id, tags_id, state, add_time, last_login_time'
            ]);
            $role_id = $tags_id = [];
            foreach ($list as $key => $val) {
                $role_id[] = $val['role_id'];
                $tags_id[] = $val['tags_id'];
            }
            if (!empty($role_id)) {
                $in_role_id = ['in', $role_id];
            } else {
                $in_role_id = 0;
            }

            if (!empty($tags_id)) {
                $in_tags_id = ['in', $tags_id];
            } else {
                $in_tags_id = 0;
            }

            $admin_role = BaseModel::getInstance('factory_adrole')->getList([
                'where' => ['id' => $in_role_id],
                'field' => 'id, name',
                'index' => 'id',
            ]);
            $admin_group = BaseModel::getInstance('factory_adtags')->getList([
                'where' => ['id' => $in_tags_id],
                'field' => 'id,name',
                'index' => 'id',
            ]);

            foreach ($list as $k => $v) {
                $v['admin_role'] = $admin_role[$v['role_id']];
                if ($v['admin_role'] == 0) {
                    $v['admin_role']['name'] = '系统管理员';
                }
                $v['admin_group'] = $admin_group[$v['tags_id']];

                if (empty($v['admin_group']['name'])) {
                    $v['admin_group']['name'] = FactoryService::FACTORY_INTERNAL_GROUP['name'];
                }
                $v['add_time'] = date('Y-m-d H:i:s', $v['add_time']);
                $v['last_login_time'] = date('Y-m-d H:i:s', $v['last_login_time']);
                unset($v['admin_role']['id']);
                unset($v['admin_group']['id']);
                $list[$k] = $v;
            }
            $this->paginate($list, $count);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    //厂家子账号信息
    public function adminInfo()
    {
        try {
            $this->requireAuth([AuthService::ROLE_FACTORY]);
            $id = I('get.id', 0);
            if (empty($id)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }
            $admin_info = BaseModel::getInstance('factory_admin')->getOneOrFail([
                'where' => ['id' => $id],
                'field' => 'id, nickout, tell, tell_out, role_id, tags_id, state, add_time, last_login_time'
            ]);
            $admin_info['add_time'] = date('Y-m-d H:i', $admin_info['add_time']);
            if (!empty($admin_info['last_login_time'])) {
                $admin_info['last_login_time'] = date('Y-m-d H:i', $admin_info['last_login_time']);
            }
            $this->response($admin_info);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

}
