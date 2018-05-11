<?php
/**
* @User zjz
* @Date 2016/12/12
*/
namespace Api\Model;

use Api\Model\BaseModel;
use Api\Common\ErrorCode;

class FactoryExcelModel extends BaseModel
{

	// TODO 命名错误 应该是 getExcelDataByMd5CodeOrFail
	public function getExcelDataByMyPidOrFail($md5 = '', $field = '*')
	{
		return (new \Api\Model\YimaModel())->getYimaInfoByEnCode($md5);

		// if (strlen($md5) != 32) {
		// 	return [];
		// }
		// $k = substr($md5, 0, 1);

		// $where = [
  //   		'md5code' => $md5
  //   	];
  //   	$opt = [
  //   		'where' => $where,
  //   		'field' => $field,
  //   	];
		// $excel_data = BaseModel::getInstance('factory_excel_datas_'.$k)->getOneOrFail($opt);
		// return $excel_data;
	}

}
