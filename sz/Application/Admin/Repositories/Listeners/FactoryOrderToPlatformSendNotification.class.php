<?php
/**
 * File: FactoryOrderToPlatformSendNotification.class.php
 * User: xieguoqiu
 * Date: 2016/12/27 16:24
 */

namespace Admin\Repositories\Listeners;

use Admin\Model\BaseModel;
use Admin\Repositories\Events\FactoryOrderToPlatformEvent;
use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;

class FactoryOrderToPlatformSendNotification implements ListenerInterface
{

    /**
     * @param FactoryOrderToPlatformEvent $event
     */
    public function handle(EventAbstract $event)
    {
        $order = BaseModel::getInstance('worker_order')->getOneOrFail($event->data['order_id'], 'servicetype,add_member_id');
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

        $notification = "【工单处理中】尊敬的用户，您的{$info['servicebrand_desc']}{$info['model']}{$info['servicepro_desc']}的{$servicetype}工单正在处理中，请保持电话畅通，神州联保客服稍后将会与您联系！";

        D('WeChatNewsEvent', 'Logic')->wxSendNewsByOpenId($open_id, $notification, 'text');
    }

}
