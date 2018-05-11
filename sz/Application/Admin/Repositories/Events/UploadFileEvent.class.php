<?php
/**
 * File: UploadFileEvent.class.php
 * User: xieguoqiu
 * Date: 2016/12/23 10:15
 */

namespace Admin\Repositories\Events;

use Admin\Repositories\Listeners\SynchronizeUploadFiles;
use Common\Common\Repositories\Events\EventAbstract;

class UploadFileEvent extends EventAbstract
{

    protected $listeners = [
        SynchronizeUploadFiles::class,
    ];

    public $files;

    /**
     * UploadFileEvent constructor.
     * @param $files
     */
    public function __construct($files)
    {
        $this->files = $files;
    }

}
