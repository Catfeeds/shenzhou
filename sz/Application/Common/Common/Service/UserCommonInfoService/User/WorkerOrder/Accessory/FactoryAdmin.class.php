<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/16
 * Time: 10:06
 */

namespace Common\Common\Service\UserCommonInfoService\User\WorkerOrder\WorkerOrderMessage;

use Common\Common\Model\BaseModel;
use Common\Common\Service\AuthService;
use Common\Common\Service\FactoryService;
use Common\Common\Service\UserCommonInfoService\UserCommonInfo;
use Library\Common\Util;

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
            'name' => AuthService::getModel() == AuthService::ROLE_FACTORY_ADMIN ? $this->data['nickout']: $this->data['factory_short_name'].'-'.$this->data['nickout'],
            'group_name' => AuthService::getModel() == AuthService::ROLE_FACTORY_ADMIN ? FactoryService::getGroupNameByGroupId($this->data['group_id']) : $this->data['group_name'],
        ];
    }

    public function getThumb()
    {
        $path = $this->data['factory_logo']?? C('ORDER_MESSAGE_THUMB.FACTORY');
        return preg_match('#^https?:\/\/#', $path)? $path: Util::getServerFileUrl($path);
    }

    protected function loadData($id)
    {
        return BaseModel::getInstance('factory_admin')->getOne([
            'where' => [
                'factory_admin.id' => $id,
            ],
            'join' => [
                'LEFT JOIN factory_adtags ON factory_adtags.id=factory_admin.tags_id',
                'LEFT JOIN factory On factory.factory_id=factory_admin.factory_id',
            ],
            'field' => 'factory_admin.id,tell,nickout,factory_adtags.name group_name,factory.group_id,factory_short_name'
        ]);
    }

}