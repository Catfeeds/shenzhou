<?php

namespace Library\Redis;

use Common\Common\ErrorCode;
use Think\Cache\Driver\Redis;

class RedLock
{
    private $retryDelay;
    private $retryCount;
    private $clockDriftFactor = 0.01;

    private $instance = null;

    function __construct($retryDelay = 100, $retryCount = 5)
    {
        $this->retryDelay = $retryDelay;
        $this->retryCount = $retryCount;

    }

    public function lock($resource, $ttl)
    {
        $this->initInstances();

        $token = uniqid();
        $retry = $this->retryCount;

        do {
            $startTime = microtime(true) * 1000;

            $this->lockInstance($resource, $token, $ttl);

            # Add 2 milliseconds to the drift to account for Redis expires
            # precision, which is 1 millisecond, plus 1 millisecond min drift
            # for small TTLs.
            $drift = ($ttl * $this->clockDriftFactor) + 2;

            $validityTime = $ttl - (microtime(true) * 1000 - $startTime) - $drift;
            if ($validityTime > 0) {
                return [
                    'validity' => $validityTime,
                    'resource' => $resource,
                    'token'    => $token,
                ];

            }

            // Wait a random delay before to retry
            $delay = mt_rand(floor($this->retryDelay / 2), $this->retryDelay);
            usleep($delay);

            $retry--;

        } while ($retry > 0);

        throw new \Exception('加锁失败', ErrorCode::SYS_REDIS_LOCK_FAIL);

    }

    public function unlock(array $lock)
    {
        $this->initInstances();
        $resource = $lock['resource'];
        $token = $lock['token'];

        $this->unlockInstance($resource, $token);
    }

    private function initInstances()
    {
        if (empty($this->instances)) {
            $this->instance = new Redis() ; //补充 RedisPool
        }
    }

    private function lockInstance($resource, $token, $ttl)
    {
        return $this->instance->set($resource, $token, ['NX', 'PX' => $ttl]);
    }

    private function unlockInstance($resource, $token)
    {
        $script
            = '
            if redis.call("GET", KEYS[1]) == ARGV[1] then
                return redis.call("DEL", KEYS[1])
            else
                return 0
            end
        ';

        return $this->instance->eval($script, [$resource, $token], 1);
    }
}
