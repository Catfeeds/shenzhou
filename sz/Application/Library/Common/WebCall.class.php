<?php
/**
 * File: WebCall.class.php
 * Function:电信云
 * User: sakura
 * Date: 2018/1/17
 */

namespace Library\Common;


class WebCall
{

    const HOST = 'http://14.29.4.233';

    const PBX = 'gd.zj.1.4';

    const ACCOUNT = 'N00000000628';

    const SECRET = 'ae40e076-08ce-434e-a9c5-3d1350caaa58';

    const SERVICE_NO = '020818193451';

    const REQUEST_URL = self::HOST . '/command';

    const PRIMARY_KEY_NAME = 'callPrimaryKey';

    /**
     * @param string $call             呼出号码
     * @param string $called           被呼叫号码
     * @param string $call_no          话单ID,建议使用唯一,用于事件触发的时候能区分对应的事务
     * @param array  $param            附加项
     *                                 事件推送参数-使用键值对 e.g. ['Timeout' => 20]
     *                                 |-WebCallType string
     *                                 调用WebCall的接口类型（同步异步）当传此值时采用异步的接口
     *                                 asynchronous-异步,不填-同步
     *                                 |-CallBackUrl string
     *                                 调用异步时需要传参，回调的地址(仅支持http),WebCallType=asynchronous必填
     *                                 |-CallBackType string get|post
     *                                 (不填默认get),WebCallType=asynchronous必填
     *                                 |-Variable array
     *
     *                                 |-Timeout string
     *                                 接口呼叫被叫的时长,单位:秒。值的范围为20至60,不填默认20
     *                                 |-MaxCallTime string
     *                                 双向回呼时,设置最大通话时长,单位:秒,从双方接通开始计时，到时间自动掐断,只针对双向回呼场景
     * @param array  $webcall_variable webcall配置参数 webcall使用的参数,格式使用键值对
     *                                 e.g. ['name' => 'jojo']
     *                                 |-callshow string 用户手机显示的坐席号码
     * @param array  $event_variable   事件配置参数 自定义参数,在通话时间对接中推送,格式使用键值对
     *                                 e.g. ['name' => 'jojo']
     *
     *
     * @return mixed
     * @throws \Exception
     */
    public static function create($call, $called, $call_no, $param = [], $webcall_variable = [], $event_variable = [])
    {
        if (!self::isTelNumber($call)) {
            throw new \Exception('呼出号码错误');
        }

        if (!self::isTelNumber($called)) {
            throw new \Exception('被叫号码错误');
        }

        $call = preg_replace('#[^0-9]#', '', $call);
        $called = preg_replace('#[^0-9]#', '', $called);

        if (array_key_exists('Variable', $param)) {
            //剔除Variable内容,防止配置重叠
            unset($param['Variable']);
        }

        $variables = [];
        if (!empty($webcall_variable)) {
            $variables = array_merge($variables, $webcall_variable);
        }
        $variables['called'] = $called;

        if (!empty($event_variable)) {
            foreach ($event_variable as $key => $value) {
                $key_name = '__' . $key; // "__"前缀是为了事件能把参数带过来
                $variables[$key_name] = $value;
            }
        }

        $variables['__' . self::PRIMARY_KEY_NAME] = $call_no;

        if (array_key_exists('callshow', $variables)) {
            $variables['callshow'] = preg_replace('#[^0-9]#', '', $variables['callshow']);
        }

        $variable_str = '';
        foreach ($variables as $key => $val) {
            $variable_str .= $key . ':' . $val . ',';
        }
        $variable_str = rtrim($variable_str, ',');

        $params = array_merge($param, [
            'Action'    => 'Webcall',
            'PBX'       => self::PBX,
            'Account'   => self::ACCOUNT,
            'ServiceNo' => self::SERVICE_NO,
            'Exten'     => $call,
            'Variable'  => $variable_str,
        ]);
        $url = self::REQUEST_URL . '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPGET, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);

        return json_decode($result, true);
    }

    /**
     * 坐席呼叫
     *
     * @param string $agent            坐席号 e.g 8032
     * @param string $called           被呼叫号码
     * @param string $call_no          话单ID,建议使用唯一,用于事件触发的时候能区分对应的事务
     * @param string $exten_type       外呼时强制坐席使 座席不登陆系统发起外呼，需要传此字段。Local-手机
     *                                 sip-软电话 gateway-语音网关
     * @param array  $event_variable   事件配置参数 自定义参数,在通话时间对接中推送,格式使用键值对
     *                                 e.g. ['name' => 'jojo']
     *
     * @return mixed
     * @throws \Exception
     */
    public static function createAgent($agent, $called, $call_no, $exten_type, $event_variable = [])
    {
        if (strlen($agent) <= 0) {
            throw new \Exception('坐席号为空');
        }

        if (!self::isTelNumber($called)) {
            throw new \Exception('被叫号码错误');
        }

        $called = preg_replace('#[^0-9]#', '', $called);

        $variables = [];

        if (!empty($event_variable)) {
            foreach ($event_variable as $key => $value) {
                $key_name = $key;
                $variables[$key_name] = $value;
            }
        }

        $variables[self::PRIMARY_KEY_NAME] = $call_no;

        $variable_str = '';
        foreach ($variables as $key => $val) {
            $variable_str .= $key . ':' . $val . ',';
        }
        $variable_str = rtrim($variable_str, ',');

        $time = date('YmdHis');

        $sig = strtoupper(md5(self::ACCOUNT . self::SECRET . $time));

        $auth_str = base64_encode(self::ACCOUNT . ':' . $time);

        $url = "http://apis.tycc100.com/v20160818/call/dialout/" . self::ACCOUNT . "?sig={$sig}";

        $ch = curl_init();
        $post_data = [
            'FromExten' => $agent,
            'Exten'     => $called,
            'ExtenType' => $exten_type,
            'CdrVar'    => $variable_str,
            // 'Agent'     => '8032',
            // 'PBX'       => 'gd.zj.1.4',
        ];
        $post_data = json_encode($post_data);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept:application/json',
            'Content-Type:application/json;charset=utf-8',
            'Authorization:' . $auth_str,
        ]);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);

        curl_close($ch);

        return json_decode($result, true);
    }

    protected static function isFixedLineNumber($tel)
    {
        return true;
    }

    protected static function isTelNumber($tel)
    {
        return true;
    }


    /**
     * @return array
     * |-CallNo string 主叫号码
     * |-CalledNo string 被叫号码
     * |-CallSheetID string 通话记录ID,CallSheetID是这条通话记录再DB中的唯一id
     * |-CallType string
     * 通话类型：dialout外呼通话,normal普通来电,transfer转接电话,dialTransfer外呼转接
     * |-Ring string 通话振铃时间（话务进入呼叫中心系统的时间）
     * |-Begin string 通话接通时间（呼入是按座席接起的时间,外呼按客户接起的时间,如果没接听的话为空）
     * |-End string 通话结束时间
     * |-QueueTime string 来电进入技能组时间
     * |-Agent string 处理坐席的工号
     * |-Exten string 处理坐席的工号,历史原因该字段与Agent相同
     * |-AgentName string 处理坐席的姓名
     * |-Queue string 通话进入的技能组名称
     * |-State string
     * 接听状态：dealing（已接）,notDeal（振铃未接听）,leak（ivr放弃）,queueLeak（排队放弃）,blackList（黑名单）,voicemail（留言）
     * |-CallState string 事件状态：Ring,Ringing,Link,Hangup(Unlink也当成Hangup处理)
     * |-ActionID string 通过外呼接口调用时,该字段会保存请求的actionID,其它情况下该字段为空
     * |-WebcallActionID string 通过调用webcall接口,该字段会保存请求的actionID,其它情况下该字段为空
     * |-RecordFile string
     * 通话录音文件名：用户要访问录音时,在该文件名前面加上服务路径即可,如：FileServer/RecordFile
     * |-FileServer string 通过FileServer中指定的地址加上RecordFile的值可以获取录音
     * |-Province string 目标号码的省,例如广州市。呼入为来电号码,呼出为去电号码
     * |-District string 目标号码的市,例如广州市。呼入为来电号码,呼出为去电号码
     * |-CallID string
     * 通话ID,通话连接的在系统中的唯一标识。CallID是在通话进行中channel的id,可以用这个id来挂断通话之类的操作。一个call有一个CallID,但一个call可能会出现在多个通话中,比如转接。
     * |-IVRKEY string
     * 通话在系统中选择的按键菜单,10004@0。格式为：按键菜单的节点编号@选择的菜单按键。如果为多级菜单则为：10004@0-10005@1。
     * |-AccountId string 账户编号字段,默认不推送有需求的客户对接时联系云呼技术支持人员进行开通
     * |-AccountName string 账户名称字段,默认不推送有需求的客户对接时联系云呼技术支持人员进行开通
     */
    public static function getEventParam()
    {
        $param = $_GET;

        $event_var = [];

        $primary_key = '';

        if (array_key_exists('CdrVar', $param)) {
            $key_values = explode(',', $param['CdrVar']);
            foreach ($key_values as $key_value) {
                list($key, $val) = explode(':', $key_value);
                $event_var[$key] = $val;
            }

            if (array_key_exists(self::PRIMARY_KEY_NAME, $event_var)) {
                $primary_key = $event_var[self::PRIMARY_KEY_NAME];
            }
        }

        return [
            'data'        => $param,
            'primary_key' => $primary_key,
            'event_var'   => $event_var,
        ];
    }

}