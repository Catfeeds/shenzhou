<?php
/**
 * File: EventAbstract.class.php
 * User: xieguoqiu
 * Date: 2016/7/22 16:04
 */

namespace Common\Common\Repositories\Events;

abstract class EventAbstract
{
    
    protected $listeners = [];
    
    public function addListener($listener)
    {
        $this->listeners[] = $listener;
    }

    public function removeListener($listener)
    {
        if (($key = array_search($listener, $this->listeners)) !== false) {
            unset($this->listeners[$key]);
        }
    }

    public function getListeners()
    {
        return $this->listeners;
    }
}
 