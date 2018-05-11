<?php
/**
 * File: Events.class.php
 * User: xieguoqiu
 * Date: 2016/7/25 9:55
 */

namespace Common\Common\Repositories\Events;

class Event
{

    public function fire(EventAbstract $event)
    {
        $listeners = $event->getListeners();

        foreach ($listeners as $listener) {
            (new $listener())->handle($event);
        }
    }

}
 