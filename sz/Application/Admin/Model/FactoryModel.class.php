<?php
/**
* 
*/
namespace Admin\Model;

use Admin\Model\BaseModel;
use Common\Common\Service\OrderService;

class FactoryModel extends BaseModel
{
    const ORDER_TABLE_NAME = 'worker_order';
	const FACTORY_FROZEN_TABLE_NAME = 'factory_money_frozen';
	const FORACTORY_MONEY_RECORD_TABLE_NAME = 'factory_money_change_record';

	public function factoryYimaApplyCategory($factory_id = 0, $field = '*')
	{
		$model = BaseModel::getInstance('factory_product_qrcode');

		$list = $model->getList([
				'alias' => 'FPQ',
				'join'  => 'LEFT JOIN factory_product FP ON FPQ.product_id = FP.product_id',
				'field' => $field,
				'order' => 'FPQ.datetime DESC',
				'limit' => getPage(),
				'where' => [
					'FPQ.factory_id' => $factory_id,
					'_string' => 'FPQ.product_id IS NOT NULL',
				],
				'group' => 'cate_id',
			]);

		return $list;
	}

	public function getWorkerOrderFeeTotalForFactory($fid)
    {
    	$status_2 = OrderService::STATUS_ADDED_TO_PLATFORM_AND_NEED_CHECKER_RECEIVE;
        $status_16 = OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT;
        $status_17 = OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT;
    	$status_18 = OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED;
        
        $ing_where = '('.$status_2.'<=a.worker_order_status&&a.worker_order_status<'.$status_16.')||a.worker_order_status='.$status_17;
        $wait_where = 'a.worker_order_status='.$status_16;

        $field_arr = [
            'group_concat(IF('.$ing_where.',id,null)) as ing_ids', //  对厂家来说是进行中的工单ids 
            'IFNULL(SUM(IF('.$ing_where.',1,0)),0) as ing_nums',  	       //  对厂家来说是进行中的工单数量
            'group_concat(IF('.$wait_where.',id,null)) as wait_ids', //  对厂家来说是等待结算的工单ids 
            // 'IFNULL(SUM(IF('.$wait_where.',1,0)),0) as wait_end_nums',  	//  等待结算
            // 'IFNULL(SUM(IF('.$wait_where.',b.factory_total_fee_modify,0)),0) as wait_end_money',  	//  等待结算
            'IFNULL(SUM(IF(a.worker_order_status='.$status_18.',1,0)),0) as end_nums',  		//  结算
            'IFNULL(SUM(IF(a.worker_order_status='.$status_18.',b.factory_total_fee_modify,0)),0) as end_money',  		//  结算
        ];

        $total = BaseModel::getInstance(self::ORDER_TABLE_NAME)->getList([
                'alias' => 'a',
                'join'  => 'left join worker_order_fee b on a.id = b.worker_order_id',
                'field' => implode(',', $field_arr),
                'where' => [
                    'a.factory_id' => $fid,
                    'a.cancel_status' => ['in', OrderService::CANCEL_TYPE_NULL.','.OrderService::CANCEL_TYPE_CS_STOP],
                ],
            ]);
        // die(M()->_sql());
        $total = reset($total);

        $fr_model = BaseModel::getInstance(self::FACTORY_FROZEN_TABLE_NAME);
        $ing_ids = $total['ing_ids'];
        $total['frozen_money'] = $ing_ids ? $fr_model->where([
                'worker_order_id' => ['in', $ing_ids]
            ])->SUM('frozen_money') : 0;

        $wait_ids = $total['wait_ids'];

        $total['wait_end_nums']     = (string)count(array_filter(explode(',', $wait_ids)));
        $total['wait_end_money']    = $wait_ids ? $fr_model->where([
                'worker_order_id' => ['in', $wait_ids]
            ])->SUM('frozen_money') : 0;
        // die(M()->_sql());
        unset($total['ing_ids'], $total['wait_ids']);
        return $total;
    }
	
}
