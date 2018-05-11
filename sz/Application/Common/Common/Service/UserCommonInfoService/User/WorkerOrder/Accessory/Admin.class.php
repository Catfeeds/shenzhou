<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/15
 * Time: 18:30
 */

namespace Common\Common\Service\UserCommonInfoService\User\WorkerOrder\WorkerOrderMessage;

use Common\Common\CacheModel\AdminCacheModel;
use Common\Common\Model\BaseModel;
use Common\Common\Service\AuthService;
use Common\Common\Service\UserCommonInfoService\UserCommonInfo;

class Admin extends UserCommonInfo
{
    public function getId()
    {
        return $this->data['id'];
    }

    public function getPhone()
    {
        return AuthService::getModel() == AuthService::ROLE_ADMIN ? $this->data['tell'] : $this->data['tell_out'];
    }

    public function getName()
    {
        return AuthService::getModel() == AuthService::ROLE_ADMIN ? $this->data['nickout']: $this->data['user_name'];
    }

    protected function loadData($id)
    {
        return AdminCacheModel::getOne($id, 'id,user_name,nickout,tell,tell_out');
//        return BaseModel::getInstance('admin')
//            ->getOne($id, 'id,user_name,nickout,tell,tell_out');
    }

}