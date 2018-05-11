<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/12/1
 * Time: 14:35
 */

namespace Common\Common\Job;

use Library\Queue\Queue;
use Library\Common\Util;

class SendAppNotificationJob implements Queue
{
    protected $registration_id;
    protected $title;
    protected $content;
    protected $extras;
    protected $send_type;
    protected $is_radio;
    protected $data_id;
    protected $url;

    /**
     * SendAppNotificationJob constructor.
     * @param $registration_id
     * @param $title
     * @param $content
     * @param $extras
     * @param $send_type
     * @param $is_radio
     * @param $data_id
     */
    public function __construct($registration_id, $title, $content, $extras, $send_type=array('ios', 'android'), $is_radio='', $data_id)
    {
        $this->registration_id = $registration_id;
        $this->title           = $title;
        $this->content         = $content;
        $this->extras          = $extras;
        $this->send_type       = $send_type;
        $this->is_radio        = $is_radio;
        $this->data_id         = $data_id;
    }

    public function handle()
    {
        $app_key = C('jpush.app_key');
        $master_secret = C('jpush.master_secret');
        $jpush = new \JPush\Client($app_key, $master_secret);
        $params = [
            'extras'=>[
                'type'        => (string)$this->extras['type'],
                'id'          => $this->extras['id'],
                'title'       => $this->title,
                'content'     => $this->content,
                'create_time' => (string)NOW_TIME,
                'data_id'     => $this->data_id,
                'url'         => C('qiyewechat_host') . C('qy_base_path') . '/app/manual-content/'.$this->extras['type'].'/'.$this->extras['id']
            ]
        ];
        $push = $jpush->push()
            ->setPlatform($this->send_type);
        if ($this->registration_id) {
            $push->addRegistrationId($this->registration_id);
        }
        if ($this->is_radio) {
            $push->setAudience('all');
        }
        $push->setNotificationAlert($params['extras']['title'])
            ->iosNotification($params['extras']['content'], array(
                'title'  => $params['extras']['title'],
                'extras' => $params
            ))
            ->androidNotification($params['extras']['content'], array(
                'title'  => $params['extras']['title'],
                'extras' => $params
            ))
            ->options(array(
                'apns_production' => true,
            ))
            ->send();
    }

}