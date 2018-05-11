<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/11/21
 * Time: 17:41
 */

namespace Common\Common\Job;

use Common\Common\Logic\Sms\SmsServerLogic;
use Common\Common\Service\SMSService;
use Library\Queue\Queue;

class SendSmsJob implements Queue
{

    protected $phone;
    protected $template_sn;
    protected $template_params;
    protected $client_ip;

    /**
     * SendSmsJob constructor.
     */
    public function __construct($phone, $template_sn, $template_params, $client_ip)
    {
        $this->phone = $phone;
        $this->template_sn = $template_sn;
        $this->template_params = $template_params;
        $this->client_ip = $client_ip;
    }

    public function handle()
    {
        SMSService::sendSms($this->phone, $this->template_sn, $this->template_params, $this->client_ip);
    }
}