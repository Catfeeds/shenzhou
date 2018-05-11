<?php
/**
* 
*/
namespace Api\Controller;

use Api\Common\ErrorCode;
use Api\Controller\BaseController;
use Api\Model\BaseModel;
use Library\Common\Util;

class FactoryController extends BaseController
{
	
	public function info()
	{
		$id = I('get.id', 0);
		try {
			$data = BaseModel::getInstance('factory')->getOneOrFail($id, 'factory_id,factory_full_name,factory_short_name');
			$this->response($data);
		} catch (\Exception $e) {
			$this->getExceptionError($e);
		}
	}

}
