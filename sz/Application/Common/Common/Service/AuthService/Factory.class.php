<?php
/**
 * File: Factory.class.php
 * User: xieguoqiu
 * Date: 2017/4/8 23:21
 */


namespace Common\Common\Service\AuthService;

class Factory extends Auth implements \ArrayAccess
{

    use AuthDataTrait;

    public $primary_key = 'factory_id';

    public function loadData($id)
    {
        $this->data = D('Factory')->where(['factory_id' => $id])->find();
    }
}
