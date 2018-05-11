<?php
/**
* @User zjz
* @Date 2016/12/12
*/
namespace Api\Model;

use Api\Model\BaseModel;
use Api\Common\ErrorCode;

class WorkerOrderDetailModel extends BaseModel
{

    protected $tableName = 'worker_order_product';
	
	public $md5_code = 'shenzhou';

	public function codeToMd5Code($code = '')
	{
		if (!strlen($code)) {
			return [];
		}
		$num = strlen($code) - 1;
		$code = substr($code, -$num);
		return md5(md5($code.$this->md5_code));
	}

	public function getFieldByKey($key = '')
	{
		$fields = [
			'orders_product_info' => 'WOD.worker_order_id,WOD.fault_id,WOD.servicefault,FP.product_id,WOD.servicepro as product_cate_id,WOD.servicepro_desc as product_cate_name,WOD.code, FP.product_xinghao,FP.product_status,FP.factory_id,FP.product_thumb',
		];
		return $fields[$key] ? $fields[$key] : '';
	}

	/**
	 * @User zjz
	 * 获取指定的订单的产品信息
	 */
	public function  getOrdersProductByOrderIds($ids = '', $index = '', $field = '*')
	{
		$id_arr = array_unique(array_filter(explode(',', $ids)));
		if (!$id_arr) {
			return [];
		}
		$opt = [
			'alias' => 'WOD',
			'join'  => 'LEFT JOIN factory_product FP ON WOD.product_id = FP.product_id',
			'field' => $field,
			'where' => [
				'worker_order_id' => ['in', implode(',', $id_arr)]
			],
		];

		$index && ($opt['index'] = $index);
		
		$list = $this->getList($opt);

		// 图片缩略图
		if (strstr($field, 'product_thumb') || $field == '*') {
			$product_id_key = keyForAsEndKey('product_id', $field);
			$product_thumb_key = keyForAsEndKey('product_thumb', $field);
			$servicepro_key = keyForAsEndKey('servicepro', $field);
			$cate_product_thumb = $product_thumb = $cate_id_arr = [];
			foreach ($list as $k => $v) {
				if ($v[$product_thumb_key]) {
					$product_thumb[$v[$product_id_key]] = $v[$product_thumb_key];
				} else {
					$cate_id_arr[$v[$product_id_key]] = $v[$servicepro_key];	
				}
			}

			if (count($cate_id_arr)) {
				$opt = [
					'where' => [
						'list_item_id' => ['in', implode(',', array_unique($cate_id_arr))],
					],
					'field' => 'list_item_id,item_thumb',
					'index' => 'list_item_id',
				];
				$cate_product_thumb = BaseModel::getInstance('cm_list_item')->getList($opt);
			}

			$this_product_thumb = '';
			foreach ($list as $k => $v) {
				$this_product_thumb = $product_thumb[$v[$product_id_key]] ? $product_thumb[$v[$product_id_key]] : $cate_product_thumb[$v[$servicepro_key]]['item_thumb'];

				// $v[$product_thumb_key] = $this_product_thumb ? Util::getServerFileUrl($this_product_thumb) : '';
				$v[$product_thumb_key] = $this_product_thumb ? $this_product_thumb : '';
				$list[$k] = $v;
			}
		}

		return $list;
	}

	/**
	 * @User zjz
	 * 获取指定的Code的购买信息
	 */
	public function getExcelDatasByCodes($codes = '', $field = '*', $is_index = false)
	{
		if (!str_replace(',', '', $codes)) {
			return [];
		}
		$code_arr = array_unique(array_filter(explode(',', $codes)));

		$new = [];
		foreach ($code_arr as $k => $v) {
			$id = getFidByCode($v);
			if ($id) {
				$new[factoryIdToModelName($id)][] = $v;
			}
		}

		$return = [];
		foreach ($new as $k => $v) {
			$opt = [
				'field' => $field,
				'where' => [
					'code' => ['in', implode(',', array_filter($v))]
				]
			];
			if ($is_index) {
				$opt['index'] = 'code';
			}
			$list = BaseModel::getInstance($k)->getList($opt);
			$return += $list;
		}
		
		return $return;
	}

	/**
	 * @User zjz
	 * 获取指定的md5code的购买信息
	 */
	public function getExcelDatasByMd5Codes($md5codes = '', $field = '*', $is_index = false)
	{
		if (!str_replace(',', '', $md5codes)) {
			return [];
		}
		$arr = array_unique(array_filter(explode(',', $md5codes)));
		
		$new = [];
		foreach ($arr as $k => $v) {
			$key = substr($v, 0, 1);
			$new[$key][] = $v;
		}

		// md5code 转 code
		$code_md5 = $f_code = [];
		foreach ($new as $k => $v) {
			foreach (BaseModel::getInstance('old_yima_code_index_'.$k)->getList(['md5code' => ['in', implode(',', array_filter($v))]]) as $key => $value) {
				$f_code[yimaCodeToModelName($value['code'])][] = $value['code'];
				$code_md5[$value['code']] = $value['md5code'];
			}
		}
		
		$in_yima = $return = [];
		foreach ($f_code as $k => $v) {
			$opt = [
				'field' => $field,
				'where' => [
					'code' => ['in', implode(',', array_filter($v))]
				]
			];
			if ($is_index) {
				$opt['index'] = 'code';
			}
			// $list = BaseModel::getInstance('factory_excel_datas_'.$k)->getList($opt);
			foreach (BaseModel::getInstance($k)->getList($opt) as $key => $value) {
				$value['md5code'] = $code_md5[$value['code']];
				$list[$key] = $value;

				$in_yima[$value['code']] = $value['md5code'];
			}

			$return += $list;
		}

		$yimo_model = new \Api\Model\YimaModel();
		foreach (array_diff_key($code_md5, $in_yima) as $key => $value) {
			if ($is_index) {
				$return[$value] = $yimo_model->getYimaInfoByCode($key);
			} else {
				$return[] = $yimo_model->getYimaInfoByCode($key);
			}
		}

		return $return;
	}

	/**
	 * @User zjz
	 * 获取指定的md5code的订单详情信息 不包括其他的orderDetail
	 */
	public function getWorkerOrderDetailByMd5Codes($md5codes = '', $field = '*', $is_index = false)
	{
		if (!str_replace(',', '', $md5codes)) {
			return [];
		}
		$arr = array_unique(array_filter(explode(',', $md5codes)));

		$new = [];
		foreach ($arr as $k => $v) {
			$key = substr($v, 0, 1);
			$str = $new[$key];
			$new[$key] = trim($str.','.$v, ',');
		}
		$return = [];
		foreach ($new as $k => $v) {
			$opt = [
				'alias' => 'FEDS',
				'join'  => 'LEFT JOIN worker_order_product WOP ON FEDS.code = WOP.code',
				'field' => $field,
				'where' => [
					'FEDS.md5code' => ['in', $v]
				]
			];
			if ($is_index) {
				$opt['index'] = 'md5code';
			}
			$list = BaseModel::getInstance('factory_excel_datas_'.$k)->getList($opt);
			$return = array_merge($return, $list);
		}
		return $return;
	}

	/**
	 * @User zjz
	 * 获取指定的md5code的订单信息 不包括其他的orderDetail
	 */
	public function getOrdersByMd5Codes($md5codes = '', $field = '*', $is_index = false)
	{

		$list = $this->getWorkerOrderDetailByMd5Codes($md5codes);
		$check = $arr = [];
		foreach ($list as $k => $v) {
			if (!$v['worker_order_id']) {
				continue;
			}
			$arr[$v['worker_order_id']] = $v['worker_order_id'];
			$check[$v['worker_order_id']] = $v;
		}
		
		if (!$arr) {
			return  [];
		}

		$datas = D('WorkerOrder')->getList([
				'where' => [
					'order_id' => implode(',', $arr)
				],
			]);

		$return = [];
		foreach ($datas as $k => $v) {
			$v['order_detail'] = $check[$v['order_id']];
			$return[] = $v;
		}

		return $return;
	}

}
