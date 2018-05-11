<?php
/**
 * File: OrderSettlementSendNotification.class.php
 * User: xieguoqiu
 * Date: 2017/2/15 16:15
 */

namespace Common\Common\Repositories\Listeners;

use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;
use Library\Common\Util;
use Common\Common\Model\BaseModel;
use Common\Common\Repositories\Events\OrderSendNotificationEvent;
use Common\Common\Service\AppMessageService;
use Common\Common\Service\GroupService;
use Stoneworld\Wechat\Message;
use Stoneworld\Wechat\Messages\NewsItem;

class OrderSendNotification implements ListenerInterface
{
    /**
     * @param OrderSendNotificationEvent $event
     * 工单消息事件
     * data_id 对应的工单id
     * type    对应AppMessageService中 TYPE_*类型
     * num     明天需上门数量
     */
    public function handle(EventAbstract $event)
    {
        $order_info = BaseModel::getInstance('worker_order')->getOne($event->data['data_id']);
        $worker = BaseModel::getInstance('worker')->getOne($order_info['worker_id'], 'worker_telephone, jpush_alias');

        $remark = '未上传预约的工单将在3小时后回收,请尽快与用户联系预约';
        if ($order_info['service_type'] == 110) {
            $service_type = '预发件安装';
            $remark = '产品已从厂家发出，但尚未到达用户处。为提高服务时效，请您点击工单的“查看物流”查询物流进度，并提前与用户预约服务时间，在收到“用户已签收”的通知后，尽快为用户安装产品';
        } elseif ($order_info['service_type'] == 106) {
            $service_type = '上门安装';
        } elseif ($order_info['service_type'] == 107) {
            $service_type = '上门维修';
        } elseif ($order_info['service_type'] == 108) {
            $service_type = '上门维护';
        } else {
            $service_type = '用户送修';
        }

        $product_list = BaseModel::getInstance('worker_order_product')->getList([
            'where' => [
                'worker_order_id' => $event->data['data_id']
            ],
            'field' => 'cp_product_brand_name, cp_category_name'
        ]);
        $fault_name = '';
        foreach ($product_list as $v) {
            $fault_name .= $v['cp_product_brand_name'].$v['cp_category_name'].',';
        }
        $fault_name = substr($fault_name, 0, -1);

        $worker_order_user_info = BaseModel::getInstance('worker_order_user_info')->getOne([
            'where' => [
                'worker_order_id' => $event->data['data_id']
            ],
            'field' => '*'
        ]);
        $address = BaseModel::getInstance('area')->getFieldVal([
            'id' => $worker_order_user_info['area_id']
        ], 'name').$worker_order_user_info['address'];

        if ($event->data['type'] == AppMessageService::TYPE_NEW_WORKER_ORDER_MASSAGE) {
            $title = '您有一张新工单';
            $content = "工单号:{$order_info['orno']}({$service_type})<br>".
                       "服务产品:{$fault_name}<br>".
                       "服务地址:{$address}<br>".
                       "{$remark}";
        } elseif ($event->data['type'] == AppMessageService::TYPE_APPOINT_MASSAGE) {
//            $date = strtotime(date('Y-m-d', strtotime('+1 day')));
//            $appoint_model = BaseModel::getInstance('worker_order_appoint_record');
//            $appoints = $appoint_model->getList([
//                'where' => [
//                    'appoint_time' => ['between', [$date, $date+86400]],
//                ],
//                'field' => 'worker_order_id, create_time'
//            ]);
//            foreach ($appoints as $k => $v) {
//                $appoint_id = $appoint_model->getFieldVal([
//                    'create_time' => ['gt', $v['create_time']],
//                    'appoint_time' => ['gt', $date+86400]
//                ], 'id');
//                if (empty($appoint_id)) {
//                    $worker_order_ids[] = $v['worker_order_id'];
//                }
//            }
//            $worker_order_ids = !empty($worker_order_ids) ? implode(',', $worker_order_ids) : '0';
//            $num = BaseModel::getInstance('worker_order_appoint_record')->getNum([
//                'where' => [
//                    'appoint_time' => ['between', [$date, $date+86400]],
//                    'appoint_status' => ['in', '1,2,5'],
//                    'worker_order_id' => ['in', $worker_order_ids]
//                ],
//                'group' => 'worker_order_id'
//            ]);
            $title = '您明天有需要上门的工单';
            $content = "您有{$event->data['num']}张工单与用户预约了明天上门服务,请提前做好准备";
        } elseif ($event->data['type'] == AppMessageService::TYPE_SIGN_IN_REMIND) {
            $title = '安装预发件工单已签收提醒';
            $content = "用户已签收产品，请尽快联系用户进行安装<br>".
                       "服务产品:$fault_name<br>".
                       "服务地址:{$address}";
        } elseif ($event->data['type'] == AppMessageService::TYPE_VISIT_PASS_MASSAGE) {
            $title = '工单回访服务完成';
            $content = "工单号:{$order_info['orno']}({$service_type})<br>".
                "服务地址:{$address}<br>".
                "您服务的{$fault_name}已完成客户回访<br>";
        } elseif ($event->data['type'] == AppMessageService::TYPE_VISIT_NOT_PASS) {
            $title = '工单回访不通过';
            $content = "工单号:{$order_info['orno']}({$service_type})<br>".
                "服务地址:{$address}<br>".
                "您服务的{$fault_name}客户回访不通过,请再安排上门服务<br>";
        } elseif ($event->data['type'] == AppMessageService::TYPE_ORIGIN_ORDER_REWORK) {
            $title = '工单返修';
            $content = "工单号:{$order_info['orno']}({$service_type})<br>".
                "服务产品:$fault_name<br>".
                "服务地址:{$address}<br>".
                "用户发起返修，稍后会有客服与您联系再次上门<br>";
        } elseif ($event->data['type'] == AppMessageService::TYPE_NEW_REWORK_ORDER_MESSAGE) {
            $title = '您有一张返修单';
            $content = "工单号:{$order_info['orno']}({$service_type})<br>".
                "服务产品:$fault_name<br>".
                "服务地址:{$address}<br>".
                "用户发起返修，稍后会有客服与您联系再次上门<br>";
        } else {
            return false;
        }
        //消息记录
        $id = AppMessageService::create($order_info['worker_id'], $event->data['data_id'], $event->data['type'], $title, $content);
        if (!empty($id)) {
            //极光推送
            $jpush_content = str_replace('<br>', ' ', $content);
            workerNotificationJPush($worker['jpush_alias'], $event->data['type'], $id, $title, $jpush_content, $event->data['data_id']);
        }

        //企业号推送
        $news = new NewsItem();
        $news->title = $title;
        $news->description  = str_replace('<br>', "\n", $content);
        if ($event->data['type'] == AppMessageService::TYPE_APPOINT_MASSAGE) {
            $url_path = C('application_url.order_base_url') . '7?appoint_time='. (strtotime(date('Y-m-d', NOW_TIME)) + 86400);
        } else {
            $url_path = C('application_url.worker_order_base_url') . $event->data['data_id'];
        }
        $news->url = C('qiyewechat_host') . C('qy_base_path') . $url_path;
        $message = Message::make('news')->item($news);
        sendQyWechatNotification($worker['worker_telephone'], $message, C('SEND_NEWS_MESSAGE_APPLICATION_BY_MY_WORKER_ORDER'));

        //检查是否群内工单
        if (!empty($order_info['worker_group_id'])) {
            $owner_worker_id = GroupService::getOwnerId($order_info['worker_group_id']);
            if ($owner_worker_id != $order_info['worker_id']) {
                $owner_worker = BaseModel::getInstance('worker')->getOne($owner_worker_id, 'worker_id, worker_telephone, jpush_alias');
            }
        }
        if (!empty($owner_worker)) {
            //消息记录
            $id = AppMessageService::create($owner_worker['worker_id'], $event->data['data_id'], $event->data['type'], $title, $content);
            if (!empty($id)) {
                //极光推送
                $jpush_content = str_replace('<br>', ' ', $content);
                workerNotificationJPush($owner_worker['jpush_alias'], $event->data['type'], $id, $title, $jpush_content, $event->data['data_id']);
            }
            //企业号推送
            sendQyWechatNotification($owner_worker['worker_telephone'], $message, C('SEND_NEWS_MESSAGE_APPLICATION_BY_MY_WORKER_ORDER'));
        }
    }

}
