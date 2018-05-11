<?php
/**
 * File: ExpressCompleteSendNotification.class.php
 * User: sakura
 * Date: 2017/11/10
 */

namespace Common\Common\Repositories\Listeners;

use Common\Common\Logic\ExpressTrackingLogic;
use Common\Common\Model\BaseModel;
use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Events\OrderSendNotificationEvent;
use Common\Common\Repositories\Listeners\ListenerInterface;
use Common\Common\Service\AppMessageService;
use Common\Common\Service\OrderService;
use Common\Common\Service\SystemMessageService;

class ExpressCompleteSendNotification implements ListenerInterface
{

    public function handle(EventAbstract $event)
    {
        try {
            $param = $event->getData();

            $type = $param['type'];
            $data_id = $param['data_id'];

            if (ExpressTrackingLogic::TYPE_ACCESSORY_SEND == $type) {
                //发件
                $accessory_id = $data_id;

                $model = BaseModel::getInstance('worker_order_apply_accessory');
                $accessory_info = $model->getOneOrFail($accessory_id);
                $worker_order_id = $accessory_info['worker_order_id'];

                $order_model = BaseModel::getInstance('worker_order');
                $order = $order_model->getOneOrFail($worker_order_id);
                $orno = $order['orno'];
                $distributor_id = $order['distributor_id'];

                $receiver_type = SystemMessageService::USER_TYPE_ADMIN;

                $sys_msg = "工单号{$orno}的配件，物流已签收";
                $sys_type = SystemMessageService::MSG_TYPE_ADMIN_ACCESSORY_WORKER_TAKE;

                SystemMessageService::create($receiver_type, $distributor_id, $sys_msg, $accessory_id, $sys_type);
            } elseif (ExpressTrackingLogic::TYPE_ACCESSORY_SEND_BACK == $type) {
                //返件

            } elseif (ExpressTrackingLogic::TYPE_ORDER_PRE_INSTALL_SEND == $type) {
                //工单预安装发件
                $worker_order_id = $data_id;
                $order_model = BaseModel::getInstance('worker_order');
                $order = $order_model->getOneOrFail($worker_order_id);
                $worker_id = $order['worker_id'];

                //检查是否有接收信息的技工,没有就不推送消息
                if ($worker_id > 0) {
                    $data = [
                        'data_id' => $worker_order_id,
                        'type'    => AppMessageService::TYPE_SIGN_IN_REMIND,
                    ];
                    event(new OrderSendNotificationEvent($data));
                }

            }
        } catch (\Exception $e) {

        }
    }

}