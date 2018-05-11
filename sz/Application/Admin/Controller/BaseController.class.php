<?php
/**
 * File: BaseController.class.php
 * User: xieguoqiu
 * Date: 2017/4/5 15:09
 */

namespace Admin\Controller;

use Admin\Common\ErrorCode;
use Admin\Logic\FactoryLogic;
use Admin\Model\BaseModel;
use Common\Common\CacheModel\AdminCacheModel;
use Common\Common\CacheModel\AdminRoleCacheModel;
use Common\Common\CacheModel\BackendRoutingCacheModel;
use Common\Common\CacheModel\FrontendRoutingCacheModel;
use Common\Common\Service\AuthService;
use Common\Common\Service\XinYingYngService;
use Library\Crypt\AuthCode;

class BaseController extends \Common\Common\Controller\BaseController
{
    Protected $Path_Root = null;

    const FACTORY_TABLE_NAME                = 'factory';

	function __construct()
	{
        parent::__construct();

        $this -> Path_Root = APP_PATH."data/msg_logs/";//自己定义的文件存放位置
	}

    protected function checkAdminPermission()
    {
        $request_routing = strtolower(CONTROLLER_NAME . '/' . ACTION_NAME);

        if (in_array($request_routing, C('POWER_BACKEND_ROUTING'))) {
            return ;
        }

        if (AuthService::getModel() == AuthService::ROLE_ADMIN) {
            $id = AuthService::getAuthModel()->getPrimaryValue();
            $admin_role_ids = AdminCacheModel::getRelation($id, 'rel_admin_roles', 'admin_id', 'admin_roles_id');
            if (!in_array(C('SUPERADMINISTRATOR_ROLES_ID'), $admin_role_ids)) { // 超级管理员不做权限判断
                foreach ($admin_role_ids as $admin_role_id) {
                    $admin_role = AdminRoleCacheModel::getOne($admin_role_id, 'id,is_disable');
                    if ($admin_role['is_disable'] == 1) {
                        continue;
                    }
                    $role_frontend_routing_ids = AdminRoleCacheModel::getRelation($admin_role['id'], 'rel_frontend_routing_admin_roles', 'admin_roles_id', 'frontend_routing_id');
                    foreach ($role_frontend_routing_ids as $role_frontend_routing_id) {
                        $frontend_routing = FrontendRoutingCacheModel::getOne($role_frontend_routing_id, 'id');
                        $frontend_backend_routing_ids = FrontendRoutingCacheModel::getRelation($frontend_routing['id'], 'rel_backend_frontend_routing', 'frontend_routing_id', 'backend_routing_id');
                        foreach ($frontend_backend_routing_ids as $frontend_backend_routing_id) {
                            $backend_routing = BackendRoutingCacheModel::getOne($frontend_backend_routing_id, 'routing');
                            if (strtolower($backend_routing['routing']) == $request_routing) {
                                return ;
                            }
                        }
                    }
                }

                $this->fail(ErrorCode::ADMIN_NO_PERMISSION);
            }
        }
	}

	protected function okNull()
    {
        $this->response(null);
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

    /**
     * 接口必须要登录时使用，
     * 用户已登录则返回用户实例，否则返回错误信息
     *
     * @param $allow_role string|array 允许使用该接口的人员
     * @return mixed
     */
    protected function requireAuth ($allow_role = null)
    {
        $userId = $this->checkAuth();

        if (!$userId) {
            $this->fail(ErrorCode::SYS_USER_VERIFY_FAIL);
        }
        if (is_array($allow_role) && !in_array(AuthService::getModel(), $allow_role)) {
            $this->fail(ErrorCode::SYS_NOT_POWER);
        } elseif (!empty($allow_role) && !is_array($allow_role) && $allow_role != AuthService::getModel()) {
            $this->fail(ErrorCode::SYS_NOT_POWER);
        }

        $this->checkAdminPermission();

        return $userId;
    }

    /**
     * 接口必须要登录时使用，
     * 用户已登录，是厂家账号和子账号：则返回用户实例，否则返回错误信息
     *
     * @return mixed 厂家id
     */
    protected function requireAuthFactoryGetFid ()
    {
        $allow_role = [AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN];

        $userId = $this->requireAuth($allow_role);

        // AuthService::getModel() == AuthService::ROLE_FACTORY_ADMIN && $userId = AuthService::getAuthModel()->getPrimaryValue();
        AuthService::getModel() == AuthService::ROLE_FACTORY_ADMIN && $userId = AuthService::getAuthModel()->factory_id;

        return $userId;
    }

    /**
     * 参照 requireAuthFactoryGetFid 返回对应得厂家信息
     *
     * @return array
     */
    protected function requireAuthFactoryGetFactory ()
    {
        $factory_id = $this->requireAuthFactoryGetFid();

        if (AuthService::getModel() == AuthService::ROLE_FACTORY) {
            $factory = AuthService::getAuthModel();
        } else {
            $factory = (new FactoryLogic())->getFactoryById($factory_id);
        }

        return $factory;
    }

        /**
     * 用户已登录，是厂家账号和子账号：则返回用户实例，否则返回错误信息
     *
     * @return mixed 厂家id
     */
    protected function requireAuthSearchFactoryGetFactory ()
    {
        $allow_role = [AuthService::ROLE_FACTORY, AuthService::ROLE_FACTORY_ADMIN, AuthService::ROLE_ADMIN];

        $userId = $this->requireAuth($allow_role);

        AuthService::getModel() == AuthService::ROLE_FACTORY_ADMIN && $userId = AuthService::getAuthModel()->getPrimaryValue();

        $fid = I('get.id', 0, 'intval');
        switch (AuthService::getModel()) {
            case AuthService::ROLE_FACTORY:
                $factory = AuthService::getAuthModel();
                break;
            
            case AuthService::ROLE_FACTORY_ADMIN:
                $factory = BaseModel::getInstance(self::FACTORY_TABLE_NAME)->getOneOrFail(AuthService::getAuthModel()->factory_id);
                break;

            case AuthService::ROLE_ADMIN:
                $factory = BaseModel::getInstance(self::FACTORY_TABLE_NAME)->getOneOrFail($fid);
                break;
        }

        $factory['factory_id'] != $fid && $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR);

        return $factory;
    }

    // TODO 时间关系初略处理第三方登录
    // ==== ==== ==== ==== ==== ====  新迎燕 start ==== ==== ==== ==== ==== ==== ==== ====
    protected function XYYFail($code, $message, $data = null)
    {
        $this->ajaxReturn([
            'error_code' => (string)$code,
            'error_msg' => (string)$message,
            'data' => $data ?? null,
        ]);
//        die(json_encode([
//            'error_code' => (string)$code,
//            'error_msg' => (string)$message,
//            'data' => $data ?? null,
//        ], JSON_UNESCAPED_UNICODE));
    }

    protected function XYYSuccess($data = null)
    {
        $this->ajaxReturn([
            'error_code' => XinYingYngService::RETURN_CODE_SUCCESS,
            'error_msg' => '',
            'data' => $data ?? null,
        ]);
    }

    protected function platFormRequireAuth($platform)
    {

        switch ($platform) {
            case XinYingYngService::PLATFORM_CODE :
                $this->XYYRequireAuth();
                break;

            default:
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
                break;
        }
    }
    protected function XYYRequireAuth()
    {
//        if (IS_GET) {
//            $des_key = urldecode(I('get.des_key'));
//            $data = urldecode(I('get.data'));
//        } else {
//            $des_key = I('des_key', I('get.des_key'));
//            $data = I('data', I('get.data'));
//        }
        $des_key = I('des_key', I('get.des_key'));
        $data = I('data', I('get.data'));

        if (!$des_key || !$data) {
            $this->XYYFail(XinYingYngService::RETURN_CODE_OTHER_ERROR, '信息不足');
        }
        XinYingYngService::decrypt($data, $des_key);
        XinYingYngService::login();
    }
    protected function XYYGetExceptionError(\Exception $e) {
        $this->XYYFail($e->getCode(), $e->getMessage());
    }
    // ==== ==== ==== ==== ==== ==== 新迎燕 end ==== ==== ==== ==== ==== ==== ==== ====

}
