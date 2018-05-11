<?php
/**
* 
*/
namespace Api\Model;

class FactoryModel extends BaseModel
{
	
	public function getWorkerNeedPhone($f_id = 0)
	{
		$data = $this->getOne($f_id, 'linkphone,qrcode_tell');
		return $data['qrcode_tell'] ? $data['qrcode_tell'] : $data['linkphone'];
	}

}
