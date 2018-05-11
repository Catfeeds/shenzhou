<?php
/**
 * File: OrderCancelSendNotification.class.php
 * User: xieguoqiu
 * Date: 2016/12/28 11:06
 */

namespace Common\Common\Repositories\Listeners\OrderCancel;

use Admin\Model\BaseModel;

use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Events\OrderCancelEvent;
use Common\Common\Repositories\Listeners\ListenerInterface;
use Common\Common\Service\OrderService;

class OrderCancelSendNotification implements ListenerInterface
{
    /**
     * @param OrderCancelEvent $event
     */
    public function handle(EventAbstract $event)
    {
        $order = BaseModel::getInstance('worker_order')->getOneOrFail($event->data['order_id'], 'service_type,distributor_id,worker_id,create_time');
        $open_id = BaseModel::getInstance('worker_order_user_info')
            ->getFieldVal(
                [
                    'where' => ['worker_order_id' => $event->data['order_id']],
                    'join' => 'LEFT JOIN wx_user ON wx_user.id=worker_order_user_info.wx_user_id',
                ],
                'openid'
            );
        if (!$open_id) {
            return ;
        }

        $info = BaseModel::getInstance('worker_order_product')
            ->getOneOrFail(['worker_order_id' => $event->data['order_id']], 'cp_category_name,cp_product_brand_name,cp_product_mode');

        $admin_info = $order['distributor_id'] ? BaseModel::getInstance('admin')->getOne($order['distributor_id'], 'id,user_name,tell_out') : '';

        $service_type = OrderService::SERVICE_TYPE[$order['service_type']];

        if ($admin_info) {
            $notification = "尊敬的用户，您的{$info['cp_product_brand_name']}{$info['cp_product_mode']}{$info['cp_category_name']}的{$service_type}工单已成功取消，如果对我们的服务有任何意见和建议，请与客服联系:{$admin_info['user_name']}-{$admin_info['tell_out']}。神州联保祝您生活愉快！";
        } else {
            $notification = "尊敬的用户，您的{$info['cp_product_brand_name']}{$info['cp_product_mode']}{$info['cp_category_name']}的{$service_type}工单已成功取消。神州联保祝您生活愉快！";
        }

        sendWechatNotification($open_id, $notification, 'text');
    }

}
