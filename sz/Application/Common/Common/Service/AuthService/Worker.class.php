<?php
/**
 * File: WxUser.class.php
 * User: zjz
 * Date: 2017/01/24
 */

namespace Common\Common\Service\AuthService;

class Worker extends Auth implements \ArrayAccess
{
    
    use AuthDataTrait;

    public $primary_key = 'worker_id';

    public function loadData($id)
    {
        $this->data = D('Worker')->where(['worker_id' => $id])->find();
    }
}
