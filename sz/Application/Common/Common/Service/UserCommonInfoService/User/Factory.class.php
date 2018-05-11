<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/16
 * Time: 09:59
 */

namespace Common\Common\Service\UserCommonInfoService\User;

use Common\Common\Model\BaseModel;
use Common\Common\Service\UserCommonInfoService\UserCommonInfo;

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
        return $this->data['linkman'];
    }

    protected function loadData($id)
    {
        return BaseModel::getInstance('factory')->getOne($id, 'factory_id,linkman,linkphone');
    }

}