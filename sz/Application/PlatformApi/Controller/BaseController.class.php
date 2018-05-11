<?php
/**
 * File: BaseController.class.php
 * User: xieguoqiu
 * Date: 2017/4/5 15:09
 */

namespace PlatformApi\Controller;

use Admin\Common\ErrorCode;
use Admin\Logic\FactoryLogic;
use Admin\Model\BaseModel;
use Common\Common\CacheModel\AdminCacheModel;
use Common\Common\CacheModel\AdminRoleCacheModel;
use Common\Common\CacheModel\BackendRoutingCacheModel;
use Common\Common\CacheModel\FrontendRoutingCacheModel;
use Common\Common\Service\AuthService;
use Common\Common\Service\PlatformApiService;
use Library\Crypt\AuthCode;

class BaseController extends \Common\Common\Controller\BaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->checkApiSecretParam();
    }

    protected function platFormRequireAuth($allow_role = '')
    {
        $platform_code  = I('platform_code', I('get.platform_code', ''));
        $config = C("PLATFORM_CONFIG.{$platform_code}");
        PlatformApiService::$code = $platform_code;
        PlatformApiService::getPlatformService($config)->privateDecrypt();
//        PlatformApiService::getPlatformService($config)->base64Decode();
        $aurh_type = PlatformApiService::getAuthType();
        if ($allow_role && is_array($allow_role) && in_array($aurh_type, $allow_role)) {
            $this->throwException(-501, '无权限');
//            $this->platformFail(-501, '无权限');
        } elseif ($allow_role && is_string($allow_role) && $allow_role != $aurh_type) {
            $this->throwException(-501, '无权限');
//            $this->platformFail(-501, '无权限');
        }
        AuthService::getAuth($aurh_type)->loadData($config['PLATFORM_ID']);
        return AuthService::getAuthModel()->getPrimaryValue();
    }

    public function platformFail($error_code, $error_msg = '')
    {
        $data = [
            'error_code' => $error_code,
            'error_msg' => $error_msg ?? '',
            'data' => null,
        ];
//        $this->fail($error_code, $error_msg);
        $this->ajaxReturn($data);
    }

    public function getExceptionError(\Exception $e)
    {
        // 报错 $e->getCode() 编号 需要根据不同平台区分
        $config = PlatformApiService::$config;
        $this->platformFail($e->getCode(), $e->getMessage());
    }

    /**
     * 验证接口是否携带安全参数
     * @author zjz
     */
    protected function checkApiSecretParam()
    {
        if (I('get.'.API_SECRET_PARAM) !== API_SECRET_CODE) {
            $this->_empty();
        }
    }

    protected function throwException($error_code, $error_msg = '')
    {
        if (is_array($error_msg)) {
            $msg_arr = $error_msg;
            $error_msg = ErrorCode::getMessage($error_code);
            foreach ($msg_arr as $search => $msg) {
                $error_msg = str_replace(':' . $search, $msg, $error_msg);
            }
        } else {
            empty($error_msg) && $error_msg = ErrorCode::getMessage($error_code);
        }
        throw new \Exception($error_msg, $error_code);
    }

}
