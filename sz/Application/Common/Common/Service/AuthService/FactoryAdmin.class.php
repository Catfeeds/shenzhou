<?php
/**
 * File: FactoryAdmin.class.php
 * User: xieguoqiu
 * Date: 2017/4/8 23:21
 */


namespace Common\Common\Service\AuthService;

use Common\Common\CacheModel\FactoryAdminCacheModel;
use Common\Common\Model\BaseModel;

class FactoryAdmin extends Auth implements \ArrayAccess
{

    use AuthDataTrait;

    public $primary_key = 'id';

    public function loadData($id)
    {
//        $field = BaseModel::getInstance('factory_admin')->getOne($id);
//        die(implode(',', array_keys($field)));
        $this->data = FactoryAdminCacheModel::getOne($id, 'id,factory_id,tags_id,user_name,nickname,nickout,tell,tell_out,password,add_time,thumb,role_id,last_login_ip,last_login_time,state,is_delete');
//        $this->data = BaseModel::getInstance('factory_admin')->where(['id' => $id])->find();
    }
}
