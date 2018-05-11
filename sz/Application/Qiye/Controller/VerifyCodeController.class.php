<?php
/**
 * File: VerifyCodeController.class.php
 * User: sakura
 * Date: 2017/11/1
 */

namespace Qiye\Controller;

use Qiye\Common\ErrorCode;
use Qiye\Model\BaseModel;
use Common\Common\Service\AuthService;
use Library\Crypt\AuthCode;
use Qiye\Controller\BaseController;

class VerifyCodeController extends BaseController
{

    public function sendCode()
    {
        try {

            $type = I('type', 0, 'intval');

            $verify_logic = D('VerifyCode', 'Logic');
            $verify_logic->setParam('phone', I('phone'));
            $verify_logic->setParam('code_id', I('code_id'));
            $verify_logic->setParam('code', I('code'));

            $data = [];
            if (1 == $type) {
                //注册
                $data = $verify_logic->register();
            } elseif (2 == $type) {
                //忘记密码
                $data = $verify_logic->forget();
            } elseif (3 == $type) {
                //忘记提现密码
                $worker_id = $this->requireAuth(AuthService::ROLE_WORKER);
                $data = $verify_logic->forgetPayPassword($worker_id);
            } else {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '验证码类型错误');
            }

            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /*
     * 技工pc登陆页面验证码
     */
    public function webVerifyCode()
    {
        $code_id = I('code_id', '');
        $config =    array(
            'fontSize'    =>    100,    // 验证码字体大小
            'length'      =>    4,     // 验证码位数
            'useNoise'    =>    true, // 关闭验证码杂点
            'useCurve'    =>    false,
        );
        $Verify = new \Think\Verify($config);
        // $data = $Verify->entry($code_id, true);
        $data = $Verify->entry('', true);
        if (I('code')) {
            $this->response($data);
        }

        $return = [
            'code' => '',
            'code_id' => $data['code_id'],
            'url'  => $data['url'],
        ];
        $this->response($return);
    }

}