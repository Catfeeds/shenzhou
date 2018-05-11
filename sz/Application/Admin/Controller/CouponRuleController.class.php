<?php
/**
 * File: DrawController.class.php
 * Function:
 * User: cjy
 * Date: 2017/12/07
 */

namespace Admin\Controller;

use Admin\Common\ErrorCode;
use Common\Common\Service\AuthService;
use Common\Common\Service\ShortUrlService;

class CouponRuleController extends BaseController
{

    public function getList()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);
            $result = D('CouponRule', 'Logic')->getList();
            $this->paginate($result['data'], $result['count']);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }
    
    public function add()
    {
        try {
            $adminId = $this->requireAuth([AuthService::ROLE_ADMIN]);
            D('CouponRule', 'Logic')->add($adminId);
            $this->response(null);
        } catch (\Exception $e) {
            M()->rollback();
            $this->getExceptionError($e);
        }
    }

    public function update()
    {
        $param = I('put.');
        try {
            $adminId = $this->requireAuth([AuthService::ROLE_ADMIN]);
            D('CouponRule', 'Logic')->update($param,$adminId);
            
            $this->response(null);
        } catch (\Exception $e) {
            M()->rollback();
            $this->getExceptionError($e);
        }
    }

    public function view()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);
            $data = D('CouponRule', 'Logic')->view();
            $this->response($data);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function operate()
    {
        try {
            $adminId = $this->requireAuth([AuthService::ROLE_ADMIN]);
            D('CouponRule', 'Logic')->operate($adminId);

            $this->response(null);
        } catch (\Exception $e) {
            M()->rollback();
            $this->getExceptionError($e);
        }
    }

    public function send()
    {
        try {
            $this->requireAuth([AuthService::ROLE_ADMIN]);
            $response = D('CouponRule', 'Logic')->send();
            $this->response($response);
        } catch (\Exception $e) {
            M()->rollback();
            $this->getExceptionError($e);
        }
    }

    public function shortUrl()
    {

//        import('Admin.Common.ShortUrl.ShortUrl');
//        $model = new ShortUrl();
//        echo $model::generateShortLink('http://www.tulisssyosdfsdfuss.com');

        $url = I('get.url');
        empty($url) && $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '生成短链接地址参数url不能为空');
        import('Common.Common.Service.ShortUrl');
        $short = new ShortUrlService();
        $code = $short->encodeShortLink($url);
        $this->response($code);

//        $id = $short->decodeShortLink('mEPyq4');
//        print_r($id);exit;
    }

}