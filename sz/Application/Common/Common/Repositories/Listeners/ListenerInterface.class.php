<?php
/**
 * File: ListenerInterface.class.php
 * User: xieguoqiu
 * Date: 2016/7/25 10:20
 */

namespace Common\Common\Repositories\Listeners;

use Common\Common\Repositories\Events\EventAbstract;

Interface ListenerInterface
{

    public function handle(EventAbstract $event);

}
 