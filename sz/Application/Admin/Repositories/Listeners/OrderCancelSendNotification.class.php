<?php
/**
 * File: OrderCancelSendNotification.class.php
 * User: xieguoqiu
 * Date: 2016/12/28 11:06
 */

namespace Admin\Repositories\Listeners;

use Admin\Model\BaseModel;
use Admin\Repositories\Events\OrderCancelEvent;
use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;

class OrderCancelSendNotification implements ListenerInterface
{
    /**
     * @param OrderCancelEvent $event
     */
    public function handle(EventAbstract $event)
    {
        $order = BaseModel::getInstance('worker_order')->getOneOrFail($event->data['order_id'], 'servicetype,worker_id,datetime');
        $open_id = BaseModel::getInstance('worker_order_user_info')
            ->getFieldVal(
                [
                    'where' => ['order_id' => $event->data['order_id']],
                    'join' => 'LEFT JOIN wx_user ON wx_user.id=worker_order_user_info.wx_user_id',
                ],
                'openid'
            );
        if (!$open_id) {
            return ;
        }

        $info = BaseModel::getInstance('worker_order_detail')
            ->getOneOrFail(['worker_order_id' => $event->data['order_id']], 'servicepro_desc,servicebrand_desc,model');

        $admin_info = BaseModel::getInstance('worker_order_access')
            ->getOne([
                'field' => 'user_name,tell_out',
                'where' => ['link_order_id' => $event->data['order_id'], 'worker_order_access.role_id' => 5],   // 5为工单客服
                'join' => [
                    'LEFT JOIN admin ON admin.id=worker_order_access.admin_id'
                ],
                'order' => 'worker_order_access.id DESC',
                'limit' => 1
            ]);

        $servicetype = '';
        if ($order['servicetype'] == 106) {
            $servicetype = '上门安装';
        } elseif ($order['servicetype'] == 107) {
            $servicetype = '上门维修';
        } elseif ($order['servicetype'] == 108) {
            $servicetype = '上门维护';
        } elseif ($order['servicetype'] == 109) {
            $servicetype = '用户送修';
        } elseif ($order['servicetype'] == 110) {
            $servicetype = '预发件安装';
        }


        if ($admin_info) {
            $notification = "尊敬的用户，您的{$info['servicebrand_desc']}{$info['model']}{$info['servicepro_desc']}的{$servicetype}工单已成功取消，如果对我们的服务有任何意见和建议，请与客服联系:{$admin_info['user_name']}-{$admin_info['tell_out']}。神州联保祝您生活愉快！";
        } else {
            $notification = "尊敬的用户，您的{$info['servicebrand_desc']}{$info['model']}{$info['servicepro_desc']}的{$servicetype}工单已成功取消。神州联保祝您生活愉快！";
        }


        D('WeChatNewsEvent', 'Logic')->wxSendNewsByOpenId($open_id, $notification, 'text');
    }

}
