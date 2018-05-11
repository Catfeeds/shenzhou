<?php
/**
 * File: AccessoryCheckSendNotification.class.php
 * User: xieguoqiu
 * Date: 2017/2/15 10:24
 */

namespace Common\Common\Repositories\Listeners;

use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;
use \Common\Common\Model\BaseModel;
use Common\Common\Service\GroupService;

class UpdateOrderNumberNotification implements ListenerInterface
{
    /*
     * 自动更新群关联的工单数量
     * worker_order_id               工单id
     * operation_type                操作记录类型
     * original_worker_id            原负责的技工id(无变化不需要传该值)
     * original_children_worker_id   原负责的技工子账号id(无变化不需要传该值)
     * original_worker_order_status  原工单状态(无变化不需要传该值)
     * original_group_id             原工单负责的群(派单需要传该值)
     */
    public function handle(EventAbstract $event)
    {
        try {
            GroupService::autoUpdateOrderNumber($event->data['worker_order_id'], $event->data['operation_type'], $event->data['original_worker_id'], $event->data['original_children_worker_id'], $event->data['original_worker_order_status'], $event->data['original_group_id']);
        } catch (\Exception $e) {

        }
    }

}
