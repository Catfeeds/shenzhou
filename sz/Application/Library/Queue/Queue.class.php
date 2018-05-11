<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/21
 * Time: 11:33
 */

namespace Library\Queue;

interface Queue
{
    public function handle();
}