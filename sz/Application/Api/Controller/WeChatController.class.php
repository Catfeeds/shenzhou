<?php
namespace Api\Controller;

use Api\Logic\WeChatMenusLogic;
use Api\Controller\BaseController;
use EasyWeChat\Foundation\Application;

class WeChatController extends BaseController
{
	public function index()
	{
		try {
			D('WeChatNewsEvent', 'Logic')->run();
		} catch (\Exception $e) {
			$this->getExceptionError($e);
		}
	}

	public function scancodePush()
	{
		var_dump(I('post.'), I('get.'));
	}

	public function getMemus()
	{
		$list = (new WeChatMenusLogic())->getMemus(1);
		$this->response($list);
	}

	public function setMenu()
	{
		$post = I('post.button');
		foreach ($post as $key => $value) {
		    foreach ($value['sub_button'] as $k => $v) {
		        //去掉url中的转义
                $post[$key]['sub_button'][$k]['url'] = html_entity_decode(html_entity_decode($v['url']));
            }
        }
		try {
			$list = (new WeChatMenusLogic())->addMenuOrFail($post);
			$this->okNull();
		} catch (\Exception $e) {
			$this->getExceptionError($e);
		}
	}

	public function deleteAll()
	{
		$menu = (new WeChatMenusLogic())->deleteAll();
		$this->okNull();
	}

	public function delete()
	{
		$mid = I('get.mid', '');
		(new WeChatMenusLogic())->delete($mid);
		$this->okNull();
	}

	public function jssdkOpt()
	{
		$options = C('easyWeChat');
		$app = new Application($options);
		$js = $app->js;
		$js->setUrl(urldecode(I('url')));
		$con = $js->config([
				'scanQRCode',
				'uploadImage',
				'chooseWXPay',
				'chooseImage',
			], false);
		$this->response([
				'json' => $con,
			]);
	}

	public function payConfig()
	{
		$get = I('get.');
		$config = [];
		try {
			switch ($get['type']) {
				case 'worker_order_service':
					$config = D('WeChatPayment', 'Logic')->getIsOutOrderServicePayConfigByOrderId($get['id']);
					break;
				
				default:
					
					break;
			}
			$this->response([
				'json' => $config,
			]);
		} catch (\Exception $e) {
			$this->getExceptionError($e);
		}

	}

	public function wxpaynotify()
	{
		try {
			$notify = D('WeChatPayment', 'Logic')->payNotify();
        	return $notify;
		} catch (\Exception $e) {
			$this->getExceptionError($e);
		}
	}

	/*
	 * 微信支付
	 */
	public function jsPay()
    {
        try {
            $res = D('WeChatPayment', 'Logic')->jsPay(I('get.'), $this->requireAuth());
            $this->response($res);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 微信支付回调
     */
    public function payNotify()
    {
        try {
            $res = D('WeChatPayment', 'Logic')->payNotify();
            $this->response($res);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 支付费用详情
     */
    public function payInfo()
    {
        try {
            $res = D('WeChatPayment', 'Logic')->payInfo(I('get.id'), $this->requireAuth());
            $this->response($res);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}
