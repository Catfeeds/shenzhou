<?php
/**
 * File: ComplaintLogic.class.php
 * Function:
 * User: sakura
 * Date: 2018/4/9
 */

namespace Admin\Logic;


use Admin\Model\BaseModel;
use Common\Common\Repositories\Events\PromptComplaintToEvent;
use Common\Common\Service\ComplaintService;
use Common\Common\Service\OrderService;

class ComplaintLogic extends BaseLogic
{

    public function promptComplaintTo($complaint_id)
    {
        $field = 'is_prompt_complaint_to';
        $model = BaseModel::getInstance('worker_order_complaint');
        $complaint_info = $model->getOneOrFail($complaint_id, $field);
        $is_prompt_complaint_to = $complaint_info['is_prompt_complaint_to'];

        if (ComplaintService::IS_PROMPT_COMPLAINT_TO_NO == $is_prompt_complaint_to) {
            $model->update($complaint_id, [
                'is_prompt_complaint_to' => ComplaintService::IS_PROMPT_COMPLAINT_TO_YES,
            ]);

            event(new PromptComplaintToEvent(['complaint_id' => $complaint_id]));
        }

    }

    public function getMatchAdmin($worker_order_id)
    {
        $field = 'distributor_id,checker_id,returnee_id,worker_order_status';
        $order = BaseModel::getInstance('worker_order')
            ->getOneOrFail($worker_order_id, $field);

        $worker_order_status = $order['worker_order_status'];
        $distributor_id = $order['distributor_id'];
        $returnee_id = $order['returnee_id'];
        $checker_id = $order['checker_id'];

        //核实
        $checker_status = [
            OrderService::STATUS_CHECKER_RECEIVED_AND_NEED_CHECKER_CHECK,
            OrderService::STATUS_CHECKER_CHECKED_AND_NEED_DISTRIBUTOR_RECEIVE,
        ];
        //派单
        $distributor_status = [
            OrderService::STATUS_DISTRIBUTOR_RECEIVED_AND_NEED_DISTRIBUTOR_DISTRIBUTE,
            OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_INTO_ORDER_POLL,
            OrderService::STATUS_DISTRIBUTOR_DISTRIBUTED_AND_WORKER_RECEIVE_AND_NEED_WORKER_APPOINT,
            OrderService::STATUS_WORKER_APPOINTED_AND_NEED_WORKER_SERVICE,
            OrderService::STATUS_WORKER_SERVICED_AND_NEED_WORKER_FINISH_SERVICE,
            OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE,
            OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE,
        ];
        //回访
        $returnee_status = [
            OrderService::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT,
            OrderService::STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE,
            OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT,
        ];
        //财务
        $auditor_status = [
            OrderService::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT,
            OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT,
            OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT,
            OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED,
        ];

        $admin_id = 0;

        if (in_array($worker_order_status, $checker_status)) {
            $admin_id = $checker_id;
        } elseif (in_array($worker_order_status, $distributor_status)) {
            $admin_id = $distributor_id;
        } elseif (in_array($worker_order_status, $returnee_status)) {
            $admin_id = $distributor_id;
        } elseif (in_array($worker_order_status, $auditor_status)) {
            $admin_id = $returnee_id;
        }

        return $admin_id;
    }

}