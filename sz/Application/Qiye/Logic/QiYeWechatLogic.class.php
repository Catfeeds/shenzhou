<?php
/**
 * File: QiYeWechatLogic.class.php
 * User: xieguoqiu
 * Date: 2017/1/10 15:33
 */

namespace Qiye\Logic;

use Common\Common\ResourcePool\RedisPool;
use GuzzleHttp\Client;
use Library\Common\Util;
use Psr\Http\Message\ResponseInterface;
use Qiye\Common\ErrorCode;
use Qiye\Model\BaseModel;
use Qiye\Repositories\Events\SubscribeEvent;
use Stoneworld\Wechat\AccessToken;
use Stoneworld\Wechat\Broadcast;
use Stoneworld\Wechat\Group;
use Stoneworld\Wechat\Media;
use Stoneworld\Wechat\Message;
use Stoneworld\Wechat\Server;
use Stoneworld\Wechat\User;
use Think\Log;
use Stoneworld\Wechat\Cache;
use Stoneworld\Wechat\Http;
use Api\Repositories\Events\UploadFileEvent;

class QiYeWechatLogic extends BaseLogic
{

    protected $http;

    const ACCESS_TOKEN_URL = 'https://qyapi.weixin.qq.com/cgi-bin/gettoken';
    const LOGIN_URL = 'https://open.weixin.qq.com/connect/oauth2/authorize';
    const GET_USER_INFO_URL = 'https://qyapi.weixin.qq.com/cgi-bin/user/getuserinfo';
    const CONVERT_TO_OPENID = 'https://qyapi.weixin.qq.com/cgi-bin/user/convert_to_openid';

    const CREATE_DEPARTMENT = 'https://qyapi.weixin.qq.com/cgi-bin/department/create';
    const UPDATE_DEPARTMENT = 'https://qyapi.weixin.qq.com/cgi-bin/department/update';
    const DELETE_DEPARTMENT = 'https://qyapi.weixin.qq.com/cgi-bin/department/delete';
    const LIST_DEPARTMENT = 'https://qyapi.weixin.qq.com/cgi-bin/department/list';

    const UPLOAD_MEDIA = 'https://qyapi.weixin.qq.com/cgi-bin/media/upload';

    const SYNC_USER = 'https://qyapi.weixin.qq.com/cgi-bin/batch/syncuser';
    const GET_SYNC_RESULT = 'https://qyapi.weixin.qq.com/cgi-bin/batch/getresult';

    const CREATE_MENU = 'https://qyapi.weixin.qq.com/cgi-bin/menu/create';

    const ADD_USER = 'https://qyapi.weixin.qq.com/cgi-bin/user/create';
    const UPDATE_USER = 'https://qyapi.weixin.qq.com/cgi-bin/user/update';
    const GET_USER = 'https://qyapi.weixin.qq.com/cgi-bin/user/get';

    const SEND_MESSAGE = 'https://qyapi.weixin.qq.com/cgi-bin/message/send';

    const JSSDK_URL = 'https://qyapi.weixin.qq.com/cgi-bin/get_jsapi_ticket';
    protected $jsapiTicketCacheKey = 'Stoneworld.wechat.jsapi_ticket';

    const GET_MEDIA = 'https://qyapi.weixin.qq.com/cgi-bin/media/get';

    public function __construct()
    {
        $this->http = new Client();
    }

    public function getMedias($ids = '')
    {
        $id_arr = array_filter(explode(',', $ids));
        $return = $post_server = [];
        if ($id_arr) {
            $http = new Http();
            $url = './Uploads/'.date('Ymd', NOW_TIME).'/';
            if (!is_dir($url)){  
                //第三个参数是“true”表示能创建多级目录，iconv防止中文目录乱码
                mkdir($url, 0777, true); 
            }
            $result = [];
            foreach ($id_arr as $v) {
                $params = [
                    'access_token' => $this->getAccessToken(),
                    'media_id' => $v,
                ];
                $str = $http->get(self::GET_MEDIA, $params);
                if (isset($str['errcode'])) {
                    $this->throwException(ErrorCode::SYS_DATA_NOT_EXISTS);
                }
                $result[] = [
                    'name' => md5(date('Ymd', NOW_TIME).$v).'.jpg',
                    'string' => $str,
                ];
            }
            foreach ($result as $k => $v) {
                $save = $url.$v['name'];
                file_put_contents($save, $v['string']);
                $post_server [] = [
                    'url' => '.'.trim($save, '.'),
                ];
                $return[] = [
                    'url' => trim($save, '.'),
                    'url_full' => Util::getServerFileUrl(trim($save, '.')),
                    'name' => $v['name'],
                ];
            }
        }
        //event(new UploadFileEvent($post_server));
        return $return;
    }

    // jssdk
    public function getJsSdk($list = [], $url = '', $config=[])
    {
        $signature_arr = $this->getSignature($url, $config);

        $appid = empty($config['appid'])? C('qiyewechat.appid'): $config['appid'];

        $cond = [
            'debug' => false,
            'beta'  => true,
            'appId' => $appid,
            'timestamp' => $signature_arr['timestamp'],
            'nonceStr' => $signature_arr['noncestr'],
            'signature' => $signature_arr['signature'],
            'jsApiList' => $list,
        ];
        return $cond;
    }

    // 签名
    public function getSignature($url = '', $config = [])
    {
        $generate_code = str_pad(mt_rand(0, pow(10, 10) - 1), 10, '0', STR_PAD_LEFT);
        $noncestr = md5($generate_code);
        $sign_data = [
            'jsapi_ticket' => $this->getJsapiTicket($config),
            'noncestr' => $noncestr,
            'timestamp' => NOW_TIME,
            'url' => $url ? $url : Util::getServerUrl(),
        ];
        $arr = [];
        foreach ($sign_data as $k => $v) {
            $arr[] = $k.'='.$v;
        }
        $sign_str = implode('&', $arr);
        $sign_data['signature'] = sha1($sign_str);
        return $sign_data;
    }

    // 签名凭证
    public function getJsapiTicket($config = [])
    {
        $http = new Http();

        $appid = empty($config['appid'])? C('qiyewechat.appid'): $config['appid'];
        $secret = empty($config['secret'])? C('qiyewechat.secrect'): $config['secret'];

        $cache = new Cache($appid);
        $jsapi_key = $cache->get($this->jsapiTicketCacheKey);

        if (!$jsapi_key) {
            $params = [
                'access_token' => $this->getAccessToken($secret, $appid),
            ];
            $jsapi_ticket_data = $http->get(self::JSSDK_URL, $params);
            $cache->set($this->jsapiTicketCacheKey, $jsapi_ticket_data['ticket'], $jsapi_ticket_data['expires_in'] - 800);
            $jsapi_key = $jsapi_ticket_data['ticket'];
        }
        return $jsapi_key;
    }

    public function callback()
    {

        $options = C('qiyewechat');
        $options['agentid'] = C('SEND_NEWS_MESSAGE_APPLICATION_ID');

        $server  = new Server($options);

        $server->on('message',function($message) {

        });

        $server->on('event', function($message) use ($options) {
            \Think\Log::record($message);
            if ($message->Event == 'click') {
                if ($message->EventKey == 'lianxi_kf') {
                    $text = '尊敬的师傅，有关工单以及注册等相关信息，您可微信联系我们的在线客服或者拨打我们的免费服务热线020-81316747  020-81316748';
                    $this->sendText2User($message->FromUserName, $text, C('ABOUT_SHENZHOU_APPLICATION_ID'));
                }
            } elseif ($message->Event == 'subscribe') {
                $key = 'qy_subscript' . $message->FromUserName;
                if (!S($key)) {
                    S($key, 'subscript', ['expire' => 10]);
                    if (strtolower($message->Event) == 'subscribe') {
                        event(new SubscribeEvent($message->FromUserName));
                    }
                }
            }

        });


        echo $server->server();
//        file_put_contents('aa.txt', 'aaaaaaa', FILE_APPEND);
//        $msg_signature = I('msg_signature');
//        $timestamp = I('timestamp');
//        $nonce = I('nonce');
//        $echostr = I('echostr');
//
//        Log::record(json_encode(file_get_contents('php://input')), Log::DEBUG);
//        Log::record(json_encode($_POST), Log::DEBUG);
//        Log::record(json_encode($_REQUEST), Log::DEBUG);
////        exit;
//
//        $wxcpt = new \WXBizMsgCrypt(C('CALLBACK_TOKEN'), C('ENCODING_AES_KEY'), C('qiyewechat.appid'));
//        if ($echostr) {
//            $msg = '';
//
//            include MODULE_PATH . 'Common/CallbackCrypt/WXBizMsgCrypt.php';
//
//
//            $errCode = $wxcpt->VerifyURL($msg_signature, $timestamp, $nonce, $echostr, $msg);
//
//            if ($errCode == 0) {
//                echo $msg;
//            }
//        } else {
//            $wxcpt->DecryptMsg($msg_signature, $timestamp, $nonce, file_get_contents('php://input'), $msg);
//            Log::record($msg, Log::DEBUG);
//            event(new SubscriptEvent('jjz'));
//        }
    }

    public function login($redirect_path)
    {
        $config = C('qiyewechat');

        $config['secrect'] = 'DedbdlTWqJO_OB-fsAAXgIsz2Y_j17cnupIGUbljvSQ';

        $code = I('code');

        if (!$code) {
            // 回调地址
            $redirect_path = $redirect_path ? :  $_SERVER['SCRIPT_NAME'] . '/wechat/login';
            $callback_url = I('url', '/app/user-main', 'urldecode');
            $login_url = Util::getServerUrl() . $redirect_path . '?url=' . $callback_url;
            $config['redirect_uri'] = $login_url;
            $config['response_type'] = 'code';
            $config['scope'] = 'snsapi_base';

            $url = $this->buildUrl(static::LOGIN_URL, $config) . '#wechat_redirect';

            header('Location:' . $url);
            exit;
        } else {
            $info = $this->getIdByCode($code);

//            if (!$info['UserId']) {
//                $this->throwException(ErrorCode::SYS_SYSTEM_ERROR, '获取用户ID失败');
//            }
//
//            $user = $this->getUser($info['UserId']);

            return $info;
        }
    }

    public function exportAllUser()
    {
        set_time_limit(0);

        $worker = BaseModel::getInstance('worker');
        $num = $worker->getNum([
            'is_qianzai' => 1,
        ]);



        for ($selected = 0, $batch_num = 20000, $last_id = 0; $selected < $num; $selected += $batch_num) {
            $workers = $worker->getList(
                [
                    'field' => 'worker_id,worker_telephone,nickname',
                    'where' => [
                        'worker_id' => ['GT', $last_id],
//                        'is_complete_info' => 1,    // 通过审核
//                        'is_check' => 1,            // 通过了验证的
                        'is_qianzai' => 1,          // 正式用户
                    ],
                    'order' => 'worker_id ASC',
                    'limit' => $batch_num,
                ]
            );
            $this->exportUsers($workers);
            $worker_info = $workers[count($workers) - 1];
            $last_id = $worker_info['worker_id'];
        }
    }

    public function syncAllUser()
    {
        set_time_limit(0);

        $worker_phone_map = BaseModel::getInstance('worker')
            ->getFieldVal(
                ['is_qianzai' => 1],
                'worker_telephone,nickname,worker_id,is_check',
                true
            );


        $should_disabled_list = [];
        foreach ($worker_phone_map as $phone => $item) {
            if ($item['is_check'] == 0) {
                $should_disabled_list[$phone] = $item['worker_id'];
            }
        }

        $should_enabled_list = [];
        $options = C('qiyewechat');
        $user = new User($options['appid'], $options['secrect']);
        $in_qiye_notin_official = [];
        // 0获取全部成员，1获取已关注成员列表，2获取禁用成员列表，4获取未关注成员列表。status可叠加
        $users = $user->lists(C('WORKER_DEPARTMENT_ROOT_ID'), 1, 0);
        foreach ($users['userlist'] as $item) {
            // 筛选掉已经禁用的用户
            if (isset($should_disabled_list[$item['userid']]) && $item['status'] == 2) {
                unset($should_disabled_list[$item['userid']]);
            }
            if ($worker_phone_map[$item['userid']]['is_check'] == 1 && $item['status'] == 2) {
                $should_enabled_list[] = $item;
            }

            if (isset($worker_phone_map[$item['userid']])) {
                unset($worker_phone_map[$item['userid']]);
            } else {
                $in_qiye_notin_official[] = $item['userid'];
            }
        }

        foreach ($worker_phone_map as $phone => $item) {
            $telephone = $this->getWorkerPhone($phone);
            $user->create([
                'userid' => $telephone,
                'name' => trim($item['nickname']),
                'department' => C('WORKER_CHECKED_DEPARTMENT_ID'),
                'mobile' => $telephone
            ]);
        }

        foreach ($should_disabled_list as $phone => $worker_id) {
            $telephone = $this->getWorkerPhone($phone);
            $user->update(
                [
                    'userid' => $telephone,
                    'enable' => 0
                ]
            );
        }

        foreach ($should_enabled_list as $item) {
            $user->update(
                [
                    'userid' => $item['userid'],
                    'enable' => 1
                ]
            );
        }

        return [
            'should_disabled_list' => $should_disabled_list,
            'should_enabled_list' => $should_enabled_list,
            'in_qiye_notin_official_list' => $in_qiye_notin_official,
            'in_official_notin_qiye' => $worker_phone_map,
        ];
    }

    public function getExportResult()
    {
        set_time_limit(0);

        $model_qy_jobs = BaseModel::getInstance('qy_jobs');
        $job_ids = $model_qy_jobs
            ->getFieldVal([
                'status' => 0,
            ], 'job_id', true);

        if (!$job_ids) {
            return ;
        }

        $conf = [
            'access_token' => $this->getAccessToken(),
        ];

        $success_users = [];
        $model_qy_jobs->startTrans();
        foreach ($job_ids as $job_id) {
            $conf['jobid'] = $job_id;
            $url = $this->buildUrl(static::GET_SYNC_RESULT, $conf);
            $response = $this->http->get($url);
            $result = $this->getResponse($response);

            // 1表示任务开始，2表示任务进行中，3表示任务已完成
            if ($result['status'] != 3) {
                continue;
            }

            $error_result = [];
            foreach ($result['result'] as $item) {
                if ($item['errcode'] == 0) {
                    $success_users[] = $item['userid'];
                    continue;
                }
                $error_result[] = [
                    'job_id' => $job_id,
                    'userid' => $item['userid'],
                    'type' => 1,        // 1.异步用户导入；2.更新用户
                    'errmsg' => $item['errcode'] . '__' . $item['errmsg'],
                    'create_at' => NOW_TIME,
                ];
            }

            $status = 1;
            if (count($error_result) > 0) {
                BaseModel::getInstance('qy_job_fail_list')
                    ->insertAll($error_result);

                $status = 2;
            }
            $model_qy_jobs->update([
                'job_id' => $job_id
            ],[
                'status' => $status,
                'update_at' => NOW_TIME,
            ]);
            dump($result);

//            if ($success_users) {
//                $disabled_worker_phones = BaseModel::getInstance('worker')->getFieldVal([
//                    'worker_telephone' => ['IN', $success_users],
//                    'is_check' => 0,            // 停用的用户
//                ], 'worker_telephone', true);
//                foreach ($disabled_worker_phones as $disabled_worker_phone) {
//                    $result = $this->updateUser($disabled_worker_phone, ['enable' => 0]);
//                    if (isset($result['errcode']) && $result['errcode'] != 0) {
//                        BaseModel::getInstance('qy_job_fail_list')
//                            ->insert([
//                                'job_id' => $job_id,
//                                'type' => 2,            // 1.异步用户导入；2.更新用户
//                                'userid' => $disabled_worker_phone,
//                                'errmsg' => $result['errcode'] . '__' . $result['errmsg']
//                            ]);
//                    }
//                }
//                dump($success_users);
//            }
        }
        $model_qy_jobs->commit();
    }

    public function createMenu($application_id, $menu)
    {
        $conf = [
            'access_token' => $this->getAccessToken(),
            'agentid' => $application_id,
        ];

        $response = $this->http->post(static::CREATE_MENU, ['query' => $conf, 'body' => $this->getEncodeBody($menu)]);
        $result = $this->getResponse($response);

        dump($result);
    }

    public function sendNews2User($users, $message, $application_id = null)
    {
        $options = C('qiyewechat');
        $application = C('application_secret');
        $broadcast = new Broadcast($options['appid'], $application[$application_id]);

        $application_id = $application_id ?  : C('SEND_NEWS_MESSAGE_APPLICATION_ID');
        $broadcast->fromAgentId($application_id)->send($message)->to($users);
    }

    public function sendText2User($user, $text, $application_id = null)
    {
        $options = C('qiyewechat');
        $application = C('application_secret');
        $broadcast = new Broadcast($options['appid'], $application[$application_id]);
        $message = Message::make('text')->content($text);

        $application_id = $application_id ? : C('SEND_NEWS_MESSAGE_APPLICATION_ID');

        $broadcast->fromAgentId($application_id)->send($message)->to($user);

    }

    public function addUserById($user_id)
    {
        $worker = BaseModel::getInstance('worker')->getOneOrFail($user_id);

        $phone = $this->getWorkerPhone($worker['worker_telephone']);
        $data = [
            'userid' => $phone,
            'name' => $worker['nickname'],
            'department' => C('WORKER_CHECKED_DEPARTMENT_ID'),
            'mobile' => $phone,
        ];

        $options = C('qiyewechat');
        $user = new User($options['appid'], $options['secrect']);

        $result = $user->create($data);


        if ($result['errcode'] == 0) {
            // 如果技工状态为停用，则修改企业号状态为禁用
            if ($worker['is_check'] == 0) {
                $this->updateUser($phone, [
                    'enable' => 0
                ]);
            }
        }
    }

    public function moveUser2CheckedGroup($user_id, $qy_worker = null)
    {
        $qy_worker = $qy_worker ? : $this->getUser($user_id);
        $department = $qy_worker['department'];

        // 删除未审核部门ID，添加已审核部门ID
        $index = array_search(C('WORKER_UNCHECKED_DEPARTMENT_ID'), $department);
        if ($index !== false) {
            array_splice($department, $index, 1);
        }
        $department[] = C('WORKER_CHECKED_DEPARTMENT_ID');

        $res = D('QiYeWechat', 'Logic')->updateUser($qy_worker['userid'], [
            'department' => $department
        ]);

        return $res;
    }

    public function getUser($user_id)
    {
        $options = C('qiyewechat');
        $user = new User($options['appid'], $options['secrect']);
        return $user->get($user_id);
    }

    public function updateUser($phone, $data)
    {
        $data['userid'] = $phone;

        $options = C('qiyewechat');
        $user = new User($options['appid'], $options['secrect']);
        $result = $user->update($data);

        return $result;
    }


    protected function exportUsers($users)
    {
        if (!$users) {
            return ;
        }

        $sample_file = C('WORKER_EXPORT_SAMPLE_FILE');
        $sample_file = realpath($sample_file);
        $pathinfo = pathinfo($sample_file);
        $target_file = $pathinfo['dirname'] . DIRECTORY_SEPARATOR . date('YmdHis') . 'all.' . $pathinfo['extension'];


        $title = [
            $this->utf82gbk('姓名'),
            $this->utf82gbk('帐号'),
            $this->utf82gbk('微信号'),
            $this->utf82gbk('手机号'),
            $this->utf82gbk('邮箱'),
            $this->utf82gbk('所在部门'),
            $this->utf82gbk('职位')
        ];

        $department_id = C('WORKER_CHECKED_DEPARTMENT_ID');

        $excel_str = implode(",",$title) . "\r\n";

        foreach ($users as $user) {
            $user['worker_telephone'] = $this->getWorkerPhone($user['worker_telephone']);
            $info = [
                $this->utf82gbk($user['nickname']),    // 姓名
                $user['worker_telephone'],      // 帐号
                '',
                $user['worker_telephone'],      // 手机号
                '',                             // 邮箱
                $department_id,         // 所在部门
            ];
            $excel_str .= implode(',', $info) . "\r\n";
//            dump($excel_str);
        }

        trim($excel_str, "\r\n");
        file_put_contents($target_file, $excel_str);

//        $options = C('qiyewechat');
//        $media = new Media($options['appid'], $options['secrect']);
//        $upload_result = $media->file($target_file);
        $media = $this->uploadFile($target_file);

        $query = [
            'access_token' => $this->getAccessToken(),
        ];

        $body = [
            'media_id' => $media['media_id']
        ];

        $response = $this->http->post(static::SYNC_USER, ['query' => $query, 'body' => $this->getEncodeBody($body)]);
        $result = $this->getResponse($response);

        if ($result['errcode'] != 0) {
            $this->throwException(ErrorCode::SYS_SYSTEM_ERROR, '更新成员失败:' . $result['errmsg']);
        }

        BaseModel::getInstance('qy_jobs')
            ->insert([
                'job_id' => $result['jobid'],
                'create_at' => NOW_TIME,
            ]);
    }

    public function uploadFile($file)
    {
        $query = [
            'access_token' => $this->getAccessToken(),
            'type' => 'file',
        ];
        $multipart[] = [
            'name' => 'media',
            'contents' => fopen($file, 'r'),
        ];

        $response = $this->http->post(static::UPLOAD_MEDIA, ['query' => $query, 'multipart' => $multipart]);
        $result = $this->getResponse($response);

        return $result;
    }

    public function createDepartment($name, $parent_id)
    {
        if (!$name) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请填写部门名称');
        }

        $options = C('qiyewechat');
        $group = new Group($options['appid'], $options['secrect']);
        $result = $group->create($name, $parent_id);

        return $result;
    }

    public function getIdByCode($code)
    {
        $conf = [
            'access_token' => $this->getAccessToken(C('login_secrect')),
            'code' => $code
        ];
        $url = $this->buildUrl(static::GET_USER_INFO_URL, $conf);

        $result = $this->getResponse($this->http->get($url));

        if ($result['OpenId']) {
            header('Location:' . C('qiyewechat_host') . C('qy_base_path') .'/app?open_id='.$result['OpenId']);
            exit();
        } elseif (!$result['UserId']) {
            $this->throwException(ErrorCode::SYS_SYSTEM_ERROR, '获取用户ID失败');
        }

        return $result;
    }

    protected function getAccessToken($secret = '', $appid = '')
    {
        $config = C('qiyewechat');

        $appid = !empty($appid)? $appid: $config['appid'];
        $secret = !empty($secret) ? $secret : $config['secrect'];

        $tk = new AccessToken($appid, $secret);

        return $tk->getToken();

//        $key = $conf['corpid'] . '_qiye_access_token';
//        $access_token = S($key);
//
//        if (!$access_token) {
//            $response = $this->http->get(static::ACCESS_TOKEN_URL, ['query' => $conf]);
//            $access_token = (String)$response->getBody();
//            $access_token = \GuzzleHttp\json_decode($access_token, true);
//            if (isset($access_token['errcode']) ||  $access_token['errcode'] != 0) {
//                $this->throwException(ErrorCode::SYS_SYSTEM_ERROR, $access_token['errmsg']);
//            }
//            S($key, $access_token['access_token'], ['expire' => 3600]);
//            $access_token = $access_token['access_token'];
//        }
//
//        return $access_token;
    }

    public function convert2Openid($user_id, $token = '')
    {
        $token = empty($token)? $this->getAccessToken(): $token;

        $query = [
            'access_token' => $token
        ];

        $body = $this->getEncodeBody([
            'userid' => $user_id
        ]);

        $result = $this->getResponse($this->http->post(static::CONVERT_TO_OPENID, ['query' => $query, 'body' => $body]));

        if (!array_key_exists('errcode', $result)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '微信返回参数异常');
        }

        if (0 != $result['errcode']) {
            $this->throwException(ErrorCode::SYS_SYSTEM_ERROR, '转换失败:' . $result['errmsg']);
        }

        return empty($result['openid'])? '': $result['openid'];
    }

    private function buildUrl($url, $params)
    {
        return $url . '?' . http_build_query($params);
    }

    protected function getResponse(ResponseInterface $response)
    {
        $data = (String)$response->getBody();

        return \GuzzleHttp\json_decode($data, true);
    }

    protected function getEncodeBody($body)
    {
        return \GuzzleHttp\json_encode($body, JSON_UNESCAPED_UNICODE);
    }

    protected function utf82gbk ($str) {
        return iconv('UTF-8', 'gbk', $str);
    }

    protected function getWorkerPhone($phone)
    {
        return substr($phone, 0, 11);
    }

}
