<?php
/**
 * File: SystemReceiveOrderListener.class.php
 * Function:
 * User: sakura
 * Date: 2018/3/29
 */

namespace Admin\Repositories\Listeners;


use Admin\Logic\SystemReceiveOrderCacheLogic;
use Carbon\Carbon;
use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;

class SystemReceiveOrderListener implements ListenerInterface
{

    public function handle(EventAbstract $event)
    {
        $weekday = Carbon::now()->dayOfWeek;

        //时间长度为1天+30分钟,保证队列在 边界时间点(23:59:59) 消费的时候工单 能获取缓存信息
        $expire = strtotime(date('Ymd')) + 86400 + 1800;
        (new SystemReceiveOrderCacheLogic())->init($weekday, $expire);
    }

}