<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/16
 * Time: 10:07
 */

namespace Common\Common\Service\UserCommonInfoService\User\WorkerOrder\WorkerOrderList;

use Common\Common\CacheModel\WxUserCacheModel;
use Common\Common\Model\BaseModel;
use Common\Common\Service\UserCommonInfoService\UserCommonInfo;

class WxUser extends UserCommonInfo
{
    public function getId()
    {
        return $this->data['id'];
    }

    public function getPhone()
    {
        return $this->data['telephone'];
    }

    public function getName()
    {
        return [
            'name' => $this->data['user_type'] == 1 ? $this->data['real_name'] : $this->data['nickname'],
            'group_name' => '',
        ];
    }

    protected function loadData($id)
    {
        return WxUserCacheModel::getOne($id, 'id,telephone,real_name,nickname,user_type');
//        return BaseModel::getInstance('wx_user')->getOne($id, 'id,telephone,real_name,nickname,user_type');
    }

}