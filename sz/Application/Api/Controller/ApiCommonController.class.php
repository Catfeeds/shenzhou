<?php
/**
* 
*/
namespace Api\Controller;

use Api\Common\ErrorCode;
use Api\Model\BaseModel;
use Api\Controller\BaseController;
use Common\Common\Logic\ExportDataLogic;
use Common\Common\ResourcePool\RedisPool;
use Common\Common\Service\SMSService;
use Library\Common\Util;

class ApiCommonController extends BaseController
{

    public function checkSmsFrequency($phone, $is_check = false)
    {
        $phone_key = 'send_sms_phone_' . $phone;
        $ip_key = 'send_sms_ip_' . $_SERVER['REMOTE_ADDR'];
        $sms_phone = S($phone_key);
        $sms_ip = S($ip_key);
        if ($sms_phone || $sms_ip) {
            $times = max($sms_phone['times'], $sms_ip['times']);
            $expire = strtotime(date('Y-m-d') . '+ 1day');
        } else {
            $times = 0;
            $expire = strtotime(date('Y-m-d') . '+ 1day');
        }


        if ($is_check && $times >= 3) {
            $this->fail(ErrorCode::SYS_SYSTEM_ERROR, '您今天接收的验证码已经超过3次，请明天再试');
        }

        if (!$is_check) {
            $data = array(
                'times' => ++$times,
                'expire' => $expire,
            );
            S($phone_key, $data, ['expire' => $expire - NOW_TIME]);
            S($ip_key, $data, ['expire' => $expire - NOW_TIME]);
        }
    }

    public function mediaToUrl()
    {
        try {
            $ids = I('post.ids', '');
            $data = D('WeChatFile', 'Logic')->downloadFiles($ids);
            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getPhoneCode()
    {
        try {
            $phone = I('phone');
            if (!Util::isPhone($phone)) {
                $this->throwException(ErrorCode::PHONE_GS_IS_WRONG);
            }

            // 指定IP手机号码发送限制 +1
            $this->checkSmsFrequency($phone);

            $phone_user = BaseModel::getInstance('wx_user')->getOne(['telephone' => $phone], 'id,openid');
            if ($phone_user['id'] && $phone_user['openid']) {
                $this->throwException(ErrorCode::HAD_SAME_PHONE);
            }

            $verify = mt_rand(100000, 999999);
            S('C_REGISTER_VERIFY_' . $phone, $verify, ['expire' => 1800]);


            sendSms($phone, SMSService::TMP_WX_USER_REGISTER_PHONE_CODE, [
                'verify' => $verify
            ]);

            if (I('is_return')) {
                $this->response(['verify' => $verify]);
            }
            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
            
        }
    }

    public function errorCodes()
    {
        $is_export = I('get.is_export', 0, 'intval');
        $list = get_class_vars(get_class(new ErrorCode()));
        $customMessage = $list['customMessage'];
        $systemMessage = $list['systemMessage'];
        foreach ($systemMessage as $k => $v) {
            if ($is_export) {
                $export_data[] = ['code' => $k, 'message' => $v];
            } else {
                echo $k.'&nbsp;&nbsp;&nbsp;&nbsp;'.$v.'</br>';    
            }
            
        }
        foreach ($customMessage as $k => $v) {
            if ($is_export) {
                $export_data[] = ['code' => $k, 'message' => $v];
            } else {
                echo $k.'&nbsp;&nbsp;&nbsp;&nbsp;'.$v.'</br>';    
            }
        }
        $is_export ? $this->exportData($export_data, ['状态码', '提示'], '错误码对照表') : '';
    }

    function exportData($excel_data)
    {
        $excel = null;
        $setSheetData_title = [
            'setWidth' => [
                'A' => 20,
                'B' => 80
            ],
            'setCellValue' => [
                1 => [
                    'A' => '状态码',
                    'B' => '提示',
                ],
            ],
        ];
        $setSheetData_lines = [
            'A' => 'code',
            'B' => 'message',
        ];
        $excel = new ExportDataLogic();
        $excel->setSheetTitle('错误码对照表');
        $excel->setExcelForDatas($setSheetData_title, $setSheetData_lines, $excel_data);
        $excel->putOut('错误码对照表'.date('Y-m-d', NOW_TIME));
        die();
    }

    public function weChatMessage()
    {
        $type = I('type');
        $data = I('data', '', '');

        try {
            D('ApiCommon', 'Logic')->weChatMessage($type, $data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

//        $logic = D('WeChatNewsEvent', 'Logic');
//        $message = $logic->arrTotNewsMessages($datas);
//        $wx_id = I('post.id', 0);
//
//        $user = BaseModel::getInstance('wx_user')->getOne($wx_id);
//
//        if (!$user || $wx_id != $user['id'] || !$user['openid']) {
//            $this->fail(0, '没有用户信息');
//        }
//        // 'on0h9sxuFM8jS9EB3-GrkL1bOj6o'
//        try {
//            switch (I('post.type', '')) {
//                case 'text':
//                    $mess = I('post.message', '');
//                        $mess
//                    &&  ($result = $logic->wxSendNewsByOpenId($user['openid'], $mess, 'text'));
//                    break;
//            }
//            $this->response($result);
//        } catch (\Exception $e) {
//            $this->getExceptionError($e);
//        }
    }

    public function checkFactoryMoney()
    {

    }

}