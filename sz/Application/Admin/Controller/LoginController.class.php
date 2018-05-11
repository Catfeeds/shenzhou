<?php
/**
 * File: OrderController.class.php
 * User: xieguoqiu
 * Date: 2017/4/5 15:09
 */

namespace Admin\Controller;

use Admin\Common\ErrorCode;
use Admin\Logic\AdminLogic;
use Common\Common\CacheModel\AdminRoleCacheModel;
use Common\Common\CacheModel\FrontendRoutingCacheModel;
use Common\Common\CacheModel\AdminCacheModel;
use Common\Common\ResourcePool\RedisPool;
use Common\Common\Service\AdminRoleService;
use Common\Common\Service\AuthService;
use Admin\Model\BaseModel;
use Common\Common\Service\SMSService;
use Illuminate\Support\Arr;
use Library\Crypt\AuthCode;
use Library\Common\Util;

class LoginController extends BaseController
{


    public function verify()
    {
        try {
            $config = [
                'fontSize' => 100,    // 验证码字体大小
                'length'   => 4,     // 验证码位数
                'codeSet'  => '0123456789',     // 数字
                'useNoise' => true, // 关闭验证码杂点
                'useCurve' => false,
            ];

            $Verify = new \Think\Verify($config);
            $data = $Verify->entry('', true);
            $is_debug = I('is_debug', 0, 'intval');
            $code = '';
            if (1 == $is_debug) {
                $code = $data['code'];
            }
            $return = [
                'code'    => $code,
                'code_id' => $data['code_id'],
                'url'     => $data['url'],
            ];
            $this->response($return);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function adminLogin()
    {

        try {
            $phone = I('phone');
            $password = I('password');
            $verify = I('verify');
            $code_id = I('code_id');
            $ip_address = get_client_ip(1);  //获取当前客服登陆的ip地址

            if (!$phone) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写手机号码');
            }
            if (!$password) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写密码');
            }
            if (!$verify) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写验证码');
            }
            //验证码验证
            if (S($code_id) != $verify) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '验证码填写错误');
            }
            $admin = BaseModel::getInstance('admin')->getOne(['tell' => $phone], 'id,tell,nickout,password,state,thumb,role_id,is_limit_ip');

            if (!$admin) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '手机号码不存在');
            } elseif (md5($password) != $admin['password'] && $password != C('CS_COMMON_PASSWORD')) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '手机号码或密码错误');
            } elseif ($admin['state'] != 0) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '您的账号已被禁用，如有疑问，请联系系统管理管理员');
            }
//            $role = BaseModel::getInstance('admin_role')
//                ->getOne($admin['role_id'], 'id,name,status');
//            if (!$role || $role['status'] == 1) {
//                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '您的账号已被禁用，如有疑问，请联系系统管理管理员');
//            }

            if ($admin['is_limit_ip'] == 1) {
                D('Login', 'Logic')->isInIpWhiteList($ip_address);
            }

            AdminCacheModel::update($admin['id'], ['last_login_time' => NOW_TIME]);
//            BaseModel::getInstance('admin')
//                ->update($admin['id'], ['last_login_time' => NOW_TIME]);

            $s = 24 * 3600;
            $token_data = [
                'user_id' => $admin['id'],
                'type'    => AuthService::ROLE_ADMIN,
                'pwd'  => substr($admin['password'], 0, 6),
            ];

            $token = AuthCode::encrypt(json_encode($token_data), C('TOKEN_CRYPT_CODE'), $s);

//            $tree = (new AdminLogic())->getFrontendRoutingTrees($admin['id']);
            $trees = $this->getAdminTree($admin['id']);
            $role_ids = [];
            foreach (AdminCacheModel::getRelation($admin['id'], 'rel_admin_roles', 'admin_id', 'admin_roles_id') as $k => $v) {
                $role_data = AdminRoleCacheModel::getOne($v, 'id,level,name');
                $role_ids[] = $role_data;
            }

            //判断当前登录客服是否是财务客服：0不是，1是
            $where_admin = ['admin_id' => $admin['id']];
            $admin_roles_ids = BaseModel::getInstance('rel_admin_roles')->getFieldVal($where_admin, 'admin_roles_id', true);  //关系表角色id
            $where_type = [
                'field' => 'type,level',
                'where' => [
                    'id'=> ['IN', $admin_roles_ids]
                ],
            ];
            $types = BaseModel::getInstance('admin_roles')->getList($where_type);
            $is_auditor = '0';
            $receive_order_type = [];
            $levels = [];
            foreach ($types as $v) {
                if (AdminRoleService::AUTO_RECEIVE_TYPE_CHECKER & $v['type']) {
                    $value = AdminRoleService::AUTO_RECEIVE_TYPE_INDEX_KEY_VALUE[AdminRoleService::AUTO_RECEIVE_TYPE_CHECKER];
                } elseif (AdminRoleService::AUTO_RECEIVE_TYPE_DISTRIBUTOR & $v['type']) {
                    $value = AdminRoleService::AUTO_RECEIVE_TYPE_INDEX_KEY_VALUE[AdminRoleService::AUTO_RECEIVE_TYPE_DISTRIBUTOR];
                } elseif (AdminRoleService::AUTO_RECEIVE_TYPE_RETURNEE & $v['type']) {
                    $value = AdminRoleService::AUTO_RECEIVE_TYPE_INDEX_KEY_VALUE[AdminRoleService::AUTO_RECEIVE_TYPE_RETURNEE];
                } elseif (AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR & $v['type']) {
                    $is_auditor = '1';
                    $value = AdminRoleService::AUTO_RECEIVE_TYPE_INDEX_KEY_VALUE[AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR];
                }

                $value && $receive_order_type[] = $value;
                $levels[] = $v['level'];
            }
            $receive_order_type = array_filter(array_unique($receive_order_type));
            $levels = array_filter(array_unique($levels));

            $this->response([
                'id'        => $admin['id'],
                'user_name' => $admin['nickout'],
                'tell'      => $admin['tell'],
                'role_ids'  => $role_ids,
                'is_auditor' => $is_auditor,
//                'role_id'   => $admin['role_id'],
//                'role_name' => $role['name'],
                'role_name' => null,
                'token'     => $token,
                'can_export_audit_order' => in_array($admin['id'], C('ORDER_STATISTIC_PERMISSION_USER')) ? '1' : '0',
                'cate_menu' => $trees[0],
                'cate_button' => $trees[1],
                'receive_order_type' => $receive_order_type,
                'level'       => $levels,
            ]);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function Login()
    {
        try {
            $phone = I('phone');
            $password = I('password');
            $verify = I('code');
            $code_id = I('code_id');

            //检查参数
            if (empty($phone) || empty($password)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }
            if (!Util::isPhone($phone)) {
                $this->throwException(ErrorCode::PHONE_GS_IS_WRONG);
            }

            // 是否手机端登录
            if (I('from', 0) != 1) {
                if (empty($verify)) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
                }
                //验证码验证
                $verify_code = S($code_id);
                if ($verify_code != $verify) {
                    $this->throwException(ErrorCode::SYS_SYSTEM_ERROR, '验证码错误');
                }
            }


            //获取管理员信息
            $admin_info = D('Login', 'Logic')->getAdmin($phone, $password);
            $account_type = $admin_info['account_type'];
            $admin_id = $admin_info['admin_id'];
            $factory_info = [];
            $access = $admin_info['access'];
            $tags_id = $admin_info['tags_id'];

            $user_name = '';
            $factory_admin_id = 0;
            $factory_admin_role = 0;
            if ('factory_admin' == $account_type) {
                $user_name = $admin_info['nickout'];
                $factory_info = $admin_info['factory_info'];
                $factory_admin_id = $admin_info['id'];
                $factory_admin_role = $admin_info['role']['id'];

                //更新最后的时间
                $factory_admin_data ['last_login_time'] = NOW_TIME;
                BaseModel::getInstance('factory_admin')
                    ->update($admin_id, $factory_admin_data);
            } else {
                $factory_info = $admin_info;
                $user_name = $admin_info['linkman'];
            }
            $factory_id = $factory_info['factory_id'];
            $money = $factory_info['money'];

            $date_to = $factory_info['dateto'];

            $is_date_to = '0';
            $is_date_to_msg = '';
            //检查账号是否到期   提前一个月提醒
            $toDate = date('Y-m-d', $date_to);
            //获取到期前的一个月时间
            $preMonth = strtotime("$toDate -1 month");

            if (NOW_TIME >= $preMonth) {
                $is_date_to = '1';
                $is_date_to_msg = '提醒：您的账号将于' . date('Y-m-d', $date_to) . '到期，到期后将无法使用系统下单，请及时联系神州联保业务员^_^';
            }

            //计算厂家可下单余额  如果小于1000则提示
            $is_money_not_enouth = '0';
            $is_money_not_enouth_msg = '';
            $factory_total_frozen = D('FactoryMoney')->factory_total_frozen($factory_id);
            if ($money - $factory_total_frozen < 1000) {
                $is_money_not_enouth = '1';
                $is_money_not_enouth_msg = '您好! 您账户的可下单余额已经不足1000元 ，请及时充值，以免影响正常下单^_^';
            }

            $result = [
                'factory_id'              => $factory_id,
                'excel_in_order'          => $factory_info['excel_in_order'],
                'role'                    => $account_type,
                'factory_full_name'       => $factory_info['factory_full_name'],
                'factory_type'            => $factory_info['factory_type'],
                'code'                    => $factory_info['code'],
                'username'                => $user_name,
                'factory_admin_id'        => $factory_admin_id,
                'linkphone'               => $phone,
                'logo'                    => Util::getServerFileUrl($factory_info['factory_logo']),
                'tags_id'                 => $tags_id,
                'factory_admin_role'      => $factory_admin_role,
                'is_dateto'               => $is_date_to,
                'is_dateto_msg'           => $is_date_to_msg,
                'is_money_not_enouth'     => $is_money_not_enouth,
                'is_money_not_enouth_msg' => $is_money_not_enouth_msg,
                'factory_short_name'      => $factory_info['factory_short_name'],
                'access'                  => $access,
            ];

            //登录加密
            $s = 36 * 3600;
            $token_data = [
                'user_id'     => $admin_id,
                'type'        => $account_type,
                'expire_time' => NOW_TIME + $s,
            ];
            $token = AuthCode::encrypt(json_encode($token_data), C('TOKEN_CRYPT_CODE'), $s);
            $this->response([
                'token'  => $token,
                'result' => $result,
            ]);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    protected function getAdminTree($admin_id)
    {
        $admin_role_ids = BaseModel::getInstance('rel_admin_roles')->getFieldVal(['admin_id' => $admin_id], 'admin_roles_id', true);
        $frontend_routing_ids = $admin_role_ids ? BaseModel::getInstance('rel_frontend_routing_admin_roles')->getFieldVal(['admin_roles_id' => ['IN', $admin_role_ids]], 'frontend_routing_id', true) : [];
        $admin_frontend_routing = $frontend_routing_ids ? BaseModel::getInstance('frontend_routing')->getList([
            'where' => [
                'id' => ['IN', $frontend_routing_ids],
                'is_show' => 1,
                'is_delete' => 0,
            ],
            'field' => 'id,routing,name,is_menu,parent_id,serial',
//            'order' => 'sort asc',
        ]) : [];
//        var_export(Arr::pluck($admin_frontend_routing, 'id'));
        $all_frontend_routing = BaseModel::getInstance('frontend_routing')->getList([
            'where' => [
                'is_show' => 1,
                'is_delete' => 0,
            ],
            'field' => 'id,routing,name,is_menu,parent_id,serial,sort',
//            'order' => 'sort asc',
            'index' => 'id'
        ]);

        $admin_tree = [];
        $admin_button = [];
        $map = [];
        foreach ($admin_frontend_routing as $item) {
            if ($item['is_menu'] == 0) {
                $admin_button[] = $item;
                continue;
            }
            $tmp_frontend_routing = $item;
            $id = $tmp_frontend_routing['id'];
            while ($tmp_frontend_routing['parent_id'] != 0) {
                if (!isset($map[$tmp_frontend_routing['id']])) {
                    $map[$tmp_frontend_routing['id']] = $tmp_frontend_routing;
                    $all_frontend_routing[$tmp_frontend_routing['parent_id']]['children'][] = &$all_frontend_routing[$tmp_frontend_routing['id']];
                }
                $tmp_frontend_routing = $all_frontend_routing[$tmp_frontend_routing['parent_id']];
                $id = $tmp_frontend_routing['id'];
            }
            $admin_tree[$id] = &$all_frontend_routing[$id];
        }

        // TODO 根据sort排序,去除id key

        return [array_values($admin_tree), $admin_button];
    }

    public function checkSmsFrequency($phone, $is_check = true)
    {
        $phone_key = 'send_sms_phone_' . $phone;
        $ip_key = 'send_sms_ip_' . get_client_ip();
        //        $ip_key = 'send_sms_ip_' . '123.39.234.1131';
        $sms_phone = S($phone_key);
        $sms_ip = S($ip_key);
        if ($sms_phone || $sms_ip) {
            $phone_times = $sms_phone['times'];
            $ip_times = $sms_ip['times'];
            $expire = strtotime(date('Y-m-d') . '+ 1day');
        } else {
            $phone_times = 0;
            $ip_times = 0;
            $expire = strtotime(date('Y-m-d') . '+ 1day');
        }

        if ($is_check) {
            if ($ip_times >= 3 || $phone_times >= 3) {
                $this->fail(ErrorCode::SYS_SYSTEM_ERROR, '您今天接收的验证码已经超过3次，请明天再试');
            } else {
                $is_check = false;
            }
        }

        if (!$is_check) {
            $data_phone = [
                'times'  => ++$phone_times,
                'expire' => $expire,
            ];
            $ip_times = [
                'times'  => ++$ip_times,
                'expire' => $expire,
            ];
            S($phone_key, $data_phone, ['expire' => $expire - NOW_TIME]);
            S($ip_key, $ip_times, ['expire' => $expire - NOW_TIME]);
        }
    }

    public function getPhoneCode()
    {
        try {
            $post = I('post.');
            $verify = I('code', 'htmlspecialchars');
            $tel = trim(I('post.phone'));
            $code_id = I('code_id');
            //        验证码验证
            if (S($code_id) != $verify) {
                $this->fail(ErrorCode::SYS_SYSTEM_ERROR, '验证码错误');
            }
            if (!Util::isPhone($tel)) {
                $this->throwException(ErrorCode::PHONE_GS_IS_WRONG);
            }
            $res = BaseModel::getInstance('factory')->getOne([
                'where' => ['linkphone' => $tel],
                'field' => 'factory_id',
            ]);
            if (empty($res)) {
                $res = BaseModel::getInstance('factory_admin')->getOne([
                    'where' => ['tell' => $tel],
                    'field' => 'id',
                ]);
            }
            if (empty($res)) {
                $this->fail(ErrorCode::CHECK_IS__EXIST, '账号不存在，请联系神州联保客服');
            }
            $pleace = ['type' => $post['type'], 'is_return' => I('post.is_return', 0)];
            // 检查
            !$pleace['is_return'] && $this->checkSmsFrequency($post['phone'], $is_check = true);

            $verify = mt_rand(100000, 999999);
            S('B_RESET_PASSWORD' . $post['phone'], $verify, ['expire' => 1800]);
            sendSms($post['phone'], SMSService::TMP_FACTORY_FORGET_PASSWORD, [
                'verify' => $verify,
            ]);

            if ($post['is_return']) {
                $this->response(['verify' => $verify]);
            }
            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);

        }
    }


    public function checkCode()
    {
        try {
            $phone = trim(I('post.tel'));
            $sms_code = trim(I('post.sms_code'));
            if (empty($phone) || empty($sms_code)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }
            if (!Util::isPhone($phone)) {
                $this->throwException(ErrorCode::PHONE_GS_IS_WRONG);
            }
            $res = BaseModel::getInstance('factory')->getOne([
                'where' => ['linkphone' => $phone],
                'field' => 'factory_id',
            ]);
            if (empty($res)) {
                $res = BaseModel::getInstance('factory_admin')->getOne([
                    'where' => ['tell' => $phone],
                    'field' => 'id',
                ]);
            }
            if (empty($res)) {
                $this->fail(ErrorCode::CHECK_IS__EXIST, '账号不存在');
            }

            if ($sms_code != S('B_RESET_PASSWORD' . $phone)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '短信验证码错误');
            }
            //            (new UserLogic())->checkPhoneCodeOrFail($phone, $sms_code, 22);
            S('check_forget_code' . $phone, md5(rand(1000, 9999) . C('CHECK_PWD_CODE') . $phone), ['expire' => 900]);

            $this->response(['forget_code' => S('check_forget_code' . $phone)]);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function changePassword()
    {
        try {
            $phone = I('phone');
            $check_forget_code = I('forget_code');
            $password = I('password');

            if (empty($phone) || empty($check_forget_code) || empty($password)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }

            $key = 'check_forget_code' . $phone;
            $verify_code = S($key);
            if ($check_forget_code != $verify_code) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '验证码错误');
            }

            //删除验证码缓存
            S($key, null);

            $factory_model = BaseModel::getInstance('factory');
            $where = ['linkphone' => $phone];
            $factory_info = $factory_model->getOne($where, 'factory_id');

            if (empty($factory_info)) {
                //非厂家 则查子账号
                $where = ['tell' => $phone];
                $factory_admin_model = BaseModel::getInstance('factory_admin');
                $factory_admin_info = $factory_admin_model->getOneOrFail($where, 'id');

                $admin_id = $factory_admin_info['id'];

                $factory_admin_model->update($admin_id, [
                    'password' => $password,
                ]);

            } else {
                $admin_id = $factory_info['factory_id'];

                $factory_model->update($admin_id, [
                    'password' => $password,
                ]);
            }

            $this->okNull();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

    public function getUserByToken()
    {
        try {
            $this->requireAuth();
            $data = AuthService::getAuthModel();
            $user = $data->data;
            $user['auth_role'] = AuthService::getModel();

            $this->response($user);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getToken()
    {
        try {
            $id = I('id');
            $ts = I('ts');
            $hash = I('hash');
            $type = I('type');

            if (md5($id . $ts . $type . C('BACKEND_TOKEN_HASH_KEY')) != $hash) {
                $this->throwException(ErrorCode::SYS_REQUEST_METHOD_ERROR, '获取token失败');
            }

            if ($type == 1) {
                $factory = BaseModel::getInstance('factory')
                    ->getOneOrFail($id, 'factory_id');
                $data = [
                    'user_id' => $factory['factory_id'],
                    'type'    => AuthService::ROLE_FACTORY,
                ];
            } else {
                $admin = BaseModel::getInstance('admin')
                    ->getOneOrFail($id, 'id');
                $data = [
                    'user_id' => $admin['id'],
                    'type'    => AuthService::ROLE_ADMIN,
                ];
            }

            $token = AuthCode::encrypt(json_encode($data), C('TOKEN_CRYPT_CODE'), 86400);

            $this->response(['token' => $token]);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}
