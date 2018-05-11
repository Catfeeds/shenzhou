<?php
/**
 * File: IncrementScanTimes.class.php
 * User: xieguoqiu
 * Date: 2016/12/14 19:54
 */

namespace Admin\Repositories\Listeners;

use Common\Common\Model\BaseModel;
use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;

class IncrementScanTimes implements ListenerInterface
{

    /**
     * @param \Api\Repositories\Events\ScanQrcodeEvent $event
     */
    public function handle(EventAbstract $event)
    {
        $suffix = substr($event->md5Code , 0, 1 );
        $table_name = 'factory_excel_datas_' . $suffix;
        BaseModel::getInstance($table_name)->setNumInc(['md5code' => $event->md5Code], 'saomiao');
    }
    
}
