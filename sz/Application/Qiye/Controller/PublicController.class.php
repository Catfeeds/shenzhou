<?php
/**
* 
*/
namespace Qiye\Controller;

use Common\Common\ErrorCode;
use Illuminate\Support\Arr;

class PublicController extends BaseController
{
    /*
     * 物流公司列表
     */
    public function expressCompanies()
    {
        try {
            $data = D('Public', 'Logic')->expressCompanies();
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 物流查询
     */
    public function expresses()
    {
        try {
            $param = ['type', 'data_id'];
            $key_arr = array_keys(I('get.'));
            foreach ($param as $v) {
                if (!in_array($v, $key_arr)) {
                    $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '缺少请求参数'.$v);
                }
            }
            $this->checkEmpty(I('get.'));
            $data = D('Public', 'Logic')->expresses(I('get.'));
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 获取jssdk
     */
    public function jssdkOption()
    {
        try {
            $option = [
                'scanQRCode',
                'chooseImage',
                'previewImage',
                'uploadImage',
                'getBrandWCPayRequest',
            ];
            $url = urldecode(I('get.url', ''));
            $config = [];
            $data = D('QiYeWechat', 'Logic')->getJsSdk($option, $url, $config);
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 根据id获取微信服务器上的图片
     */
    public function mediaToUrl()
    {
        try {
            $ids = I('post.ids', '');
            $data = D('QiYeWechat', 'Logic')->getMedias($ids);
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 联保价格
     */
    public function orderAtCategory()
    {
        try {
            $token = I('token', I('get.token'));
            if (!$token) {
                $headers = getallheaders();
                $token = $headers['token'];
            }
            if (empty($token)) {
                $this->fail(ErrorCode::SYS_USER_VERIFY_FAIL, '该版本暂不支持查看联保价格，请更新APP版本');
            }
            $data = D('Public', 'Logic')->orderAtCategory($this->requireAuth());
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 推送给厂家和技工相关更新短信
     */
    public function sendSmsToFactoryAndWorker()
    {
        try {
            $data = D('Public', 'Logic')->sendSmsToFactoryAndWorker();
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 旧保外单修改支付状态为已完成
     */
    public function updateOldWarrantyOrder()
    {
        try {
            $data = D('Public', 'Logic')->updateOldWarrantyOrder();
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}
