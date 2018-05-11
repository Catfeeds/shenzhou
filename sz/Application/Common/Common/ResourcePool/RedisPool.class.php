<?php

namespace Common\Common\ResourcePool;

use Think\Cache\Driver\Redis;

class RedisPool extends ResourcePool
{

    private static $redis_obj;

    public static function getInstance($is_new_instance = false)
    {
        if ($is_new_instance) {
            return static::getNewInstance();
        } elseif (!self::$redis_obj) {
            self::$redis_obj = static::getNewInstance();
        }

        return self::$redis_obj;
    }


    protected static function getNewInstance()
    {
        $options = [
            'host' => C('REDIS_HOST') ? : '127.0.0.1',
            'port' => C('REDIS_PORT') ? : 6379,
            'timeout' => C('DATA_CACHE_TIMEOUT') ? : false,
            'auth' => C('REDIS_AUTH_PASSWORD') ? C('REDIS_AUTH_PASSWORD') : null,//auth认证的密码
            'persistent' => false,
        ];

        $options['expire'] = isset($options['expire'])?  $options['expire']  :   C('DATA_CACHE_TIME');
        $options['prefix'] = isset($options['prefix'])?  $options['prefix']  :   C('DATA_CACHE_PREFIX');
        $options['length'] = isset($options['length'])?  $options['length']  :   0;
        $func = $options['persistent'] ? 'pconnect' : 'connect';
        $handler = new \Redis;
        $options['timeout'] === false ?
            $handler->$func($options['host'], $options['port']) :
            $handler->$func($options['host'], $options['port'], $options['timeout']);

        if($options['auth'] !== null)
        {
            $handler->auth($options['auth']); //说明有配置redis的认证配置密码 需要认证一下
        }

        return $handler;
    }

}