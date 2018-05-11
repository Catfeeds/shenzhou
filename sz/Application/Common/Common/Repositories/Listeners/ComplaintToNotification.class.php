<?php
/**
 * File: ComplaintToNotification.class.php
 * Function:
 * User: sakura
 * Date: 2018/4/9
 */

namespace Common\Common\Repositories\Listeners;


use Common\Common\Model\BaseModel;
use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Service\AppMessageService;
use Common\Common\Service\ComplaintService;

class ComplaintToNotification implements ListenerInterface
{

    public function handle(EventAbstract $event)
    {

        try {

            $complaint_id = $event->data['complaint_id'];

            //获取投诉单
            $field = 'worker_order_id,complaint_to_type,content';
            $complaint_info = BaseModel::getInstance('worker_order_complaint')->getOneOrFail($complaint_id, $field);
            $worker_order_id = $complaint_info['worker_order_id'];
            $complaint_to_type = $complaint_info['complaint_to_type'];
            $content = $complaint_info['content'];

            if (ComplaintService::TO_TYPE_WORKER == $complaint_to_type) {
                //获取工单
                $field = 'children_worker_id,worker_id,orno';
                $order = BaseModel::getInstance('worker_order')->getOneOrFail($worker_order_id, $field);

                //判断是否子账号接单
                $worker_id = $order['worker_id'];
                $children_worker_id = $order['children_worker_id'];
                $orno = $order['orno'];

                $msg_type = AppMessageService::TYPE_COMPLAINT_CREATE_MESSAGE;

                $worker_model = BaseModel::getInstance('worker');
                $title = "工单{$orno}有投诉";
                $msg_id = AppMessageService::create($worker_id, $complaint_id, $msg_type, $title, $content);
                $main_worker = $worker_model->getOneOrFail($worker_id, 'jpush_alias');
                $jpush_alias = $main_worker['jpush_alias'];
                workerNotificationJPush($jpush_alias, $msg_type, $msg_id, $title, $content, $complaint_id);

                //子账号接单 要发两次
                if ($children_worker_id > 0) {
                    $msg_id = AppMessageService::create($children_worker_id, $complaint_id, $msg_type, $title, $content);
                    $children_worker = $worker_model->getOneOrFail($children_worker_id, 'jpush_alias');
                    $jpush_alias = $children_worker['jpush_alias'];
                    workerNotificationJPush($jpush_alias, $msg_type, $msg_id, $title, $content, $complaint_id);
                }
            }


        } catch (\Exception $e) {

        }


    }

}