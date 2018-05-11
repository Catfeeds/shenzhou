<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/21
 * Time: 12:01
 */

namespace Library\Queue\Driver;

use Admin\Logic\OrderLogic;
use Common\Common\Model\BaseModel;
use Common\Common\ResourcePool\RedisPool;
use Library\Queue\Driver;
use Library\Queue\Queue;
use Think\Model;

class Redis implements Driver
{
    protected $handler;

    protected $options = [];

    protected $queue;

    /**
     * Redis constructor.
     */
    public function __construct()
    {
        $this->handler = RedisPool::getInstance(true);
    }

    public function work($queue_name)
    {
        $queue_key = self::getQueueName($queue_name);
        while(true) {
            $message = $this->handler->brpop($queue_key, 30);
            /**
             * @var Queue $queue
             */
            $queue = $message ? unserialize($message[1]) : null;
            if ($queue instanceof Queue) {
                try {
                    $queue->handle();
                } catch (\Exception $e) {
                    $queue->retry += 1;
                    if ($queue->retry < C('QUEUE.RETRY')) {
                        $this->push($message[0], $queue, $queue_name);
                    } else {
                        BaseModel::getInstance('queue_failed_jobs')->insert([
                            'queue_type' => $message[0],
                            'context' => serialize($queue),
                            'exception' => json_encode([
                                'class' => (new \ReflectionClass($e))->getName(),
                                'message' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]),
                            'create_at' => time(),
                        ]);
                    }
                }
            }

        }
    }

    public function push($key, Queue $value, $queue_name = '')
    {
        $queue_name = self::getQueueName($queue_name);
        $this->handler->lpush($queue_name, serialize($value));
    }

    public function pushBatch($key, $queues, $queue_name = '')
    {
        $queue_name = self::getQueueName($queue_name);

        $len = count($queues);
        $count = 0;
        $batch_size = 10; // 批次数量
        $temp = [];
        foreach ($queues as $queue) {
            $temp[] = serialize($queue);
            $count++;

            if (0 == ($count%$batch_size) || $len == $count) {
                call_user_func_array([$this->handler, 'lPush'], array_merge([$queue_name], $temp));
                $temp = [];
            }
        }
    }

    protected static function getQueueName($queue_name = '')
    {
        $queue_name = empty($queue_name)? C('QUEUE.REDIS')['DEFAULT_QUEUE']: $queue_name;
        return C('QUEUE.REDIS')['PREFIX'].$queue_name;
    }
}