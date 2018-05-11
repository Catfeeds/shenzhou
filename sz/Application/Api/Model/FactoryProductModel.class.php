<?php
/**
* @User zjz
* @Date 2016/12/12
*/
namespace Api\Model;

use Api\Model\BaseModel;
use Api\Common\ErrorCode;
use Common\Common\Service\OrderService;

class FactoryProductModel extends BaseModel
{
    public function checkMyProductCreateOrder($my_pid = 0)
	{
		$check_data = BaseModel::getInstance('wx_user_product')->getOneOrFail($my_pid);
		$data = BaseModel::getInstance('worker_order_user_info')->getOne([
				'alias' => 'WUO',
				'join'  => 'LEFT JOIN worker_order WO ON WUO.order_id = WO.order_id LEFT JOIN worker_order_detail WOD ON WO.order_id = WOD.worker_order_id',
				'where' => [
					'WO.is_complete' => 0,
					'WO.is_fact_cancel' => 0,
					'WO.is_delete' => 0,
					'WOD.product_id' => $check_data['wx_product_id'],
				],
				'field' => 'WO.*',
			]);
		if ($data) {
			$this->throwException(ErrorCode::MYPRODUCT_IS_A_ORDER);
		}
		
		return $data;

	}

    public function checkMyProductCreateOrderByCode($code = 0, $is_return = false)
    {
        $data = BaseModel::getInstance('worker_order_user_info')->getOne([
                'alias' => 'WUO',
                'join'  => 'LEFT JOIN worker_order WO ON WUO.worker_order_id = WO.id LEFT JOIN worker_order_product WOP ON WO.id = WOP.worker_order_id',
                'where' => [
                    'WO.worker_order_status' => ['NEQ', OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED],
                    'WO.cancel_status' => ['IN', [OrderService::CANCEL_TYPE_NULL, OrderService::CANCEL_TYPE_CS_STOP]],
                    'WOP.yima_code' => $code,
                ],
                'field' => 'WO.*',
            ]);
        
        if ($data && !$is_return) {
            $this->throwException(ErrorCode::MYPRODUCT_IS_A_ORDER);
        }
    
        return $data;
    }

	public function getServiceById($id = 0)
	{
		$data = $this->getOneOrFail($id);
		return BaseModel::getInstance('factory_category_service_cost')->getOne([
				'factory_id' => $data['factory_id'],
				'cat_id' => $data['product_category'],
			]);
	}

	public function getTopProductCateByCid($cid = 0)
    {
        if (!$cid) {
            return [];
        }
        $list = $this->eachGetParentCmByIds($cid, [], true);
        return $this->getTopParentByCidForList($cid, $list, true);
    }

    public function eachGetParentCmByIds($ids = '', $list = [], $is_index = false)
    {
    	$id_arr = $list ? explode(',', $ids) : array_unique(array_filter(explode(',', $ids)));
    	
    	if ($id_arr) {
    		$where = [
    			'list_item_id' => ['in', implode(',', $id_arr)],
    		];
    		// TODO 作用？
//    		if ($type_id) {
//    			$where['list_id'] = $type_id;
//    		}
    		$opt = [
    			'where' => $where,
    			'index' => 'list_item_id',
    		];
    		$datas = BaseModel::getInstance('cm_list_item')->getList($opt);

    		$id_arr = [];
    		foreach ($datas as $k => $v) {
                $list[$k] = $v;
                if (!$v['item_parent'] || $v['item_parent'] == $v['list_item_id']) {
                    continue;
                }
    			$id_arr[$v['item_parent']] = $v['item_parent'];
    		}
    		$ids = implode(',', $id_arr);
    		// $list = array_merge_recursive($list, $datas);
    		// $ids = arrFieldForStr($datas, 'item_parent');

    		if ($ids) {
    			return $this->eachGetParentCmByIds($ids, $list, $is_index);
    		}
    	} 
    	return $is_index ? $list : array_values($list) ;
    }

    public function getTopParentByCidForList($cid, $list = [], $is_index = false)
    {
    	if (!$is_index) {
    		$new = [];
    		foreach ($list as $key => $value) {
    			$new[$value['list_item_id']] = $value;
    		}
    		$list = $new;
    	}

    	if ($list[$cid]['item_parent'] === '0' || $cid === $list[$cid]['item_parent']) {
	    	return $list[$cid];
    	}

    	return $this->getTopParentByCidForList($list[$cid]['item_parent'], $list, $is_index);

    }

}
