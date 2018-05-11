<?php
/**
 * File: function.php
 * User: xieguoqiu
 * Date: 2016/8/10 17:56
 */
use Common\Common\Service\OrderService;

// 2017-4-7 zjz 是否是保内
function isInWarrantPeriod($order_type = 0)
{
    return in_array($order_type, OrderService::ORDER_TYPE_IN_INSURANCE_LIST) ? true : false;
}

function mod($id, $mo = 0)
{
    return intval(fmod(floatval($id), $mo));
}

function factoryIdToModelName($id = 0, $model_name = 'yima_', $mo = 16)
{
    if (!$id) {
        throw new \Exception("请求参数错误", -2);
    }
    return $model_name.intval(mod(intval($id), $mo));
}

function getFidByCode($code = '')
{
    $factory_code = substr($code,1,3);
    if (strlen($factory_code) != 3) {
        // throw new \Exception("请求参数错误", -2);
        return '';
    }
    return \QiuQiuX\BaseConvert\BaseConvert::convert($factory_code, '36', 10);
}

function yimaCodeToModelName($code = 0, $model_name = 'yima_', $mo = 16)
{
    $factory_id = getFidByCode($code);
    return factoryIdToModelName($factory_id, $model_name, $mo);
}

/**
 * @param \Common\Common\Repositories\Events\EventAbstract $eventAbstract
 */
function event(\Common\Common\Repositories\Events\EventAbstract $eventAbstract)
{
    static $event = null;

    if (!$event) {
        $event = new \Common\Common\Repositories\Events\Event();
    }

    $event->fire($eventAbstract);
}

function dd()
{
    array_map(function ($x) {
        (new \Illuminate\Support\Debug\Dumper())->dump($x);
    }, func_get_args());

    die(1);
}


if (!function_exists('getallheaders'))
{
    function getallheaders()
    {
        $headers = [];
        foreach ($_SERVER as $name => $value)
        {
            if (substr($name, 0, 5) == 'HTTP_')
            {
                $headers[str_replace(' ', '-', strtolower(str_replace('_', ' ', substr($name, 5))))] = $value;
            }
        }
        return $headers;
    }
}

// 取数组中的某个key的值，并implode成字符串
function arrFieldForStr($arr = [], $field = '', $implode = ',', $sande = false)
{
    $return_arr = [];
    foreach ($arr as $key => $value) {
        $k = $value[$field];
        $return_arr[$k] = $k;
    }
    return  $sande?
            ($return = implode($implode, array_filter($return_arr))) ? $implode.$return.$implode : '' :
            implode($implode, array_filter($return_arr));
}

// htmlEntityDecode
function htmlEntityDecode($str)
{
    if (is_string($str)) {
        $str = html_entity_decode(html_entity_decode($str));
    }
    return $str;
}

//  htmlEntityDecodeAndJsonDecode
function htmlEntityDecodeAndJsonDecode($str)
{
    if (is_string($str)) {
        $str = json_decode(html_entity_decode(html_entity_decode($str)), true);
    }
    return $str;
}

function arrToJsonAndUrlEncode($arr = [])
{
    if (!is_array($arr)) {
        $arr = [];
    }
    return urlencode(json_encode($arr, JSON_UNESCAPED_UNICODE));
}

function getAreaIdNameMap($area_ids)
{
    $area_ids = array_unique($area_ids);
    $area_map = \Common\Common\Model\BaseModel::getInstance('cm_list_item')
        ->getFieldVal(['list_item_id' => ['IN', $area_ids]], 'list_item_id,item_desc');

    return $area_map;
}


function encryptYimaCode($code)
{
    $hash = new \Library\Crypt\Hashids(C('YIMA_CRYPT_KEY'), C('YIMA_CRYPT_MIN_LENGTH'));

    $factory_type = substr($code, 0, 1);
    $factory_code = substr($code, 1, 3);
    $factory_id = \QiuQiuX\BaseConvert\BaseConvert::convert($factory_code, '36', 10);
    $factory_type_int = ord($factory_type) - ord('A');

    $c = $factory_type_int . $factory_id . substr($code, -8);

    return $hash->encode($c);
}

function decryptYima($encrypt)
{
    $hash = new \Library\Crypt\Hashids(C('YIMA_CRYPT_KEY'), C('YIMA_CRYPT_MIN_LENGTH'));

    $decode = $hash->decode($encrypt)[0];
    if (substr($decode, 0, 2) > 25) {
        $decode = '0' .$decode;
    }
    $fc = str_pad(strtoupper(\QiuQiuX\BaseConvert\BaseConvert::convert(substr($decode, 2, strlen($decode) - 10), '10', '36')), 3, '0', STR_PAD_LEFT);
    return chr(ord('A') + substr($decode, 0, 2)) . $fc . substr($decode, -8);
}

//计算质保期
function get_limit_date($active_time_stamp, $m = 0)
{
    $active_date = date('Y-m-d', $active_time_stamp);
    $timestr = $active_date.' + '.$m.' month';
    return strtotime($timestr);
}

/**
 * 返回分页设置
 * @param null $page_no
 * @param null $page_num
 * @return string
 */
function getPage($page_no = NULL, $page_num = NULL)
{
    empty($page_no) && $page_no = I('page_no', 1, 'intval');
    empty($page_num) && $page_num = I('page_size', 10, 'intval');
    $offset = ($page_no - 1) * $page_num;
    $offset = max(0, $offset);
    $page_num = max(0, $page_num);
    return "$offset,$page_num";
}

function sendSms($phone, $template_sn, $template_params)
{
    $client_ip = get_client_ip();
    queue('sms', new \Common\Common\Job\SendSmsJob($phone, $template_sn, $template_params, $client_ip));
}

function expressTrack($express_code, $express_number, $data_id, $type)
{
    queue('expressTrack', new \Common\Common\Job\ExpressTrackJob($express_code, $express_number, $data_id, $type));
}

function checkerReceiveOrder($worker_order_id)
{
    $queue_name = C('AUTO_RECEIVE_QUEUE');
    $key = C('AUTO_RECEIVE_QUEUE');
    queue($key, new \Common\Common\Job\CheckReceiveOrderJob($worker_order_id), $queue_name);
    //(new \Common\Common\Job\CheckReceiveOrderJob($worker_order_id))->handle();
}

function distributorReceiveOrder($worker_order_id)
{
    $queue_name = C('AUTO_RECEIVE_QUEUE');
    $key = C('AUTO_RECEIVE_QUEUE');
    queue($key, new \Common\Common\Job\DistributorReceiveOrderJob($worker_order_id), $queue_name);
    //(new \Common\Common\Job\DistributorReceiveOrderJob($worker_order_id))->handle();
}

function returneeReceiveOrder($worker_order_id)
{
    $queue_name = C('AUTO_RECEIVE_QUEUE');
    $key = C('AUTO_RECEIVE_QUEUE');
    queue($key, new \Common\Common\Job\ReturnReceiveOrderJob($worker_order_id), $queue_name);
    //(new \Common\Common\Job\ReturnReceiveOrderJob($worker_order_id))->handle();
}

function auditorReceiveOrder($worker_order_id)
{
    $queue_name = C('AUTO_RECEIVE_QUEUE');
    $key = C('AUTO_RECEIVE_QUEUE');
    queue($key, new \Common\Common\Job\AuditorReceiveOrderJob($worker_order_id), $queue_name);
    //(new \Common\Common\Job\AuditorReceiveOrderJob($worker_order_id))->handle();
}


/*
* 极光推送
* $registration_id,极光id
* $type,对应AppMessageService TYPE_* 值
* $id, AppMessageService create方法返回的消息id
*/
function workerNotificationJPush($registration_id, $type, $id, $title, $content, $data_id, $send_type=array('ios', 'android'), $is_radio='')
{
    if (!empty($registration_id)) {
        appPushNotification($registration_id, $title, $content, [
            'type' => $type,
            'id' => $id,
        ], $send_type, $is_radio, $data_id);
    }
}

function appPushNotification($registration_id, $title, $content, $extras = [], $send_type, $is_radio, $data_id)
{
    queue('app-notification', new \Common\Common\Job\SendAppNotificationJob($registration_id, $title, $content, $extras, $send_type, $is_radio, $data_id));
}

function sendQyWechatNotification($user, $text, $application_id = null)
{
    queue('qywechat-message', new \Common\Common\Job\SendQyWechatNotification($user, $text, $application_id));
}

function sendWechatNotification($open_id, $message, $type)
{
    queue('wechat-message', new \Common\Common\Job\SendWechatNotification($open_id, $message, $type));
}

function sendXinYingYanNotification()
{
    queue('xinyingyan-message', new \Common\Common\Job\SendXinYingYanNotificationJob());
}

function queue($key, \Library\Queue\Queue $queue, $queue_name='')
{
    $queue_type = C('QUEUE.DRIVER');
    $class = getQueueDriver($queue_type);
    (new $class)->push($key, $queue, $queue_name);

}

function queueBatch($key, $queues, $queue_name='')
{
    $queue_type = C('QUEUE.DRIVER');
    $class = getQueueDriver($queue_type);
    (new $class)->pushBatch($key, $queues, $queue_name);

}

function getQueueDriver($queue_type)
{
    switch ($queue_type) {
        case 'redis':
            return \Library\Queue\Driver\Redis::class;
        default:
            throw new \Exception('暂不支持该队列类型');
    }
}

function encodeWorkerCode($worker_id)
{
    $hashids = new \Library\Crypt\Hashids(C('WORKER_CODE_KEY'), C('WORKER_CODE_MIN_LENGTH'));
    return $hashids->encode($worker_id);
}

function decodeWorkerCode($worker_code)
{
    $hashids = new \Library\Crypt\Hashids(C('WORKER_CODE_KEY'), C('WORKER_CODE_MIN_LENGTH'));

    return $hashids->decode($worker_code)[0];
}


function createSystemMessageAndNotification($worker_id, $data_id, $data_type, $title, $content, $qywechat_application_id = null)
{
    $worker = \Common\Common\Model\BaseModel::getInstance('worker')->getOne($worker_id, 'worker_telephone,jpush_alias');

    $description = $content;
    if ($content instanceof \Stoneworld\Wechat\Messages\NewsItem) {
        $description = $content->description;
        $message = \Stoneworld\Wechat\Message::make('news')->item($description);
        sendQyWechatNotification($worker['worker_telephone'], $message, $qywechat_application_id ?? C('SEND_NEWS_MESSAGE_APPLICATION_ID'));
    } else {
        sendQyWechatNotification($worker['worker_telephone'], $description, $qywechat_application_id ?? C('SEND_NEWS_MESSAGE_APPLICATION_ID'));
    }

    //消息记录
    $id = \Common\Common\Service\AppMessageService::create($worker_id, $data_id, $data_type, $title, $description);
    if (!empty($id)) {
        //极光推送
        workerNotificationJPush($worker['jpush_alias'], $data_type, $id, $title, $description, $data_id);
    }
}

/**
 * 验证手机号是否正确
 * @author honfei
 * @param number $mobile
 */
function isMobile($mobile)
{
    if (!is_numeric($mobile)) {
        return false;
    }
    return preg_match('#^1[38]\d{9}$|^14[57]\d{8}$|^15[^4]\d{8}$|^17[0678]\d{8}$#', $mobile)? true: false;
}

function isInteger($val)
{
    if (!is_numeric($val)) {
        return false;
    }
    return preg_match('#^[1-9]\d*$#', $val) ? true : false;
}

/**
 * @param array  $key_arr
 * @param array  $return
 * @param string $primary_field
 * @param string $children_field
 * @return array $return
 */
function keyArrTreeData($key_arr, &$return, $primary_field = 'id', $children_field = 'children', $keep_key = true)
{
    foreach ($return as &$v) {
//        $v[$children_field] = array_values($key_arr[$v[$primary_field]]);
        $v[$children_field] = $keep_key ? $key_arr[$v[$primary_field]] : array_values($key_arr[$v[$primary_field]]);
        keyArrTreeData($key_arr, $v[$children_field], $primary_field, $children_field, $keep_key);
    }
    return $return;
}

/**
 * 保留 但是没有用到: 树状图数据中取每一个分支的最后一级，删除重复节点
 * @param $key_arr
 * @param $check
 * @param array $result
 * @param string $primary_field
 * @param string $parent_field
 * @param array $check_children
 */
function keyArrFindLastChildrer($key_arr, $check, &$result = [], $primary_field = 'id', $parent_field = 'parent_id', $check_children = [])
{
    if (!$check) {
        return;
    }

    if (!$check_children) {
        $data = array_pop($check);
        $id = $data[$primary_field];
        $check_children = $key_arr[$id];
    } else {
        $data = $check;
    }

    if ($check_children) {
        $childer_data = [];
        foreach ($check_children as $v) {
            if ($check[$v[$primary_field]] || $result[$v[$primary_field]]) {
                unset($result[$data[$primary_field]]);
                return;
            }
            $key_arr[$v[$primary_field]] && $childer_data[] = $key_arr[$v[$primary_field]];
        }
        if ($childer_data) {
            keyArrFindLastChildrer($key_arr, $data, $result, $primary_field, $parent_field, $childer_data);
        } else {
            $result[$data[$primary_field]] = $data;
        }
    } elseif ($data) {
        $result[$data[$primary_field]] = $data;
    }

    keyArrFindLastChildrer($key_arr, $check, $result, $primary_field, $parent_field);
}

/**
 *
 * @param $key_arr
 * @param $datas
 * @param string $primary_field
 * @param string $parent_field
 * @param string $children_field
 * @return array
 */
function keyArrFindNeedTreeData($key_arr, $datas, $primary_field = 'id', $parent_field = 'parent_id', $children_field = 'children')
{
    $return = [];

    foreach ($datas as $k => $v) {
        $data = $v;
        $id = $data[$primary_field];
        $parent_id = $data[$parent_field];
        $i = 0;
        $parent_data = null;
        do {
            if ($parent_data) {
                $data = $parent_data;
                $id = $data[$primary_field];
                $parent_id = $data[$parent_field];
            }

            $parent_data = $key_arr[$parent_id];
            if ($parent_data || $data[$parent_field] == 0) {
                $pre_id = $parent_data[$primary_field] ?? 0;
                $data && $return[$pre_id][$id] = $data;
            } else {
                break;
            }
            ++$i;
        } while ($data[$parent_field] != 0);
//        } while ($data[$parent_field] != 0 && $i < 10);
    }

    $arr = array_values($return[0]);

    keyArrTreeData($return, $arr, $primary_field, $children_field, false);
    return $arr;
}

function curlPostHttps($url,$data){ // 模拟提交数据函数
    $curl = curl_init(); // 启动一个CURL会话
    curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 1); // 从证书中检查SSL加密算法是否存在
    curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
    curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
    curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data); // Post提交的数据包
    curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
    curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
    $tmpInfo = curl_exec($curl); // 执行操作
    if (curl_errno($curl)) {
//        echo 'Errno'.curl_error($curl);//捕抓异常
        throw new \Exception(curl_error($curl));
    }
    curl_close($curl); // 关闭CURL会话
    return $tmpInfo; // 返回数据，json格式
}


function isSuperAdministrator($admin_id)
{
    $admin_role_ids = \Common\Common\CacheModel\AdminCacheModel::getRelation($admin_id, 'rel_admin_roles', 'admin_id', 'admin_roles_id');
    return in_array(C('SUPERADMINISTRATOR_ROLES_ID'), $admin_role_ids);

}

/**
 * 批量处理位运算
 * @param array $x_arr
 * 格式： 1，2，4，8.......
 */
function getXarr($x_arr = [])
{
    $x_arr = array_unique(array_filter($x_arr));
    if (!$x_arr) {
        return null;
    }

    $return = [];
    foreach ($x_arr as $v) {
        $return[$v] = getX($v);
    }

    return $return;
}

function getX($x)
{
    $arr = [];
    $byte = 1;
    while($x) {
        if ($x & 1) {
            $arr[] = $byte;
        }
        $byte <<= 1;
        $x >>= 1;
    }
    return $arr;
}
