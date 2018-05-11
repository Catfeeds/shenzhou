<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/27
 * Time: 17:41
 */

namespace Library\Queue;

class Worker
{
    public function work($queue_name)
    {
        try {

            $class_name = getQueueDriver(C('QUEUE.DRIVER'));
            /**
             * @var Driver $class
             */
            $class = new $class_name;
            $class->work($queue_name);
        } catch (\Exception $e) {

        }
    }
}