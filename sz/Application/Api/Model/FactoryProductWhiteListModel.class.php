<?php
/**
* @User zjz
* @Date 2016/12/20
*/
namespace Api\Model;

use Api\Common\ErrorCode;
use Api\Model\BaseModel;
use Common\Common\Service\AuthService;

class FactoryProductWhiteListModel extends BaseModel
{
	
	public function checkThisFactoryAgencyByFid($id = 0)
	{
		if (!AuthService::getAuthModel()->user_type || AuthService::getModel() != 'wxuser') {
            $this->throwException(ErrorCode::USER_NOT_AGENCY);
        }

        if (!AuthService::getAuthModel()->telephone) {
            $this->throwException(ErrorCode::YOU_NOT_PHONE);
        }

        $where = [
            'user_name' => AuthService::getAuthModel()->telephone,
            'factory_id' => $id,
        ];

        return $this->getOne($where);
	}
	
}
