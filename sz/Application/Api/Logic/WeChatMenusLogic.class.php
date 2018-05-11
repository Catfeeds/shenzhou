<?php
/**
* @User zjz
* @Date 2016/12/5
* @mess 公众平台信息及事件处理
*/
namespace Api\Logic;

use Api\Logic\BaseLogic;
use Api\Common\ErrorCode;
use Api\Model\BaseModel;
use EasyWeChat\Foundation\Application;
use Think\Log;

class WeChatMenusLogic extends BaseLogic
{

	const ALL_MENU = 1;
	const CURRENT_MENU = 2;

	protected $menu;

	function __construct()
	{
        $config = C('easyWeChat');
		$app = new Application($config);
        Log::record(file_get_contents('php://input'));
        $this->menu = $app->menu;
        return $this->menu;
	}

	/**
	* @User zjz
	* @Mess 获取微信菜单栏
	* @$type 获取类型
	*/
	public function getMemus($type)
	{
		$list = [];
		switch ($type) {
			case self::ALL_MENU:
				$list = $this->menu->all();
				break;
			
			case self::CURRENT_MENU:
				$list = $this->menu->current();
				break;
		}

		return $list;
	}

	public function addMenuOrFail($datas)
	{
		return $this->menu->add($datas);
	}	

	public function addMatchRuleMenuOrFail($datas, $matchRule)
	{
		return $this->menu->add($datas, $matchRule);
	}

	public function deleteByIdOrFail($id)
	{
		if ($id) {
			return $this->menu->destroy($id);
		} else {
			$this->throwException(ErrorCode::SYS_DATA_NOT_EXISTS);
			
		}
	}

	public function deleteAll()
	{
		return $this->menu->destroy();
	}

	// click（点击）, 
	// view（跳转Url）, 
	// scancode_push（启动扫码）, 
	// scancode_waitmsg（启动提示扫码，并等待）, 
	// pic_sysphoto（启动拍照）, 
	// pic_photo_or_album（发送本地图片火拍照）, 
	// pic_weixin（发送本地图片火拍照，并等待）, 
	// location_select（启动定位）, 
	// media_id（下发素材）, 
	// view_limited（跳转至指定素材的url）
	public function checkData($datas)
	{
		$return = [];
		return $return;
	}

}
