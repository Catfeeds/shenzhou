<?php
/**
 * File: OrderFatalErrorLog.class.php
 * Function:
 * User: sakura
 * Date: 2017/12/15
 */

namespace Admin\Repositories\Listeners;

use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;

class OrderFatalErrorLog implements ListenerInterface
{
    public function handle(EventAbstract $event)
    {
        $worker_order_id = $event->data['worker_order_id'];
        $fatal_error_msg = $event->data['fatal_error_msg'];

        $key = 'settle_fatal_error';
        $fatal = F($key);

        if (array_key_exists($worker_order_id, $fatal)) {
            if (!in_array($fatal_error_msg, $fatal[$worker_order_id])) {
                $fatal[$worker_order_id][] = $fatal_error_msg;
            }
        } else {
            $fatal[$worker_order_id][] = $fatal_error_msg;
        }

        F($key, $fatal);
    }

}