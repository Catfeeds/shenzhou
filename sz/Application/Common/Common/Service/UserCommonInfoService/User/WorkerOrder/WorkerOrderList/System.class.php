<?php
/**
 * File: SystemUserInfo.class.php
 * User: sakura
 * Date: 2017/11/12
 */

namespace Common\Common\Service\UserCommonInfoService\User\WorkerOrder\WorkerOrderList;


use Common\Common\Service\UserCommonInfoService\UserCommonInfo;

class System extends UserCommonInfo
{

    public function getId()
    {
        return 0;
    }

    public function getPhone()
    {
        return '';
    }

    public function getName()
    {
        return [
            'name' => '',
            'group_name' => '',
        ];
    }

    protected function loadData($id)
    {
        return [];
    }
}