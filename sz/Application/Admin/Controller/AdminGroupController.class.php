<?php
/**
 * Created by PhpStorm.
 * User: seanzen
 * Date: 2018/2/9
 * Time: 16:04
 */
namespace Admin\Controller;

use Admin\Common\ErrorCode;
use Admin\Logic\AdminGroupLogic;
use Common\Common\CacheModel\AdminGroupCacheModel;
use Common\Common\Service\AdminGroupService;
use Common\Common\Service\AuthService;
use Admin\Model\BaseModel;

class AdminGroupController extends \Admin\Controller\BaseController
{
    public function getAllList()
    {
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);
            $where = [];
            $field = 'id,name';
            $list = BaseModel::getInstance(AdminGroupCacheModel::getTableName())->getList([
                'field' => $field,
                'where' => $where,
                'order' => 'create_time desc',
            ]);
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

            $model = BaseModel::getInstance(AdminGroupCacheModel::getTableName());
            $nums = $model->getNum($where);
            !$nums && $this->paginate();

            $field = 'id,name,is_disable,create_time';
            $list = $model->getList([
                'field' => $field,
                'where' => $where,
                'limit' => getPage(),
                'order' => 'create_time desc',
            ]);
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
            $field = 'id,name,is_disable,create_time,update_time';
            $info = AdminGroupCacheModel::getOneOrFail($id, $field);
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

            empty($name) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '参数组别名称不能为空');
                !in_array($is_disable, AdminGroupService::IS_DISABLE_VALID_ARRAY, true)
            &&  $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '参数组别状态不存在');

            AdminGroupCacheModel::getOneOrFail($id, 'id');
            $nums = BaseModel::getInstance(AdminGroupCacheModel::getTableName())->getNum([
                'name' => $name,
                'id' => ['neq', $id],
            ]);
            $nums && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '组别名称已存在');

            $update = [
                'name' => $name,
                'is_disable' => $is_disable,
                'update_time' => NOW_TIME,
            ];
            M()->startTrans();
            AdminGroupCacheModel::update($id, $update);
            M()->commit();
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

            empty($name) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '参数组别名称不能为空');
                !in_array($is_disable, AdminGroupService::IS_DISABLE_VALID_ARRAY, true)
            &&  $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '参数组别状态不存在');

            $nums = BaseModel::getInstance(AdminGroupCacheModel::getTableName())->getNum(['name' => $name]);
            $nums && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '组别名称已存在');

            $insert = [
                'name' => $name,
                'is_disable' => $is_disable,
                'create_time' => NOW_TIME,
                'update_time' => NOW_TIME,
            ];

            M()->startTrans();
            AdminGroupCacheModel::insert($insert);
            M()->commit();
            $this->response();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function ownGroup()
    {
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);

            $groups = (new AdminGroupLogic())->ownGroup();

            $this->responseList($groups);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function groupMember()
    {
        try {
            $this->requireAuth(AuthService::ROLE_ADMIN);


            $members = (new AdminGroupLogic())->groupMember();

            $this->paginate($members[0], $members[1]);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}