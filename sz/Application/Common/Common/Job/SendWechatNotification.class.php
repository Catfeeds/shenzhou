<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/12/8
 * Time: 14:39
 */

namespace Common\Common\Job;

use Api\Logic\WeChatNewsEventLogic;
use Library\Queue\Queue;

class SendWechatNotification implements Queue
{
    protected $open_id;
    protected $message;
    protected $type;

    /**
     * SendWechatNotification constructor.
     * @param $open_id
     * @param $message
     * @param $type
     */
    public function __construct($open_id, $message, $type)
    {
        $this->open_id = $open_id;
        $this->message = $message;
        $this->type = $type;
    }

    public function handle()
    {
        (new WeChatNewsEventLogic())->wxSendNewsByOpenId($this->open_id, $this->message, $this->type);
    }

}