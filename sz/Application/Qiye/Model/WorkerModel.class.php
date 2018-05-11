<?php

/**
 * File: BaseModel.class.php
 * User: huangyingjian
 * Date: 2017/11/1
 */

namespace Qiye\Model;

use Qiye\Model\BaseModel;
use Common\Common\Service\WorkerService;

class WorkerModel extends BaseModel
{
	const WORKER_ADJUST_TABLE_NAME = 'worker_money_adjust_record';
	const WORKER_ORDER_TABLE_NAME = 'worker_order';
	const WORKER_MONEY_RECORD_TABLE_NAME = 'worker_money_record';
	const WORKER_ORDER_REPUTATION_TABLE_NAME = 'worker_order_reputation';

	//技工评价
    public function workerServices($worker_id)
    {
        $model = BaseModel::getInstance(self::WORKER_ORDER_REPUTATION_TABLE_NAME);
        $client_code_lv1 = $model->getNum([
            'worker_id'   => $worker_id,
            'is_complete' => 1,
            'revcode'     => 'A',
            'sercode'     => 'A'
        ]);
        $client_code_lv3 = $model->getNum([
            'worker_id'   => $worker_id,
            'is_complete' => 1,
            'revcode'     => 'C',
            'sercode'     => 'C'
        ]);
        return [
            'client_code_lv1' => $client_code_lv1,
            'client_code_lv3' => $client_code_lv3
        ];
    }

	// 技工的全部金额明细变动
	public function allBalanceLogs($worker_id = 0, $type = 0)
	{
		if (!$worker_id) {
			return [];
		}

        $order_record_types =  WorkerService::WORKER_MONEY_RECORD_REPAIR_OUT.','.WorkerService::WORKER_MONEY_RECORD_REPAIR;
        $withdrawcashed_record_types =  WorkerService::WORKER_MONEY_RECORD_WITHDRAWCASHING.','.WorkerService::WORKER_MONEY_RECORD_WITHDRAWCASHED;

        switch ($type) {
            case WorkerService::WORKER_MONEY_REPAIR_TYPE:
                $where_string = 'C.type in ('.$order_record_types.')';
                break;

//            case WorkerService::WORKER_MONEY_WITHDRAWCASH_RECORD_TYPE:
//                $where_string = 'C.type in ('.$withdrawcashed_record_types.')';
//                break;

            default:
                $where_string = 'C.type = '.$type;
                break;
        }

        !in_array($type, WorkerService::WORKER_MONEY_RECORD_ALL_TYPE_VALUE) && $where_string = 1;

		$model = M();
		$sql = $model->field('id,concat("提现单号 ",withdraw_cash_number) as title,'.WorkerService::WORKER_MONEY_WITHDRAWCASH_RECORD_TYPE.' as type,-`out_money` as money,concat(status,"_",IF(withdrawcash_excel_id>0,1,0)) as other,fail_reason as remarks,create_time')
		      ->table('worker_withdrawcash_record')
		      ->union([
		      		'SELECT id,worker_order_id as title,'.WorkerService::WORKER_MONEY_ADJUST_RECORD_TYPE.' as type,adjust_money,concat("'.WorkerService::WORKER_MONEY_ADJUST_RECORD_TYPE.'","_",adjust_type) as other,adjust_remark as remarks,create_time FROM '.self::WORKER_ADJUST_TABLE_NAME.' where worker_id = '.$worker_id,
		      	], true)
		      ->union([
		      		'SELECT data_id as id,"" as title,type,money,"" as other,"" as remarks,create_time FROM '.self::WORKER_MONEY_RECORD_TABLE_NAME.' where type in ('.$order_record_types.') and worker_id = '.$worker_id,
		      	], true)
		      ->fetchSql(true)
		      ->where(['worker_id' => $worker_id])
		      ->select();

		$count =  $model->query("SELECT count(*) as counts FROM ( {$sql} ) C where {$where_string} ");

		$list =  $model->query("SELECT * FROM ({$sql}) C where {$where_string} ORDER BY C.create_time DESC LIMIT ".getPage());

		return [$list, reset($count)['counts']];
	}

	public function getBalanceLogsDetail($id = 0, $type = 0)
	{
		$return = null;
		switch ($type) {
			case WorkerService::WORKER_MONEY_RECORD_ADJUST:
				$field_arr = [
					'a.id',
					'a.worker_id',
					'a.worker_order_id',
					'a.adjust_money as money',
					'a.admin_id',
					'a.cp_admin_name',
					'b.orno',
					'a.create_time',
					'a.adjust_remark as remarks',
				];
				$return = BaseModel::getInstance(self::WORKER_ADJUST_TABLE_NAME)->getOneOrFail([
						'alias' => 'a',
						'field' => implode(',', $field_arr),
						'join'	=> 'left join '.self::WORKER_ORDER_TABLE_NAME.' b on a.worker_order_id = b.id',
						'where' => [
							'a.id' => $id,
						],
					]);
				break;
		}

		return $return;
	}

}
