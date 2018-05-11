<?php
/**
* @User zjz
*/
namespace Api\Logic;

use Api\Common\ErrorCode;
use Api\Model\BaseModel;
use Api\Logic\BaseLogic;

class RegisterLogic extends BaseLogic
{

	public function subscribeByOpenId($open_id)
	{
		$model = BaseModel::getInstance('wx_user');
		$data = $model->getOne(['openid' => $open_id]);
		if ($data) {
			return $data;
		}
		$wx_user = D('WeChatUser', 'Logic')->getOneByOpenId($open_id);
		if (!$wx_user->openid) {
			$this->throwException(ErrorCode::SYS_USER_VERIFY_FAIL);
		}
		$area = $wx_user->country.$wx_user->province.$wx_user->city;
		$data = [
			// 'openid' => $wx_user->openid,
			// 'nickname' => $wx_user->nickname ? $wx_user->nickname : '',
			// 'headimgurl' => $wx_user->headimgurl ? $wx_user->headimgurl : '',
			// 'sex' => $wx_user->sex ? $wx_user->sex : '',
			// 'area' => $area ? $area : '',
			'openid' => $wx_user->openid,
			'nickname' => $wx_user->nickname,
			'headimgurl' => $wx_user->headimgurl,
			'sex' => $wx_user->sex,
			'area' => $area,
		];
		$id_or_false = $model->insert($data);
		false === $id_or_false && $this->throwException(ErrorCode::SYS_DB_ERROR, '授权失败，请重试');
		$data['id'] = $id_or_false;
		return $data;
	}

}
