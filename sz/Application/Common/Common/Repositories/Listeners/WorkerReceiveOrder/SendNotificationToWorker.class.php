<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/12/2
 * Time: 17:16
 */

namespace Common\Common\Repositories\Listeners\WorkerReceiveOrder;

use Common\Common\Model\BaseModel;
use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;
use Common\Common\Service\AppMessageService;
use Common\Common\Service\OrderService;

class SendNotificationToWorker implements ListenerInterface
{
    /**
     * @param EventAbstract $event
     */
    public function handle(EventAbstract $event)
    {
        $order = BaseModel::getInstance('worker_order')->getOneOrFail([
            'where' => ['id' => $event->worker_order_id],
            'field' => 'id,orno,worker_id,service_type,address',
            'join' => [
                'INNER JOIN worker_order_user_info ON worker_order_user_info.worker_order_id=worker_order.id'
            ]
        ]);
        $first_order_product = BaseModel::getInstance('worker_order_product')->getOne([
            'where' => ['worker_order_id' => $event->worker_order_id],
            'field' => 'cp_product_brand_name,cp_category_name',
            'order' => 'id ASC',
            'limit' => 1,
        ]);
        $service_type_name = OrderService::SERVICE_TYPE[$order['service_type']];
        $content = "工单号：{$order['orno']}($service_type_name)\r\n"
            . "服务产品：{$first_order_product['cp_product_brand_name']}{$first_order_product['cp_category_name']}\r\n"
            . "服务地址：{$order['address']}\r\n";
        if ($order['service_type'] == OrderService::TYPE_PRE_RELEASE_INSTALLATION) {
            $content .= '产品已从厂家发出，但尚未到达用户处。为提高服务时效，请您点击工单的“查看物流”查询物流进度，并提前与用户预约服务时间，在收到“用户已签收”的通知后，尽快为用户安装产品。';
        } else {
            $content .= '未上传预约的工单将在180后回收，请尽快与用户联系预约';
        }
        $title = '您有一张新工单';
        createSystemMessageAndNotification($order['worker_id'], $order['id'], AppMessageService::TYPE_NEW_WORKER_ORDER_MASSAGE, $title, $content, C('SEND_NEWS_MESSAGE_APPLICATION_BY_MY_WORKER_ORDER'));
    }

}