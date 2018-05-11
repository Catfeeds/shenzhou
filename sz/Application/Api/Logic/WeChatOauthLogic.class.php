<?php
/**
* @User zjz
*/
namespace Api\Logic;

use Api\Logic\BaseLogic;
use Api\Common\ErrorCode;
use Api\Model\BaseModel;
use EasyWeChat\Foundation\Application;
use Library\Common\Util;
use Think\Log;

class WeChatOauthLogic extends BaseLogic
{
	
	public $oauth;

	function __construct($config = [])
	{
		$config = array_merge(C('easyWeChat'), $config);
		$app = new Application($config);
        Log::record(file_get_contents('php://input'));
        $this->oauth = $app->oauth;
	}

	public function getWxUser()
	{
		if (I('code')) {
			$data = $this->oauth->user()->toArray();
		} else {
			$this->oauth->redirect()->send();
			die;
		}
		// 验证失败，或程序错误
		if (!$data['id']) {
			die('出错，请返回重新进入');
		}
		return $data;
	}
}
