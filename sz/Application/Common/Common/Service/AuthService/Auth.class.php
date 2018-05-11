<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/7/26
 * Time: 下午4:08
 */

namespace Common\Common\Service\AuthService;


abstract class Auth
{

    protected $primary_key = 'id';

    abstract public function loadData($id);

    public function getPrimaryValue()
    {
        $pk = $this->primary_key;
        return $this->$pk;
    }

}