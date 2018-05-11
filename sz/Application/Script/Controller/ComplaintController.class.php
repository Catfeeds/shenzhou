<?php
/**
 * File: ComplaintController.class.php
 * Function:
 * User: sakura
 * Date: 2018/1/21
 */

namespace Script\Controller;


use Common\Common\Model\BaseModel;
use Common\Common\Service\ComplaintService;
use Common\Common\Service\OrderService;

class ComplaintController extends BaseController
{

    public function setAdmin()
    {
        try {

            $begin = time();

            $complaint_model = BaseModel::getInstance('worker_order_complaint');
            $opts = [
                'field' => 'id,worker_order_id',
                'where' => [
                    'replier_id' => 0,
                ],
            ];
            $complaints = $complaint_model->getList($opts);

            $worker_order_ids = [];

            foreach ($complaints as $complaint) {
                $worker_order_id = $complaint['worker_order_id'];

                $worker_order_ids[] = $worker_order_id;
            }

            $field = 'id,auditor_id,distributor_id,returnee_id,checker_id,worker_order_status';
            $orders = $this->getWorkerOrders($worker_order_ids, $field);

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

            //如果在工单状态为：待派单客服接单之前，则为：核实客服
            //如果在工单状态为：待神州财务接单之前，则为：派单客服
            //如果在工单状态为：待神州财务接单及之后，则为：回访客服
            M()->startTrans();

            foreach ($complaints as $complaint) {
                $worker_order_id = $complaint['worker_order_id'];
                $complaint_id = $complaint['id'];

                $order = $orders[$worker_order_id]?? null;

                if (empty($order)) {
                    continue;
                }

                $worker_order_status = $order['worker_order_status'];
                $distributor_id = $order['distributor_id'];
                $returnee_id = $order['returnee_id'];
                $checker_id = $order['checker_id'];

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

                $complaint_model->update($complaint_id, [
                    'replier_id' => $admin_id,
                ]);

            }

            M()->commit();

            $end = time();

            date_default_timezone_set('GMT');
            echo date('i:s', $end - $begin);


        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    protected function getWorkerOrders($worker_order_ids, $field = '')
    {
        if (empty($worker_order_ids)) {
            return [];
        }

        $data = [];

        $model = BaseModel::getInstance('worker_order');

        $opts = [
            'where' => [
                'id' => ['in', $worker_order_ids]
            ],
        ];

        if (strlen($field) > 0) {
            $opts['field'] = $field;
        }

        $list = $model->getList($opts);

        foreach ($list as $val) {
            $worker_order_id = $val['id'];

            $data[$worker_order_id] = $val;
        }

        return $data;
    }

    public function transferResponseType()
    {
        try {
            $begin = time();


            $old_model = $this->setOrderAtModel('worker_order_complaint');

            $new_model = BaseModel::getInstance('worker_order_complaint');

            $opts = [
                'field' => 'id,response_type,worker_order_id',
            ];
            $complaints = $old_model->getList($opts);

            $worker_order_ids = [];
            foreach ($complaints as $complaint) {
                $worker_order_id = $complaint['worker_order_id'];

                $worker_order_ids[] = $worker_order_id;
            }

            $field = 'id,origin_type';
            $orders = $this->getWorkerOrders($worker_order_ids, $field);

            M()->startTrans();

            //旧库 责任方：1维修商，2客服，3下单人，4用户
            //新库 1 客服；2 厂家；3 厂家子账号；4 技工；5用户
            foreach ($complaints as $complaint) {
                $complaint_id = $complaint['id'];
                $response_type = $complaint['response_type'];
                $worker_order_id = $complaint['worker_order_id'];

                $order = $orders[$worker_order_id]?? null;

                if (empty($order)) {
                    continue;
                }

                $origin_type = $order['origin_type'];

                $transfer_response_type = 0;
                if (1 == $response_type) {
                    //技工
                    $transfer_response_type = ComplaintService::RESPONSE_TYPE_WORKER;
                } elseif (2 == $response_type) {
                    //客服
                    $transfer_response_type = ComplaintService::RESPONSE_TYPE_CS;
                } elseif (3 == $response_type) {
                    //下单人 厂家 or 子账号
                    if (OrderService::ORIGIN_TYPE_FACTORY_ADMIN == $origin_type) {
                        $transfer_response_type = ComplaintService::RESPONSE_TYPE_FACTORY_ADMIN;
                    } else {
                        $transfer_response_type = ComplaintService::RESPONSE_TYPE_FACTORY;
                    }
                } elseif (4 == $response_type) {
                    //用户
                    $transfer_response_type = ComplaintService::RESPONSE_TYPE_WX_USER;
                }

                $new_model->update($complaint_id, [
                    'response_type' => $transfer_response_type,
                ]);

            }

            M()->commit();

            $end = time();

            date_default_timezone_set('GMT');
            echo date('i:s', $end - $begin);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    protected function setOrderAtModel($table = '', $is_old = true)
    {
        $conf = $is_old ? C('DB_CONFIG_OLD_V3') : '';

        return new BaseModel($table, '', $conf);
    }

}