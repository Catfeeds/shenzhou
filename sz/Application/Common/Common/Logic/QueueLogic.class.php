<?php
/**
* @User zjz
* @Date 2016/12/16
*/
namespace Common\Common\Logic;

use Common\Common\Model\BaseModel;
use Common\Common\ErrorCode;
use GuzzleHttp\Client;
use Think\Log;
use Common\Common\Logic\Sms\SmsServerLogic;

class QueueLogic
{
	public function smsQueue()
	{
		set_time_limit(0);
		// $i = 0;
        // $a = memory_get_usage();
        $server = new SmsServerLogic('queue_message');
        // var_dump('new AxjSmsServer() '.(memory_get_usage() - $a));
        do {
            // $a = memory_get_usage();
            $server->autoRun(
                    [
                        'return_type'         => 'model_function',
                        'success_function'     => 'anxinjie_sms_success',
                        'error_function'     => 'anxinjie_sms_error',
                    ]
                );
            // $b = memory_get_usage();
            // var_dump($server->_sms_result, 'start => '.$a, 'end => '.$b, $b - $a);
            // ++$i;
            // var_dump($i);
            // sleep(10);
        } while (true);
	}

}
