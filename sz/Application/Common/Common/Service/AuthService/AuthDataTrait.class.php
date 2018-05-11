<?php
/**
 * File: AuthDataTrait.class.php
 * User: xieguoqiu
 * Date: 2016/11/22 15:15
 */

namespace Common\Common\Service\AuthService;

Trait AuthDataTrait
{

    public $data;

    public function __get($name)
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        } else {
            return null;
        }
    }

    public function __set($key, $value)
    {
        $this->data[$key] = $value;
    }

    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->data[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }
    
}
