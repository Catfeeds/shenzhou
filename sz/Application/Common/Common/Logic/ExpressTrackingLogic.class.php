<?php
/**
 * Function:物流
 * File: ExpressTrackingLogic.class.php
 * User: sakura
 * Date: 2017/11/10
 */

namespace Common\Common\Logic;

use Common\Common\ErrorCode;
use Common\Common\Repositories\Events\ExpressCompleteEvent;
use Common\Common\Service\OrderService;
use Common\Common\Service\SystemMessageService;
use Library\Common\Util;
use Common\Common\Model\BaseModel;
use Library\Express\Kuaidi100;

class ExpressTrackingLogic extends BaseLogic
{

    protected $tableName = 'express_tracking';

    //配件单类型
    const TYPE_ACCESSORY_SEND         = 1; // 配件单发件
    const TYPE_ACCESSORY_SEND_BACK    = 2; // 配件单返件
    const TYPE_ORDER_PRE_INSTALL_SEND = 3; // 工单预安装单发件

    // 订阅的回调url
    protected $callback_url = '/admin.php/express/callback/%s';

    /**
     * 添加快递单订阅，并保存到记录表，如果已经存在，则更新记录，否则添加新记录
     *
     * @param string $express_code   快递公司代号
     * @param string $express_number 快递单号
     * @param int    $data_id        关联数据ID
     * @param int    $type           单号内标识 1-配件单发件 2-配件单返件 3-工单预发件安装单发件
     *
     * @return void
     */
    public function setExpressTrack($express_code, $express_number, $data_id, $type)
    {
        //检查参数
        if (
            empty($express_code) ||
            empty($express_number) ||
            $data_id <= 0 ||
            empty($type)
        ) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }
        $valid_type = [self::TYPE_ACCESSORY_SEND, self::TYPE_ACCESSORY_SEND_BACK, self::TYPE_ORDER_PRE_INSTALL_SEND];
        if (!in_array($type, $valid_type)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '单号标识错误');
        }

        $model = BaseModel::getInstance($this->tableName);

        $where = ['data_id' => $data_id, 'type' => $type];
        $express_info = $model->getOne($where);
        $express_id = 0;
        $is_book = 0;
        $log_state = 0;
        $log_express_number = $express_number;
        $log_express_code = $express_code;
        if (empty($express_info)) {
            $insert_data = [
                'express_number' => $express_number,
                'express_code'   => $express_code,
                'data_id'        => $data_id,
                'type'           => $type,
                'create_time'    => time(),
            ];
            $express_id = $model->insert($insert_data);
        } else {
            $express_id = $express_info['id'];
            $is_book = $express_info['is_book'];
            $log_state = $express_info['state'];
            $log_express_number = $express_info['express_number'];
            $log_express_code = $express_info['express_code'];
        }

        //订阅
        if (
            (1 != $is_book && 3 != $log_state) ||
            $log_express_number != $express_number ||
            $log_express_code != $express_code
        ) {
            //订阅跟踪
            $callback_url = sprintf($this->callback_url, $express_id);
            $callback_url = Util::getServerUrl() . $callback_url;
            $result = Kuaidi100::track($express_code, $express_code, $callback_url);
            $is_book = '200' == $result['returnCode'] ? 1 : 0;
            $update_data = [
                'is_book'          => $is_book,
                'last_update_time' => time(),
                'express_number'   => $express_number,
                'express_code'     => $express_code,
            ];
            $model->update($express_id, $update_data);
        }

        if (
            3 != $log_state ||
            $log_express_number != $express_number ||
            $log_express_code != $express_code
        ) {
            //获取当前物流信息
            $query = Kuaidi100::queryOrder($express_code, $express_number);

            //更新物流信息
            $current_state = -1;
            if (array_key_exists('state', $query)) {
                $current_state = $query['state'];
            }
            $content = [];
            if (array_key_exists('data', $query)) {
                $content = $query['data'];
            }
            $content_str = json_encode($content);

            $update_data = [
                'state'            => $current_state,
                'content'          => $content_str,
                'last_update_time' => NOW_TIME,
                'express_number'   => $express_number,
                'express_code'     => $express_code,
            ];
            $model->update($express_id, $update_data);

            if (3 == $current_state) {
                //对方已签收
                $event_data = [
                    'type'    => $type,
                    'data_id' => $data_id,
                ];
                event(new ExpressCompleteEvent($event_data));
            }
        }
    }

    /**
     * 物流订阅成功之后，快递100 回调处理
     *
     * @param $express_id
     */
    public function ruleExpressCallBack($express_id)
    {
        $data = Kuaidi100::getCallBackData();
        $express_number = $data['number'];
        $express_code = $data['com'];
        $state = $data['state'];
        $content = $data['content'];

        //检查参数
        if (
            empty($express_code) ||
            empty($express_number) ||
            empty($express_id)
        ) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        //获取快递记录
        $express_model = BaseModel::getInstance($this->tableName);
        $express_info = $express_model->getOneOrFail($express_id);
        $type = $express_info['type'];
        $data_id = $express_info['data_id'];

        //更新快递数据
        $update_data = [
            'state'            => $state,
            'content'          => json_encode($content),
            'last_update_time' => NOW_TIME,
        ];
        $express_model->update($express_id, $update_data);

        //已签收件处理
        if (3 == $state) {
            if (ExpressTrackingLogic::TYPE_ACCESSORY_SEND == $type) {
                //配件单发件
            } elseif (ExpressTrackingLogic::TYPE_ACCESSORY_SEND_BACK == $type) {
                //配件单返件
            } elseif (ExpressTrackingLogic::TYPE_ORDER_PRE_INSTALL_SEND == $type) {
                $worker_order_id = $data_id;
                $worker_order = BaseModel::getInstance('worker_order')
                    ->getOneOrFail($worker_order_id);
                $distributor_id = $worker_order['distributor_id'];

                $orno = $worker_order['orno'];

                $sys_msg = "工单号{$orno}的配件，物流已签收";
                //工单 预安装
                SystemMessageService::create(SystemMessageService::USER_TYPE_ADMIN, $distributor_id, $sys_msg, $data_id, SystemMessageService::MSG_TYPE_ADMIN_ORDER_WORKER_TAKE);
            }
        }
    }

    public function getExpress($param)
    {
        //获取参数
        $type = $param['type'];
        $data_id = $param['data_id'];
        $express_number = $param['express_number'];
        $is_refresh = $param['is_refresh'];

        //检查参数
        if (empty($type) || empty($data_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }
        $valid_type = [ExpressTrackingLogic::TYPE_ACCESSORY_SEND, ExpressTrackingLogic::TYPE_ACCESSORY_SEND_BACK, ExpressTrackingLogic::TYPE_ORDER_PRE_INSTALL_SEND];
        if (!in_array($type, $valid_type)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '快递单类型错误');
        }

        //获取物流信息
        $express = $this->getExpressRecord($type, $data_id, $express_number);
        if (empty($express)) {
            return null;
        }

        $is_book = $express['is_book'];
        $last_update_time = $express['last_update_time'];
        $express_code = $express['company_code'];
        $state = $express['state'];

        if (
            1 == $is_refresh &&
            !empty($express_code) &&
            !empty($express_number) &&
            1 != $is_book &&
            3 != $state &&
            $last_update_time + 120 < NOW_TIME
        ) {
            //订阅不成功 快递尚未签收 而且离最近刷新超过2分钟,直接从第三方接口获取,并尝试重新订阅
            $this->setExpressTrack($express_code, $express_number, $data_id, $type);
            $express = $this->getExpressRecord($type, $data_id);
            if (empty($express)) {
                return null;
            }
        }

        return $express;
    }

    protected function getExpressRecord($type, $data_id, $express_number = null)
    {
        //获取物流信息
        $opts = [
            'field' => 'express_code,content,is_book,last_update_time,state,express_number',
            'where' => ['type' => $type, 'data_id' => $data_id],
            'order' => 'id desc',
        ];
        if ($express_number) {
            $express = null;
            foreach (BaseModel::getInstance($this->tableName)->getList($opts) as $v) {
                if ($v['express_number'] == $express_number) {
                    $express = $v;
                }
            }
        } else {
            $express = BaseModel::getInstance($this->tableName)->getOne($opts);
        }
        if (empty($express)) {
            //物流不存在,直接返回
            return null;
        }

        //参数
        $is_book = $express['is_book'];
        $last_update_time = $express['last_update_time'];
        $express_code = $express['express_code'];
        $content = $express['content'];
        $state = $express['state'];
        $express_number = $express['express_number'];
        $content_arr = json_decode($content, true);
        $schedule = empty($content_arr) ? null : $content_arr;

        $opts = [
            'field' => 'name',
            'where' => ['comcode' => $express_code],
        ];
        $company = BaseModel::getInstance('express_com')->getOne($opts);
        $company_name = $company['name']?? '';

        return [
            'schedule'         => $schedule,
            'state'            => $state,
            'express_number'   => $express_number,
            'company_code'     => $express_code,
            'company_name'     => $company_name,
            'is_book'          => $is_book,
            'last_update_time' => $last_update_time,
        ];
    }

}