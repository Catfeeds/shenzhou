<?php
/**
* 
*/
namespace Api\Controller;

use Api\Common\ErrorCode;
use Api\Controller\BaseController;
use Api\Model\BaseModel;
use Api\Logic\WeChatOauthLogic;
use Common\Common\CacheModel\WxUserCacheModel;
use EasyWeChat\Foundation\Application;
use Library\Common\Arr;
use Library\Common\Util;
use Library\Crypt\AuthCode;
use Overtrue\Socialite\AuthorizeFailedException;
use Think\Log;

class LoginController extends BaseController
{
	
	public function wxLogin()
	{
		$web = Util::getServerUrl() . $_SERVER['SCRIPT_NAME'];
        $url = I('url', '', 'urldecode'); // 授权成功后跳转的链接

        try {
            $conf = C('easyWeChat');
            $conf['oauth']['scopes'] = ['snsapi_userinfo'];
            $conf['oauth']['callback'] = $web.'/wechat/login?url='.urlencode($url);

            $app = new Application($conf);
            $oauth = $app->oauth;
            if (!I('code')) {
                $oauth->redirect()->send();
                die;
            }

            $user = $oauth->user()->toArray();
            $user_detail = $app->user->get($user['id']);
            Log::record(\GuzzleHttp\json_encode($user_detail));

            $data = BaseModel::getInstance('wx_user')->getOne(['openid' => $user['id'], 'is_delete' => 0]);
            if (!$data) {
                $user_data = [
                    'openid' => $user['id'],
                    'unionid' => $user_detail['unionid'],
                    'nickname' => $user['nickname'],
                    'headimgurl' => $user['avatar'],
                    'sex' => $user['original']['sex'],
                    'area' => $user['original']['country'] . $user['original']['province'] . $user['original']['city'],
                    'add_time' => NOW_TIME,
                ];
                $data['id'] = BaseModel::getInstance('wx_user')->insert($user_data);
                $data['user_type'] = 0;
            } elseif (!$data['unionid']) {
                WxUserCacheModel::update($data['id'], ['unionid' => $user_detail['unionid']]);
//                BaseModel::getInstance('wx_user')->update($data['id'], ['unionid' => $user_detail['unionid']]);
            }

            $s = 24*3600;
            $token_data = [
                'user_id' => $data['id'],
                'type' => 'wxuser',
                'expire_time' => NOW_TIME + $s,
            ];
            $token = AuthCode::encrypt(json_encode($token_data), C('TOKEN_CRYPT_CODE'), $s);

            $url_arr = explode('?', $url);
            $url = $url_arr[0];

            $reutrn_url_data = [];
            if ($url_arr[1]) {
                parse_str($url_arr[1], $reutrn_url_data);
            }

            $reutrn_url_data['token'] = trim($token, '=');
            $reutrn_url_data['user_type'] = 'wxuser';
            $reutrn_url_data['user_id'] = $data['id'];
            $reutrn_url_data['type'] = $data['user_type'];
            $reutrn_url_data['had_phone'] = $data['telephone'] ? 1 : 0;
            $reutrn_url_data['phone'] = $data['telephone'];
            $reutrn_url_data['headimgurl'] = $data['headimgurl'];
            $reutrn_url_data['nickname'] = $data['nickname'];

            $url .= '?'.http_build_query($reutrn_url_data);

            header('location:'. $url);
        } catch (AuthorizeFailedException $e) {
            // 登录失败时重新登录（主要防止40029,Invalid code,可能是微信作2次响应导致2次相同跳转，也可能是用户重新返回导致）
            header('location:' . Util::getServerUrl() . $_SERVER['SCRIPT_NAME'] . '/wechat/login?' . http_build_query(Arr::only($_GET, ['url'])));
        } catch (\Exception $e) {
            Log::record($e);
            $this->getExceptionError($e);
        }




//        // 已经登陆直接跳转
//        if ($this->checkAuth()) {
//            header('location:'. $url);
//            die;
//        }
//
//        $config['oauth'] = [
//          'scopes'   => ['snsapi_userinfo'], // snsapi_base snsapi_userinfo
//          'callback' => $web.'/wechat/login?url='.urlencode($url),
//        ];
//
//        $wx_data = (new WeChatOauthLogic($config))->getWxUser();
//
//        $pen_id = $wx_data['openid'] ? $wx_data['openid'] : $wx_data['id'];
//
//        // 登录成功 生成token
//        $user = BaseModel::getInstance('wx_user')->getOne(['openid' => $pen_id]);
//        if (!$user['id']) {
//            $user = D('Register', 'Logic')->subscribeByOpenId($pen_id);
//        }
//        $s = 24*3600;
//        $expire_time = NOW_TIME + $s;
//        $token_data = [
//            'user_id' => $user['id'],
//            'type' => 'wxuser',
//            'expire_time' => $expire_time,
//         ];
//        $token = AuthCode::encrypt(json_encode($token_data), C('TOKEN_CRYPT_CODE'), $s);
//        $expire_time = $expire_time;
//
//        $url_arr = explode('?', $url);
//        $url .= (count($url_arr) > 1) ? '&' : '?';
//        $had_phone = $user['telephone'] ? 1 : 0;
//        $url .= 'token='.$token.'&user_type=wxuser&user_id='.$user['id'].'&type='.$user['user_type'].'&had_phone='.$had_phone.'&phone='.$user['telephone'];
//
//        header('location:'. $url);
	}

}
