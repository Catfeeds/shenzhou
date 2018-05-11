<?php
/**
 * File: AuthService.class.php
 * User: xieguoqiu
 * Date: 2016/11/22 15:52
 */

namespace Common\Common\Service;

use Common\Common\Service\AuthService\Auth;
use Common\Common\Service\AuthService\Factory;
use Common\Common\Service\AuthService\FactoryAdmin;
use Common\Common\Service\AuthService\User;
use Common\Common\Service\AuthService\WxUser;
use Common\Common\Service\AuthService\Worker;
use Common\Common\Service\AuthService\Admin;

class AuthService
{

    private static $model;
    private static $type;

    const ROLE_WX_USER = 'wxuser';
    const ROLE_WORKER = 'worker';
    const ROLE_FACTORY = 'factory';
    const ROLE_FACTORY_ADMIN = 'factory_admin';
    const ROLE_ADMIN = 'admin';

    /**
     * @param $model
     * @return Auth
     * @throws \Exception
     */
    public static function getAuth($type)
    {
        static::$type = $type;
        switch (strtolower($type)) {
//            case 'user':
//                if (!isset(static::$model)) {
//                    static::$model = new User();
//                }
//                break;

            case self::ROLE_WX_USER:
                if (!isset(static::$model)) {
                    static::$model = new WxUser();
                }
                break;

            case self::ROLE_WORKER:
                if (!isset(static::$model)) {
                    static::$model = new Worker();
                }
                break;
            case self::ROLE_FACTORY:
                if (!isset(static::$model)) {
                    static::$model = new Factory();
                }
                break;
            case self::ROLE_FACTORY_ADMIN:
                if (!isset(static::$model)) {
                    static::$model = new FactoryAdmin();
                }
                break;
            case self::ROLE_ADMIN:
                if (!isset(static::$model)) {
                    static::$model = new Admin();
                }
                break;
            default:
                throw new \Exception('Model not exists');
        }
        return static::$model;
    }

    /**
     * 获取最后使用的认证类型
     * @return Auth
     */
    public static function getAuthModel()
    {
        return static::$model;
    }

    public static function getModel()
    {
        return static::$type;
    }
        
}
