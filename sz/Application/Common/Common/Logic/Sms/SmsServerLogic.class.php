<?php
/**
* @User zjz
* @Date 2016/12/16
*/
namespace Common\Common\Logic\Sms;

use Common\Common\Model\BaseModel;
use Common\Common\ErrorCode;
use GuzzleHttp\Client;
use Think\Log;

class SmsServerLogic
{

    // ==================================服务相关==================================
//    const CDKEY = '8SDK-EMY-6699-RFSOS';
    const CDKEY = 'GZDL-9F53DJBZOL';
    const USERNAME = '';
//    const PASSWORD = '839286';
    const PASSWORD = '615829';

    const MAX_GET_NUMBER = 100;
    const MAX_POST_NUMBER = 200;

    const CONTENT_SUFFIX = ' 退订回复TD';

//    const API_URL = 'http://hprpt2.eucp.b2m.cn:8080/sdkproxy/sendsms.action';
    const API_URL = 'http://116.58.218.56:8080/sdkproxy/sendsms.action';

    protected static $httpClient;

    // ==================================数据相关==================================
    const TNAME = 'queue_temporary';
    const MAX_ERROR_NUM = 3;
    const MAX_SELETC = 10;

    protected $_model;
    protected $_morder;

    public $_morder_name;
    public $_morder_config = [];

    public $_sms_list;
    public $_sms_result = [];

    public $send_error_code;

    // ==================================处理数据==================================
    function __construct($table_name = '', $test = false)
    {
        if (!$table_name) {
            new \Exception("Error Processing Request", 1);
        }
        $this->_morder_name = $table_name;
        $this->ready();
    }
    
    protected function ready()
    {
        $this->_model  = D(self::TNAME);
        $this->_morder = D($this->_morder_name);
    }
        

    // 队列工作中
    protected function working($map = [], $limit = 0)
    {
            ($limit > self::MAX_SELETC || $limit == 0)
        &&  $limit = self::MAX_SELETC;
        if (!$this->_sms_list && $data = $this->_model->limit($limit)->where($map)->select()) {
            $this->_sms_list = $data;
        }
        if (count($this->_sms_list)) {
            $this->autoList();
        }
        $this->run_sms_result();
    }

    // 对短信服务商返回结果做处理
    protected function autoList()
    {
        foreach ($this->_sms_list as $k => $v) {
            $result = $this->smsServer($v);
            if ($result) {
                $this->_sms_result['success'][$v['id']] = $v;
            }else if ($this->_model->where($v['id'])->getField('error_num') >= self::MAX_ERROR_NUM) {
                ++$v['error_num'];
                $this->_sms_result['error'][$v['id']] = $v;
            }
            unset($this->_sms_list[$k]);
        }
    }

    // 开始发送 并统计 错误次数
    protected function smsServer($data)
    {
        try {
            if ($this->toOne($data['phone'], $data['content'])) {
            // if (true) {
                return true;
            }else{
                throw new \Exception(ErrorCode::SYS_DB_ERROR);
            }
        } catch (\Exception $e) {
            $this->_model->where($data['id'])->setInc('error_num');
            return false;
        }
    }

    // 处理结果
    public function run_sms_result()
    {
        $config  = $this->_morder_config;
        $success = (array)$this->_sms_result['success'];
        $error   = (array)$this->_sms_result['error'];
        unset($this->_sms_result, $this->_sms_result);

        $remove_ids = [];
        foreach (array_merge($success, $error) as $k => $v) {
            $remove_ids[$v['id']] = $v['id'];
        }
        if (count($remove_ids)) {
            $this->_model->where(['id' => ['in', implode(',', $remove_ids)]])->delete();
            // false === $this->_model->where(['id' => ['in', implode(',', $remove_ids)]])->delete() && $this->throwException(ErrorCode::DB_ERROR);
        }

        if ($config['return_type'] === 'model_function') {
            if (count($success)) {
                $this->_morder->$config['success_function']($success);
            }
            if (count($error)) {
                $this->_morder->$config['error_function']($error);
            }
        }

    }

    // 安信捷 服务商接口文档 提供错误码
    public function getErrorCode($code = null)
    {
        $errorCode = [
            '01'    => '提交参数异常/参数不完整，用户信息错误',
            '02'    => '手机号参数异常',
            '03'    => '扩展号参数异常',
            '04'    => '发送时间参数异常',
            '05'    => '短信内容解析异常/长短信处理异常',
            '10'    => 'IP认证失败',
            '11'    => '帐户认证失败',
            '12'    => '扩展号长度异常',
            '13'    => '手机号异常(黑白名单)',
            '14'    => '违禁词异常',
            '15'    => '帐户余额不足',
            '16'    => '单日条数限制',
            '17'    => '信息检查失败',
        ];
        $return = ($code === null)?$errorCode:$errorCode[$code];
        return ($code === null)?$errorCode:$errorCode[$code];
    }

    // 自动运行 
    public function autoRun($morder_config = [], $success_save = [], $error_save = [])
    {
        $this->_morder_config       = $morder_config;
        $this->_morder_success_save = $success_save;
        $this->_morder_error_save   = $error_save;
        $this->working();
    }

    // 用于服务器关闭 / 异常关闭 重启服务器后执行，没有处理完的队列。异常期间 未加进队列 的短信处理
    public function autoAddTemporary($map, $config = [], $morder_config = [])
    {
        // $this->_morder_config = $morder_config;
        $inserts = [];
        foreach ($this->_morder->getList(['where' => $map]) as $k => $v) {
            $inserts[] = [
                'table_name'    => $this->_morder_name,
                'table_id'      => $v[$config['table_id']?$config['table_id']:'table_id'],
                'phone'         => $v[$config['phone']?$config['phone']:'phone'],
                'content'       => $v[$config['content']?$config['content']:'content'],
                'type'          => $v[$config['type']?$config['type']:'type'],
            ];
        }   
        if (count($inserts)) {
            $this->_model->addAll($inserts);
        }
    }

    // 加入队列
    public function addTemporary($data)
    {
        $inserts = [];
        foreach ($data as $k => $v) {
            $inserts[] = [
                'table_name'    => $this->_morder_name,
                'table_id'      => $v['table_id'],
                'phone'         => $v['phone'],
                'content'       => $v['content'],
                'type'          => $v['type'],
            ];
        }   
        if (count($inserts)) {
            $count = $this->_model->addAll($inserts);
        }
    }

    // ==================================启动服务相关==================================
    public function toOne($phone, $content, $with_suffix = false)
    {
        if (empty($phone)) {
            throw new \Exception('Phones can not empty');
        }
        if (empty($content)) {
            throw new \Exception('Sms message can not empty');
        }

        $content = $with_suffix ? ($content . self::CONTENT_SUFFIX) : $content;

        $result = $this->send($phone, $content);
        return $result;
    }

    public function toMany($phones, $content, $with_suffix = false, $stop_when_occur_error = false)
    {
        if (empty($phones)) {
            throw new \Exception('Phones can not empty');
        }
        if (empty($content)) {
            throw new \Exception('Sms message can not empty');
        }

        if (!is_array($phones)) {
            $phones = [$phones];
        }

        $phone_list = array_chunk($phones, self::MAX_POST_NUMBER);

        $content = $with_suffix ? ($content . self::CONTENT_SUFFIX) : $content;;

        foreach ($phone_list as $v){
            $result = $this->send($v, $content);
            if (!$result && $stop_when_occur_error) {
                throw new \Exception('Send sms error.');
            }
        }
    }

    /**
     * @return \GuzzleHttp\Client
     */
    protected static function getHttp()
    {
        if (!static::$httpClient) {
            static::$httpClient = new Client();
        }
        return static::$httpClient;
    }

    protected function send($phones, $content)
    {
        if (!is_array($phones)) {
            $phones = [$phones];
        }

        $params = [
        	'cdkey' => self::CDKEY,
        	'password' => self::PASSWORD,
        	'phone' => reset($phones),
        	'message' => $content,
        ];

        if (!$this->sms_https_post(self::API_URL, $params)) {
        	return false;
        }

        // $params = [
        //     'name' => self::USERNAME,
        //     'pass' => self::PASSWORD,
        //     'subid' => '',
        //     'content' => $content,
        //     'sendtime' => date('YmdHis'),
        //     'mobiles' => implode('|', $phones)
        // ];
        // $http_client = static::getHttp();
        // $response = $http_client->request('POST', self::API_URL, ['query' => $params]);
        // $this->send_error_code = substr(rtrim($response->getBody()), -2);
        // if ($this->send_error_code !== '00') {
        //     $error_msg = '短信发送失败:'. $response->getBody() . ":(".$this->getErrorCode($error_code).") \r\n mobiles:" . $params['mobiles'];
        //     Log::write($error_msg, Log::ERR, '', RUNTIME_PATH . '/Logs/sms.log');
        //     throw new \Exception($this->send_error_code);
        // }
        return true;
    }

    protected function sms_https_post($url,$data){
	    $curl = curl_init();
	    curl_setopt($curl, CURLOPT_URL, $url);
	    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
	    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
	    curl_setopt($curl, CURLOPT_POST, 1);
	    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
	    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	    $result = curl_exec($curl);
	    if(curl_errno($curl)){
	        header('Content-Type:application/json; charset=utf-8');
	        $json['errorcode'] = 2;
	        $json['msg'] = curl_error($curl);
	        // die(json_encode($json));
	        Log::write(json_encode($json), Log::ERR, '', RUNTIME_PATH . '/Logs/sms.log');
	        return false;
	    }
	    curl_close($curl);
	    return $result;
	}
}