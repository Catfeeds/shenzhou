<?php
/**
 * File: ScanQrcodeEvent.class.php
 * User: xieguoqiu
 * Date: 2016/12/14 19:50
 */

namespace Admin\Repositories\Events;

use Admin\Repositories\Listeners\IncrementScanTimes;
use Common\Common\Repositories\Events\EventAbstract;

class ScanQrcodeEvent extends EventAbstract
{

    public $md5Code;
    
    protected $listeners = [
        IncrementScanTimes::class,
    ];
    
    public function __construct($md5Code)
    {
        $this->md5Code = $md5Code;
    }
    
}
