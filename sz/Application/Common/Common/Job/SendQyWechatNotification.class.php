<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/12/2
 * Time: 09:44
 */

namespace Common\Common\Job;

use Library\Queue\Queue;
use Qiye\Logic\QiYeWechatLogic;
use Stoneworld\Wechat\Message;

class SendQyWechatNotification implements Queue
{
    protected $user;
    protected $message;
    protected $application_id;

    /**
     * SendQyWechatTextNotification constructor.
     * @param $user
     * @param $message
     * @param $application_id
     */
    public function __construct($user, $message, $application_id)
    {
        $this->user = $user;
        $this->message = $message;
        $this->application_id = $application_id;
    }


    public function handle()
    {
        if ($this->message instanceof \Stoneworld\Wechat\Messages\BaseMessage) {
            (new QiYeWechatLogic())->sendNews2User($this->user, $this->message, $this->application_id);
        } else {
            (new QiYeWechatLogic())->sendText2User($this->user, $this->message, $this->application_id);
        }
    }
}