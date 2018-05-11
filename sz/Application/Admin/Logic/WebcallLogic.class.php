<?php
/**
 * File: WebcallLogic.class.php
 * Function:
 * User: sakura
 * Date: 2018/1/18
 */

namespace Admin\Logic;


use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Common\Common\Service\AuthService;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\WebcallService;
use Library\Common\WebCall;
use Library\Crypt\AuthCode;

class WebcallLogic extends BaseLogic
{

    public function adminLink2User($param)
    {
        $user_type = $param['user_type']; // 用户类型 1-技工 2-用户 3-技术支持人
        $user_id = $param['user_id']; // 用户id user_type=2时,user_id是工单id
        $worker_order_id = $param['worker_order_id'];

        if ($user_id <= 0 || $worker_order_id <= 0) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        if (!in_array($user_type, WebcallService::CALLED_USER_TYPE_VALID_ARRAY)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '用户类型错误');
        }

        //获取呼叫电话
        $admin_info = AuthService::getAuthModel();
        $admin_id = AuthService::getAuthModel()->getPrimaryValue();
        $agent = $admin_info['agent'];
        if ($agent <= 0) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '请绑定坐席');
        }

        //获取被呼电话
        $target_tel = '';
        $user_name = '';
        if (WebcallService::CALLED_USER_TYPE_WORKER == $user_type) {
            //技工
            $worker_model = BaseModel::getInstance('worker');
            $user_info = $worker_model->getOneOrFail($user_id);
            $target_tel = $user_info['worker_telephone'];
            $user_name = $user_info['nickname'];
        } elseif (WebcallService::CALLED_USER_TYPE_USER == $user_type) {
            //用户
            $user_model = BaseModel::getInstance('worker_order_user_info');
            $user_info = $user_model->getOneOrFail($user_id);
            $target_tel = $user_info['phone'];
            $user_name = $user_info['real_name'];
        } elseif (WebcallService::CALLED_USER_TYPE_FACTORY_HELPER == $user_type) {
            //技术支持人
            $helper_model = BaseModel::getInstance('factory_helper');
            $user_info = $helper_model->getOneOrFail($user_id);
            $target_tel = $user_info['telephone'];
            $user_name = $user_info['name'];
        }

        M()->startTrans(); // 由于有网络请求,有可能占用比较长时间,所以事务在请求前结束

        //日志
        $extra = [
            'content_replace' => [
                'user_type' => WebcallService::getCalledUserTypeStr($user_type),
                'user_name' => $user_name,
            ],
        ];
        OrderOperationRecordService::create($worker_order_id, OrderOperationRecordService::CS_CALL_TO_USER, $extra);

        //绑定关系
        $call_model = BaseModel::getInstance('worker_order_webcall');
        $insert_data = [
            'worker_order_id'  => $worker_order_id,
            'call_user_type'   => WebcallService::CALL_USER_TYPE_ADMIN,
            'call_user_id'     => $admin_id,
            'called_user_type' => $user_type,
            'called_user_id'   => $user_id,
            'create_time'      => NOW_TIME,
            'status'           => WebcallService::STATUS_CREATED,
            'agent'            => $agent,
        ];
        $insert_id = $call_model->insert($insert_data);

        M()->commit(); // 由于有网络请求,有可能占用比较长时间,所以事务在请求前结束

        $call_no = AuthCode::encrypt($insert_id, C('WEBCALL.SALT'));
        //电信云接口
        $exten = 'gateway';
        $response = WebCall::createAgent($agent, $target_tel, $call_no, $exten);
        if (empty($response)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '接口返回为空');
        }

        $is_success = $response['Succeed'];
        if (!$is_success) {
            $action_id = $response['ActionID'];
            $error_msg = $response['Message'];
            $str = $action_id.':'.$error_msg;
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, $str);
        }

        return [
            'webcall_data' => $response,
        ];
    }

    public function hangup($param)
    {
        $webcall_data = $param['data'];
        $primary_key = $param['primary_key'];
        $call_id = AuthCode::decrypt($primary_key, C('WEBCALL.SALT'));

        if (empty($call_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        $model = BaseModel::getInstance('worker_order_webcall');
        $call_info = $model->getOneOrFail($call_id);
        $status = $call_info['status'];
        $call_user_id = $call_info['call_user_id'];
        $worker_order_id = $call_info['worker_order_id'];
        $called_user_type = $call_info['called_user_type'];
        $called_user_id = $call_info['called_user_id'];
        $create_time = $call_info['create_time'];

        //判断状态
        if (WebcallService::STATUS_HANGUP == $status) {
            return 0;
        }

        $begin = strtotime($webcall_data['Begin']);
        $end = strtotime($webcall_data['End']);
        $file_server = $webcall_data['FileServer'];
        $record_file = $webcall_data['RecordFile'];
        $state = $webcall_data['State'];
        //$state 接听状态：dealing（已接）,notDeal（振铃未接听）,leak（ivr放弃）,queueLeak（排队放弃）,blackList（黑名单）,voicemail（留言）
        if ('dealing' != $state) {
            $fail_model = BaseModel::getInstance('worker_order_webcall_fail');
            $fail_model->insert([
                'webcall_id'  => $call_id,
                'state'       => $state,
                'raw_data'    => json_encode(['post' => http_build_query($_POST), 'get' => http_build_query($_GET)]),
                'create_time' => NOW_TIME,
            ]);
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '电话未接通');
        }

        $user_name = '';
        if (WebcallService::CALLED_USER_TYPE_WORKER == $called_user_type) {
            //技工
            $worker_model = BaseModel::getInstance('worker');
            $user_info = $worker_model->getOneOrFail($called_user_id);
            $user_name = $user_info['nickname'];
        } elseif (WebcallService::CALLED_USER_TYPE_USER == $called_user_type) {
            //用户
            $user_model = BaseModel::getInstance('worker_order_user_info');
            $user_info = $user_model->getOneOrFail($called_user_id);
            $user_name = $user_info['real_name'];
        } elseif (WebcallService::CALLED_USER_TYPE_FACTORY_HELPER == $called_user_type) {
            //技术支持人
            $helper_model = BaseModel::getInstance('factory_helper');
            $user_info = $helper_model->getOneOrFail($called_user_id);
            $user_name = $user_info['name'];
        }

        $record_file_url = $file_server . '/' . $record_file;

        M()->startTrans();

        $update_data = [
            'hangup_time' => NOW_TIME,
            'begin'       => $begin,
            'end'         => $end,
            'record_file' => $record_file_url,
            'status'      => WebcallService::STATUS_HANGUP,
        ];
        $model->update($call_id, $update_data);

        $begin = empty($begin) ? $create_time : $begin;

        //日志
        $remark = '通话录音为：' . "<audio src='{$record_file_url}' controls='controls'></audio>";
        $time_diff = self::timeDiff($begin, $end);
        $extra = [
            'operator_id'     => $call_user_id,
            'content_replace' => [
                'user_type' => WebcallService::getCalledUserTypeStr($called_user_type),
                'user_name' => $user_name,
                'time_len'  => sprintf('%01d:%02d:%02d', $time_diff['hour'], $time_diff['min'], $time_diff['second']),
            ],
            'remark'          => $remark,
        ];
        OrderOperationRecordService::create($worker_order_id, OrderOperationRecordService::CS_CALL_TO_USER_END, $extra);

        M()->commit();

        return 0;

    }

    protected static function timeDiff($begin, $end)
    {
        $diff = $end - $begin;

        if ($diff <= 0) {
            return ['hour' => 0, 'min' => 0, 'second' => 0];
        }

        $hour = floor($diff / 3600);

        $remain_min = $diff % 3600;

        $min = floor($remain_min / 60);

        $second = $remain_min % 60;

        return ['hour' => $hour, 'min' => $min, 'second' => $second];
    }


}