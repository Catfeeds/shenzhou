<?php
/**
* 
*/
namespace Admin\Model;

use Admin\Model\BaseModel;

class FactoryProductQrcodeModel extends BaseModel
{
	protected $trueTableName = 'factory_product_qrcode';
	
	// 指定厂家最近绑定产品
	public function getYimaAppliesBindProductByFid($f_id = 0, $where = [], $field = '*', $group_type = 1, $order_by = 'FPQ.datetime DESC')
	{
		$order_where = [
			'FPQ.factory_id' => $f_id,
			'_string' => 'FPQ.product_id is not null',
			'is_delete' => 0,
			'yima_status' => 0,
		];
		$where = $order_where + $where;
		
		$opt = [
			'alias' => 'FPQ',
			'join'  => 'LEFT JOIN factory_product FP ON FPQ.product_id = FP.product_id',
			'where' => $where,
			'field' => $field,
		];

		switch ($group_type) {
			case 1:
				$opt['group'] = 'FPQ.product_id';
				break;

			case 2:
				$opt['group'] = 'FP.product_guige';
				break;
		}

		if (strtolower($field) == 'count') {
			$count_opt = $opt;

			$count_opt['field'] = 'FPQ.id';
			$count_opt['fetchSql'] = ture;

			$nums = $this->query('SELECT count(*) AS ums FROM ('.$this->getList($count_opt).') C');
			return reset($nums)['ums'];
		}

		$opt['limit'] = getPage();
		$opt['order'] = $order_by;
		$list = $this->getList($opt);

		return $list;
	}

}
