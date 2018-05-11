<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/21
 * Time: 12:02
 */

namespace Library\Queue;

interface Driver
{

    public function work($queue_name);

    public function push($key, Queue $value, $queue_name = '');

    public function pushBatch($key, $queues, $queue_name = '');
}