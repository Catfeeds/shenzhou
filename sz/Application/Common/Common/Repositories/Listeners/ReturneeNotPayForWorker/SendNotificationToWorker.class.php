<?php
/**
 * Created by PhpStorm.
 * User: iqiuqiu
 * Date: 2017/12/11
 * Time: 12:08
 */

namespace Common\Common\Repositories\Listeners\ReturneeNotPayForWorker;

use Common\Common\Model\BaseModel;
use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Events\ReturneeNotPayForWorkerEvent;
use Common\Common\Repositories\Listeners\ListenerInterface;
use Common\Common\Service\AppMessageService;
use Common\Common\Service\OrderService;

class SendNotificationToWorker implements ListenerInterface
{
    /**
     * @param ReturneeNotPayForWorkerEvent $event
     */
    public function handle(EventAbstract $event)
    {
        $order = BaseModel::getInstance('worker_order')->getOneOrFail($event->worker_order_id, 'id,orno,service_type,worker_id');
        $order_user_info = BaseModel::getInstance('worker_order_user_info')->getOne($event->worker_order_id, 'address');
        $order_product = BaseModel::getInstance('worker_order_product')->getOne([
            'where' => [
                'worker_order_id' => $order['id'],
            ],
            'field' => 'cp_product_brand_name,cp_category_name',
            'order' => 'id ASC',
            'limit' => 1,
        ]);

        $service_type = OrderService::SERVICE_TYPE[$order['service_type']];
        $title = '工单回访没通过';
        $content = "工单号：{$order['orno']}（{$service_type}}）\r\n"
			. "服务地址：{$order_user_info['address']}
			您服务的{$order_product['cp_product_brand_name']}-{$order_product['cp_category_name']}工单，客户回访不通过，请再安排上门服务";
        createSystemMessageAndNotification($order['worker_id'], $order['id'], AppMessageService::TYPE_VISIT_PASS_MASSAGE, $title, $content, C('SEND_NEWS_MESSAGE_APPLICATION_BY_MY_WORKER_ORDER'));
    }

}