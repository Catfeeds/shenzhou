<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/16
 * Time: 10:06
 */

namespace Common\Common\Service\UserCommonInfoService\User;

use Common\Common\CacheModel\FactoryAdminCacheModel;
use Common\Common\Model\BaseModel;
use Common\Common\Service\AuthService;
use Common\Common\Service\UserCommonInfoService\UserCommonInfo;

class FactoryAdmin extends UserCommonInfo
{
    public function getId()
    {
        return $this->data['factory_id'];
    }

    public function getPhone()
    {
        return $this->data['tell'];
    }

    public function getName()
    {
        return $this->data['nickout'];
//        return AuthService::getModel() == AuthService::ROLE_ADMIN ? $this->data['factory_full_name'] : $this->data['linkman'];
    }

    protected function loadData($id)
    {
        return FactoryAdminCacheModel::getOne($id, 'id,factory_id,tell,nickout');
//        return BaseModel::getInstance('factory_admin')->getOne($id, 'id,factory_id,tell,nickout');
//        $factory = BaseModel::getInstance('factory')->getOne($factory_admin['factory_id'], 'factory_id,linkman,factory_full_name,linkphone');

    }

}