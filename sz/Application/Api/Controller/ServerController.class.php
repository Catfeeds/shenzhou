<?php
/**
* @User zjz
* @Date 2016/12/16
*/
namespace Api\Controller;

use Api\Controller\BaseController;
use Api\Model\UserModel;
use Common\Common\Logic\QueueLogic;

class ServerController extends BaseController
{
	
	public function smsQueue()
	{
		$this->checkApiSecretParam();
		$logic = new QueueLogic();
		$logic->smsQueue();
	}

}
