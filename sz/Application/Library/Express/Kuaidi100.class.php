<?php

namespace Library\Express;

class Kuaidi100
{

    // 快递100 提供的配置
    const CONFIG_KEY      = 'wVXlZZVl1983';
    const CONFIG_CUSTOMER = '11310083EE095BABC24AC220AA406274';

    const URL_POLL            = 'http://www.kuaidi100.com/poll'; // 订阅
    const URL_POLL_QUERY_DO   = 'http://poll.kuaidi100.com/poll/query.do'; // 物流实时查询
    const URL_AUTONUMBER_AUTO = 'http://www.kuaidi100.com/autonumber/auto'; //

    /**en
     * 添加快递单订阅
     *
     * @param string $company      快递公司代号
     * @param string $number       快递单号
     * @param string $callback_url 订阅回调地址
     *
     * @return array
     */
    public static function track($company, $number, $callback_url)
    {
        // callbackurl 请参考 callback.php 实现，key经常会变，请与快递100联系获取最新key
        // $post_data["param"] = '{"company":"'.$com_code.'", "number":"'.$number.'","from":"", "to":"", "key":"'.self::CONFIG_KEY.'", "parameters":{"callbackurl":"'.$exp_call_back.'"}}';
        $post_data_param_arr = [
            'company'    => $company,
            'number'     => $number,
            'from'       => '',
            'to'         => '',
            'key'        => self::CONFIG_KEY,
            'parameters' => [
                'callbackurl' => $callback_url,
            ],
        ];
        $post_data = [
            'schema' => 'json',
            'param'  => json_encode($post_data_param_arr),
        ];
        //默认UTF-8 编码格式
        // foreach ($post_data as $k => $v) {
        // 	$post_data[$k] = urlencode($v);
        // }
        $post_data_str = http_build_query($post_data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_URL, self::URL_POLL);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data_str);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);//返回提交结果，格式与指定的格式一致（result=true代表成功）
        curl_close($ch);

        return json_decode($result, true);

    }

    /**
     * 物流订阅成功之后，快递100 回调处理
     *
     * @return array|false
     * |-number string 快递号
     * |-com string 快递公司代号
     * |-state string 快递状态
     * |-content string 快递详情
     */
    public static function getCallBackData()
    {
        $data = htmlEntityDecodeAndJsonDecode($_POST['param']);
        if (empty($data)) {
            return false;
        }

        return [
            'number'  => $data['lastResult']['nu'],
            'com'     => $data['lastResult']['com'],
            'state'   => $data['lastResult']['state'],
            'content' => $data['lastResult']['data'],
        ];

    }

    /**
     * 查询快递单
     *
     * @param string $company 快递公司代号
     * @param string $number  快递单号
     *
     * @return array
     */
    public static function queryOrder($company, $number)
    {
        $post_data_param_arr = [
            'com' => $company,
            'num' => $number,
        ];
        $customer = self::CONFIG_CUSTOMER;
        $param = json_encode($post_data_param_arr);

        $post_data = [
            'customer' => $customer,
            'param'    => $param,
        ];
        // 签名
        $sign = $param . self::CONFIG_KEY . $customer;
        $post_data['sign'] = strtoupper(md5($sign));

        //默认UTF-8编码格式
        // foreach ($post_data as $k => $v) {
        // 	$post_data[$k]= urlencode($v);
        // }
        $post_data_str = http_build_query($post_data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_URL, self::URL_POLL_QUERY_DO);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data_str);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);

        $data = str_replace('\&quot;', '"', $result);
        $data = json_decode($data, true);

        return $data;
    }

    /**
     * 通过单号获取可能的快递公司
     *
     * @param string $number 快件单号
     *
     * @return array
     */
    public static function autoComCode($number = '')
    {
        $post_data = [
            'num' => $number,
            'key' => self::CONFIG_KEY,
        ];

        //默认UTF-8编码格式
        foreach ($post_data as $k => $v) {
            $post_data[$k] = urlencode($v);
        }
        $post_data_str = http_build_query($post_data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_URL, self::URL_AUTONUMBER_AUTO);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data_str);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);

        $data = str_replace('\&quot;', '"', $result);
        $data = json_decode($data, true);
        // $com_code_arr = arrFieldForStr($data, 'comName');
        foreach ($data as $key => $val) {
            $val['number'] = $number;

            $data[$key] = $val;
        }

        return $data;
    }

}