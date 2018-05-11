<?php
/**
 * File: WorkbenchController.class.php
 * Function:
 * User: sakura
 * Date: 2018/4/10
 */

namespace Script\Controller;


use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\OrderService;
use Script\Model\BaseModel;

class WorkbenchController extends BaseController
{

    public function returnTime()
    {

        $last_id = 0;

        $where = [
            'worker_order_status' => OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE,
            'cancel_status'       => ['in', [OrderService::CANCEL_TYPE_NULL, OrderService::CANCEL_TYPE_CS_STOP]],
            'id'                  => ['gt', &$last_id],
        ];

        $limit = 2000;
        $opts = [
            'field' => 'id',
            'where' => $where,
            'order' => 'id',
            'limit' => $limit,
        ];

        while (true) {
            $order = BaseModel::getInstance('worker_order')->getList($opts);

            if (empty($order)) {
                break;
            }

            $order_ids = array_column($order, 'id');

            $logs = $this->getLogs($order_ids);

            $arr = [];

            foreach ($order_ids as $order_id) {
                $log_time = empty($logs[$order_id]) ? 0 : $logs[$order_id]['create_time'];

                $arr[] = '('.implode(',', [$order_id,$log_time]).')';

            }

            $value_str = implode(',', $arr);

            $sql = "insert into worker_order(id,return_time) values %s on DUPLICATE KEY UPDATE return_time=values(return_time)";

            $sql = sprintf($sql, $value_str);


            M()->startTrans();

            M()->execute($sql);

            M()->commit();

            $last_id = end($order_ids);

        }

    }

    protected function getLogs($order_ids)
    {
        if (empty($order_ids)) {
            return [];
        }

        $opts = [
            'field' => 'worker_order_id, max(create_time) as create_time',
            'where' => [
                'worker_order_id' => ['in', $order_ids],
                'operation_type'  => OrderOperationRecordService::CS_NOT_SETTLEMENT_FOR_WORKER_AND_REST_SIGNED,
            ],
            'group' => 'worker_order_id',
            'index' => 'worker_order_id',
        ];
        $logs = BaseModel::getInstance('worker_order_operation_record')
            ->getList($opts);

        return $logs;
    }

}