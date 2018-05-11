<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/16
 * Time: 09:59
 */

namespace Common\Common\Service\UserCommonInfoService\User\WorkerOrder\WorkerOrderMessage;

use Common\Common\Model\BaseModel;
use Common\Common\Service\AuthService;
use Common\Common\Service\FactoryService;
use Common\Common\Service\UserCommonInfoService\UserCommonInfo;
use Library\Common\Util;

class Factory extends UserCommonInfo
{
    public function getId()
    {
        return $this->data['factory_id'];
    }

    public function getPhone()
    {
        return $this->data['linkphone'];
    }

    public function getName()
    {
        return [
            'name' => AuthService::getModel() == AuthService::ROLE_FACTORY ? $this->data['linkman']: $this->data['factory_full_name'].'-'.$this->data['linkman'],
            'group_name' => AuthService::getModel() == AuthService::ROLE_FACTORY ? FactoryService::getGroupNameByGroupId($this->data['group_id']) : FactoryService::FACTORY_INTERNAL_GROUP['name'],
        ];
    }

    public function getThumb()
    {
        $path = $this->data['factory_logo']?? C('ORDER_MESSAGE_THUMB.FACTORY');
        return preg_match('#^https?:\/\/#', $path)? $path: Util::getServerFileUrl($path);
    }

    protected function loadData($id)
    {
        return BaseModel::getInstance('factory')->getOne($id, 'factory_id,linkman,group_id,linkphone,factory_short_name,factory_full_name');
    }

}