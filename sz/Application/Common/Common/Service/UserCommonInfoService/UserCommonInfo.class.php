<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/15
 * Time: 17:34
 */

namespace Common\Common\Service\UserCommonInfoService;

use Common\Common\ResourcePool\RedisPool;

abstract class UserCommonInfo implements \ArrayAccess
{
    protected $data;

    protected static $map = [];

    /**
     * UserCommonInfo constructor.
     * @param string|int $user_id
     */
    public function __construct($user_id)
    {
        $this->getData($user_id);
    }

    abstract public function getId();
    abstract public function getPhone();
    abstract public function getName();
    abstract protected function loadData($id);

    public function getData($id)
    {
        $cache_key = 'user_simple_info:' . substr(md5(static::class), 0, 6) . ':' . $id;

        if (isset(self::$map[$cache_key])) {
            $this->data = self::$map[$cache_key];
        } elseif ($id == 0) {
            self::$map[$cache_key] = $this->data = [];
        } else {
            $this->data = RedisPool::getInstance()->hGetAll($cache_key);
            if (!$this->data) {
                $this->data = $this->loadData($id);
                if ($this->data) {
                    RedisPool::getInstance()->hMset($cache_key, $this->data);
                    RedisPool::getInstance()->expire($cache_key,86400 * 3);
                }
            }
            self::$map[$cache_key] = $this->data;
        }

        return $this->data;
    }

    public function __get($name)
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        } else {
            return null;
        }
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