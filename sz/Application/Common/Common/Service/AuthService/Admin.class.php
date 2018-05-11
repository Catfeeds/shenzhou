<?php
/**
 * File: Admin.class.php
 * User: xieguoqiu
 * Date: 2017/4/8 23:22
 */

namespace Common\Common\Service\AuthService;

use Common\Common\CacheModel\AdminCacheModel;
use Common\Common\Model\BaseModel;

class Admin extends Auth implements \ArrayAccess
{

    use AuthDataTrait;

    public function loadData($id)
    {
//        $field = BaseModel::getInstance('admin')->getOne($id);
//        die(implode(',', array_keys($field)));
        $this->data = AdminCacheModel::getOne($id, 'id,user_name,nickname,nickout,tell,tell_out,password,add_time,thumb,sig,sig_expire,role_id,last_login_ip,last_login_time,state,group_id,agent');
//        $this->data = D('Admin')->where(['id' => $id])->find();
    }
}
