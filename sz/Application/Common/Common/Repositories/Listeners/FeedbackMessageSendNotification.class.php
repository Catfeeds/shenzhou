<?php
/**
 * File: WorkerExtractedSendNotification.class.php
 * User: xieguoqiu
 * Date: 2017/3/23 16:35
 */

namespace Common\Common\Repositories\Listeners;

use Common\Common\Model\BaseModel;
use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;
use Common\Common\Repositories\Events\FeedbackMessageEvent;
use Common\Common\Service\AppMessageService;
use Library\Common\Util;
use Stoneworld\Wechat\Message;
use Stoneworld\Wechat\Messages\NewsItem;


class FeedbackMessageSendNotification implements ListenerInterface
{
    /**
     * @param FeedbackMessageEvent $event
     * 反馈回复消息事件
     * $event->data['data_id'] 对应worker_feedback表id
     */
    public function handle(EventAbstract $event)
    {
        try {
            $feedback = BaseModel::getInstance('worker_feedback')->getOne([
                'where' => [
                    'id' => $event->data['data_id'],
                    'status' => 1
                ]
            ]);
            $worker = BaseModel::getInstance('worker')->getOne($feedback['worker_id'], 'worker_telephone, jpush_alias');
            if (!empty($feedback)) {
                $date = date('m月d日', $feedback['addtime']);
                $title = '反馈已回复';
                $content = "您在{$date}提交的反馈，客服已回复。<br>".
                           "反馈内容：{$feedback['content']}<br>".
                           "客服回复：{$feedback['reply']}";
            } else {
                return false;
            }
            //极光推送
            $jpush_content = str_replace('<br>', ' ', $content);
            workerNotificationJPush($worker['jpush_alias'], AppMessageService::TYPE_FEEDBACK_MASSAGE, $event->data['data_id'], $title, $jpush_content, $event->data['data_id']);

            //企业号推送
            $news = new NewsItem();
            $news->title = $title;
            $news->description  = str_replace('<br>', "\n", $content);
            $news->url = C('qiyewechat_host'). C('qy_base_path'). C('application_url.feedback_base_url') . $event->data['data_id'];
            $message = Message::make('news')->item($news);
            sendQyWechatNotification($worker['worker_telephone'], $message, C('SEND_NEWS_MESSAGE_APPLICATION_BY_ABOUT_SHENZHOU'));
        } catch (\Exception $e) {

        }

    }

}
