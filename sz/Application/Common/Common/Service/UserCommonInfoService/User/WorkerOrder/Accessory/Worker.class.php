<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/16
 * Time: 10:07
 */

namespace Common\Common\Service\UserCommonInfoService\User\WorkerOrder\WorkerOrderMessage;

use Common\Common\Model\BaseModel;
use Common\Common\Service\UserCommonInfoService\UserCommonInfo;

class Worker extends UserCommonInfo
{
    public function getId()
    {
        return $this->data['worker_id'];
    }

    public function getPhone()
    {
        return $this->data['worker_telephone'];
    }

    public function getName()
    {
        return $this->data['nickname'];
    }

    protected function loadData($id)
    {
        return BaseModel::getInstance('worker')->getOne($id, 'worker_id,nickname,worker_telephone');
    }

}