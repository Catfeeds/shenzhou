<?php
/**
* @User zjz
*/
namespace Api\Logic;

use Api\Common\ErrorCode;
use Api\Model\BaseModel;
use EasyWeChat\Foundation\Application;
use EasyWeChat\Message\Image;
use EasyWeChat\Message\News;
use EasyWeChat\Message\Text;
use Library\Common\Util;
use Think\Log;
use Api\Logic\BaseLogic;

class WeChatUserLogic extends BaseLogic
{
	
	protected $user;

	function __construct()
	{
        $config = C('easyWeChat');
		$app = new Application($config);
        Log::record(file_get_contents('php://input'));
        $this->user = $app->user;
        return $this->user;
	}

	public function getOneByOpenId($open_id = '')
	{
		$user = $this->user->get($open_id);
		return $user;
	}

	public function isSubscribe($open_id = '')
	{
		$user = $this->getOneByOpenId($open_id);
		return $user->subscribe ? true : false;
	}
	
}