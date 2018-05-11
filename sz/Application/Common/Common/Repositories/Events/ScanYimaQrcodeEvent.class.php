<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/12/5
 * Time: 11:30
 */

namespace Common\Common\Repositories\Events;

use Common\Common\Repositories\Listeners\ScanYimaQrcode\IncrementScanTimes;

class ScanYimaQrcodeEvent extends EventAbstract
{

    public $code;

    protected $listeners = [
        IncrementScanTimes::class,
    ];

    public function __construct($code)
    {
        $this->code = $code;
    }

}