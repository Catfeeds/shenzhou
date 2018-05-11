<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/16
 * Time: 10:06
 */

namespace Common\Common\Service\UserCommonInfoService\User\WorkerOrder\WorkerOrderList;

use Common\Common\Model\BaseModel;
use Common\Common\Service\AuthService;
use Common\Common\Service\FactoryService;
use Common\Common\Service\UserCommonInfoService\UserCommonInfo;

class FactoryAdmin extends UserCommonInfo
{
    public function getId()
    {
        return $this->data['id'];
    }

    public function getPhone()
    {
        return $this->data['tell'];
    }

    public function getName()
    {
        return [
            'name' => $this->data['nickout'],
            'group_name' => AuthService::getModel() == AuthService::ROLE_ADMIN ? FactoryService::getGroupNameByGroupId($this->data['group_id']) : $this->data['group_name'],
        ];
    }

    protected function loadData($id)
    {
        $user = BaseModel::getInstance('factory_admin')->getOne([
            'where' => [
                'factory_admin.id' => $id,
            ],
            'join' => [
                'LEFT JOIN factory_adtags ON factory_adtags.id=factory_admin.tags_id',
                'LEFT JOIN factory On factory.factory_id=factory_admin.factory_id',
            ],
            'field' => 'factory_admin.id,tell,nickout,tags_id,factory_adtags.name group_name,factory.group_id'
        ]);
        if ($user['tags_id'] == 0) {
            $user['group_name'] = FactoryService::FACTORY_INTERNAL_GROUP['name'];
        }
        return $user;
    }

}