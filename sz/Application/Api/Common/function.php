<?php
/**
 * Created by PhpStorm.
 * User: 3N
 * Date: 2017/12/27
 * Time: 17:25
 */

function connectRedis()
{
    $redis = new \Redis();
    $redis->connect(C("REDIS_HOST"), C("REDIS_PORT"));
    return $redis;
}