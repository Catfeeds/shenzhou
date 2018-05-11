<?php
/**
 * File: WorkerReserveSendNotification.class.php
 * User: xieguoqiu
 * Date: 2016/12/27 17:17
 */

namespace Admin\Repositories\Listeners;

use Admin\Model\BaseModel;
use Admin\Repositories\Events\WorkerReserveEvent;
use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;
use Api\Logic\WeChatNewsEventLogic;

class WorkerReserveSendNotification implements ListenerInterface
{
    /**
     * @param WorkerReserveEvent $event
     */
    public function handle(EventAbstract $event)
    {
        $appoint_info = BaseModel::getInstance('worker_order_appoint')
            ->getOneOrFail($event->data['appoint_id'], 'worker_order_id,appoint_time');

        $order = BaseModel::getInstance('worker_order')->getOneOrFail($appoint_info['worker_order_id'], 'servicetype,add_member_id,worker_id');
        $open_id = BaseModel::getInstance('worker_order_user_info')
            ->getFieldVal(
                [
                    'where' => ['order_id' => $appoint_info['worker_order_id']],
                    'join' => 'LEFT JOIN wx_user ON wx_user.id=worker_order_user_info.wx_user_id',
                ],
                'openid'
            );
        if (!$open_id) {
            return ;
        }

        $info = BaseModel::getInstance('worker_order_detail')
            ->getOneOrFail(['worker_order_id' => $appoint_info['worker_order_id']], 'servicepro_desc,servicebrand_desc,model');
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

        $worker_info = BaseModel::getInstance('worker')
            ->getOneOrFail($order['worker_id'], 'nickname,worker_telephone');
        $appoint_time = date('m月d日H点i分', $appoint_info['appoint_time']);

        $notification = "尊敬的用户，您的{$info['servicebrand_desc']}{$info['model']}{$info['servicepro_desc']}的{$servicetype}工单，神州联保已安排{$worker_info['nickname']}师傅为您提供服务（联系电话{$worker_info['worker_telephone']}），预约上门时间为：{$appoint_time}，烦请到时安排时间接待，如需修改预约时间请及时联系维修师傅。";

        if ($event->data['is_first']) {
            $notification = '【已派单】' . $notification;
        } else {
            $notification = '【预约上门】' . $notification;
        }

        // D('WeChatNewsEvent', 'Logic')->wxSendNewsByOpenId($open_id, $notification, 'text');
        (new WeChatNewsEventLogic())->wxSendNewsByOpenId($open_id, $notification, 'text');
    }
}
