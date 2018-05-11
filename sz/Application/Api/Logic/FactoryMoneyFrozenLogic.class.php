<?php
/**
* @User zjz
* @Date 2016/12/14
* @Mess 冻结金
*/
namespace Api\Logic;

use Api\Logic\BaseLogic;
use Api\Common\ErrorCode;
use Api\Model\BaseModel;
use Common\Common\Service\AuthService;

class FactoryMoneyFrozenLogic extends BaseLogic
{
	/**
	 * @User zjz
	 * 取消冻结金
	 */
	public function unFrozenByOrderInfo($order_info = []){
		
		if (!$order_info['order_id']) {
			return false;
		}

		//冻结资金记录
		$where = [
			'factory_id' => $order_info['factory_id'],
			'order_id' => $order_info['order_id'],
		];

		$model = BaseModel::getInstance('factory_money_frozen');
		$frozen_info = $model->getOne($where);

		if (!$frozen_info) {
			return true;
		}

		//厂家信息
		$factory_model = BaseModel::getInstance('factory');
		$fact_info = $factory_model->getOne($order_info['factory_id']);


		//重新计算新的冻结值并入库
		$fact_data = [
			'factory_id' => $fact_info['factory_id'],
			'frozen_money' => $fact_info['frozen_money'] - $frozen_info['frozen_money'],
		];

		if($factory_model->update($fact_data)){
			// 删除冻结资金记录
			return (false === $model->remove($where)) ? false : true;
		}

		return true;
	}
		
}
