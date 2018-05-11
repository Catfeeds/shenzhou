<?php
/**
* @User zjz
*/
namespace Api\Controller;

use Api\Controller\BaseController;
use Api\Common\ErrorCode;
use Api\Model\BaseModel;
use Common\Common\Service\AreaService;
use Common\Common\Service\AuthService;

class AreaController extends BaseController
{
	
	public function areas()
	{
		$parent_id = I('parent_id', 0);
		$areas = AreaService::index($parent_id);

		$this->responseList($areas);
	}

	public function areasTree()
	{
	    try {
	        $area_tree = AreaService::tree();

	        $this->responseList($area_tree);
	    } catch (\Exception $e) {
	        $this->getExceptionError($e);
	    }
//		$type_id = BaseModel::getInstance('cm_list')->getOne(['list_name' => 'area'], 'list_id');
//
//		if (!$type_id) {
//			$this->paginate();
//		}
//		$get = I('get.');
//		$where = ['list_id' => $type_id['list_id'], ['item_parent' => 0]];
//		$opt = [
//			'where' => $where,
//			'limit' => $this->page(),
//			'field' => 'list_item_id as value,item_desc as label,item_parent', // ,item_sort,lat,lng
//			'index' => 'value',
//		];
//		$model = BaseModel::getInstance('cm_list_item');
//		$count = $model->getNum($where);
//		$list = $count ?
//				$model->getList($opt):
//				[];
//
//		$ids = arrFieldForStr($list, 'value');
//		$list_2 = $ids ? $model->getList([
//						'where' => ['item_parent' => ['in', $ids]],
//						'index' => 'value',
//						'field' => $opt['field']
//					]) : [];
//
//		$ids = arrFieldForStr($list_2, 'value');
//		$list_3 = $ids ? $model->getList([
//						'where' => ['item_parent' => ['in', $ids]],
//						'field' => $opt['field']
//					]) : [];
//
//		foreach ($list_3 as $k => $v) {
//			$item_parent = $v['item_parent'];
//			unset($v['item_parent']);
//			$list_2[$item_parent]['children'][] = $v;
//		}
//
//		foreach ($list_2 as $k => $v) {
//			$item_parent = $v['item_parent'];
//			unset($v['item_parent'], $list[$item_parent]['item_parent']);
//			$list[$item_parent]['children'][] = $v;
//		}
//		// die(json_encode(array_values($list), JSON_UNESCAPED_UNICODE));
//		$this->response(array_values($list));
	}

	public function area()
	{
		$id = I('get.id', 0);
		$type_id = BaseModel::getInstance('cm_list')->getOne(['list_name' => 'area'], 'list_id');
		
		if (!$type_id) {
			$this->paginate();
		}

		$id = I('get.id', 0);
		$where = ['list_id' => $type_id['list_id'], ['list_item_id' => $id]];
		$model = BaseModel::getInstance('cm_list_item');
		try {
			$data = $model->getOneOrFail($where, 'list_item_id,item_desc,item_parent,lat,lng');
			$this->response($data);
		} catch (\Exception $e) {
			$this->getExceptionError($e);
		}
	}

}
