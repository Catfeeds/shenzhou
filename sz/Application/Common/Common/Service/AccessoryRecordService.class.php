<?php
/**
 * File: AccessoryRecordService.class.php
 * User: sakura
 * Date: 2017/11/9
 */

namespace Common\Common\Service;


use Common\Common\ErrorCode;
use Common\Common\Model\BaseModel;
use Common\Common\Repositories\Events\PushXinYingYanOrderStatusChangeEvent;
use Common\Common\Service\UserCommonInfoService\UserInfoType;
use Library\Common\Util;

class AccessoryRecordService
{

    protected $tableName = 'worker_order_apply_accessory_record';

    const ROLE_CS            = 1;
    const ROLE_FACTORY       = 2;
    const ROLE_FACTORY_ADMIN = 3;
    const ROLE_SYSTEM        = 4;
    const ROLE_WORKER        = 5;

    //配件单日志类型
    protected static $operate_type
        = [
            'OPERATE_TYPE_WORKER_APPLY'        => 101,
            'OPERATE_TYPE_WORKER_EDIT_APPLY'   => 102,
            'OPERATE_TYPE_WORKER_CANCEL_APPLY' => 103,
            'OPERATE_TYPE_WORKER_REMIND_SEND'  => 104,
            'OPERATE_TYPE_WORKER_TAKE'         => 105,
            'OPERATE_TYPE_WORKER_SEND_BACK'    => 106,

            'OPERATE_TYPE_CS_CHECKED'    => 201,    // 客服审核通过
            'OPERATE_TYPE_CS_FORBIDDEN'  => 202,    // 客服审核不通过
            'OPERATE_TYPE_CS_STOP_APPLY' => 204,    // 客服终止配件
            'OPERATE_TYPE_CS_APPLY'      => 205,    // 客服申请配件单
            'OPERATE_TYPE_CS_ACT_FACTORY_CHECKED' => 206, // 客服代厂家审核通过
            'OPERATE_TYPE_CS_ACT_FACTORY_CONFIRM_SEND' => 207, // 客服代厂家发件

            'OPERATE_TYPE_FACTORY_CHECKED'                => 301,
            'OPERATE_TYPE_FACTORY_FORBIDDEN'              => 302,
            'OPERATE_TYPE_FACTORY_EDIT_PLAN'              => 304,
            'OPERATE_TYPE_FACTORY_CONFIRM_SEND'           => 305,
            'OPERATE_TYPE_FACTORY_GIVE_UP_SEND_BACK'      => 306,
            'OPERATE_TYPE_FACTORY_EDIT_EXPRESS_SEND'      => 307,
            'OPERATE_TYPE_FACTORY_CONFIRM_SEND_BACK'      => 308,
            'OPERATE_TYPE_FACTORY_STOP_APPLY'             => 309,
            'OPERATE_TYPE_FACTORY_EDIT_EXPRESS_SEND_BACK' => 310,

            'OPERATE_TYPE_SYSTEM_DEFAULT_TAKE'      => 401,
            'OPERATE_TYPE_SYSTEM_DEFAULT_SEND_BACK' => 402,
            'OPERATE_TYPE_SYSTEM_DEFAULT_COMPLETED' => 403,
        ];

    const OPERATE_TYPE_WORKER_APPLY                   = 101;
    const OPERATE_TYPE_WORKER_EDIT_APPLY              = 102;
    const OPERATE_TYPE_WORKER_CANCEL_APPLY            = 103;
    const OPERATE_TYPE_WORKER_REMIND_SEND             = 104;
    const OPERATE_TYPE_WORKER_TAKE                    = 105;
    const OPERATE_TYPE_WORKER_SEND_BACK               = 106;
    const OPERATE_TYPE_CS_CHECKED                     = 201;
    const OPERATE_TYPE_CS_FORBIDDEN                   = 202;
    const OPERATE_TYPE_CS_STOP_APPLY                  = 204;
    const OPERATE_TYPE_CS_APPLY                       = 205; // 客服申请配件单
    const OPERATE_TYPE_CS_ACT_FACTORY_CHECKED         = 206; // 客服代厂家审核通过
    const OPERATE_TYPE_CS_ACT_FACTORY_CONFIRM_SEND    = 207; // 客服代厂家发件
    const OPERATE_TYPE_FACTORY_CHECKED                = 301;
    const OPERATE_TYPE_FACTORY_FORBIDDEN              = 302;
    const OPERATE_TYPE_FACTORY_EDIT_PLAN              = 304;
    const OPERATE_TYPE_FACTORY_CONFIRM_SEND           = 305;
    const OPERATE_TYPE_FACTORY_GIVE_UP_SEND_BACK      = 306;
    const OPERATE_TYPE_FACTORY_EDIT_EXPRESS_SEND      = 307;
    const OPERATE_TYPE_FACTORY_CONFIRM_SEND_BACK      = 308;
    const OPERATE_TYPE_FACTORY_STOP_APPLY             = 309;
    const OPERATE_TYPE_FACTORY_EDIT_EXPRESS_SEND_BACK = 310;
    const OPERATE_TYPE_SYSTEM_DEFAULT_TAKE            = 401;
    const OPERATE_TYPE_SYSTEM_DEFAULT_SEND_BACK       = 402;
    const OPERATE_TYPE_SYSTEM_DEFAULT_COMPLETED       = 403;

    protected $record = null;

    protected $schedule = null;

    public function searchRecord($accessory_id)
    {
        $record_db = BaseModel::getInstance($this->tableName);

        $accessory = BaseModel::getInstance('worker_order_apply_accessory')
            ->getOneOrFail($accessory_id);
        $is_giveup_return = $accessory['is_giveup_return'];
        $cancel_status = $accessory['cancel_status'];
        $accessory_status = $accessory['accessory_status'];

        $field = 'create_time,user_id,user_type,type,content,remark';
        $opts = [
            'where' => ['accessory_order_id' => $accessory_id],
            'field' => $field,
            'order' => 'id desc',
        ];

        $record = $record_db->getList($opts);

        $factory_ids = [];
        $factory_admin_ids = [];
        $admin_ids = [];
        $worker_ids = [];

        foreach ($record as $val) {
            $user_type = $val['user_type'];
            $user_id = $val['user_id'];

            if (self::ROLE_CS == $user_type) {
                $admin_ids[] = $user_id;
            } elseif (self::ROLE_FACTORY == $user_type) {
                $factory_ids[] = $user_id;
            } elseif (self::ROLE_FACTORY_ADMIN == $user_type) {
                $factory_admin_ids[] = $user_id;
            } elseif (self::ROLE_WORKER == $user_type) {
                $worker_ids[] = $user_id;
            }
        }

        $factories = $this->getFactories($factory_ids);
        $factory_admins = $this->getFactoryAdmins($factory_admin_ids);
        $workers = $this->getWorkers($worker_ids);
        $admins = $this->getAdmins($admin_ids);

        foreach ($record as $key => $val) {
            $user_type = $val['user_type'];
            $user_id = $val['user_id'];

            $user_name = '';
            if (self::ROLE_CS == $user_type) {
                $user_name = isset($admins[$user_id]) ? $admins[$user_id]['user_name'] : '';
            } elseif (self::ROLE_FACTORY == $user_type) {
                $user_name = isset($factories[$user_id]) ? $factories[$user_id]['linkman'] : '';
            } elseif (self::ROLE_FACTORY_ADMIN == $user_type) {
                $user_name = isset($factory_admins[$user_id]) ? $factory_admins[$user_id]['nickout'] : '';
            } elseif (self::ROLE_WORKER == $user_type) {
                $user_name = isset($workers[$user_id]) ? $workers[$user_id]['nickname'] : '';
            } elseif (self::ROLE_SYSTEM == $user_type) {
                $user_name = '系统';
            }
            $val['user_name'] = $user_name;

            $remark = $val['remark'];

            $remark = preg_replace_callback("#src=(['\\\"])([^'\"]*)\\1#i", function ($matches) {
                $url = $matches[2];
                if (!preg_match('#^https?://#', $url)) {
                    $url = Util::getServerFileUrl($url);
                }

                return 'src="' . $url . '"';
            }, $remark);
            $val['remark'] = $remark;

            $record[$key] = $val;
        }

        //$map_time = [];
        //
        //foreach ($record as $val) {
        //    if (!array_key_exists($val['type'], $map_time)) {
        //        $map_time[$val['type']] = $val['create_time'];
        //    }
        //}

        $complete_time = $accessory['complete_time'];
        //if ($is_giveup_return > AccessoryService::RETURN_ACCESSORY_PASS) {
        //    if (isset($map_time[self::OPERATE_TYPE_WORKER_TAKE])) {
        //        $complete_time = $map_time[self::OPERATE_TYPE_WORKER_TAKE];
        //    } elseif (isset($map_time[self::OPERATE_TYPE_SYSTEM_DEFAULT_COMPLETED])) {
        //        $complete_time = $map_time[self::OPERATE_TYPE_SYSTEM_DEFAULT_COMPLETED];
        //    }
        //} else {
        //    if (isset($map_time[self::OPERATE_TYPE_FACTORY_CONFIRM_SEND_BACK])) {
        //        $complete_time = $map_time[self::OPERATE_TYPE_FACTORY_CONFIRM_SEND_BACK];
        //    } elseif (isset($map_time[self::OPERATE_TYPE_SYSTEM_DEFAULT_COMPLETED])) {
        //        $complete_time = $map_time[self::OPERATE_TYPE_SYSTEM_DEFAULT_COMPLETED];
        //    }
        //}
        //
        $stop_time = $accessory['stop_time'];
        //if (AccessoryService::CANCEL_STATUS_ADMIN_STOP == $cancel_status) {
        //    $stop_time = $map_time[self::OPERATE_TYPE_CS_STOP_APPLY]?? null;
        //} elseif (AccessoryService::CANCEL_STATUS_FACTORY_STOP == $cancel_status) {
        //    $stop_time = $map_time[self::OPERATE_TYPE_FACTORY_STOP_APPLY]?? null;
        //}

        $cs_checked = $accessory['admin_check_time'];
        $factory_checked = $accessory['factory_check_time'];
        $factory_confirm_send = $accessory['factory_send_time'];
        $worker_take = $accessory['worker_receive_time'];
        $worker_send_back = $accessory['worker_return_time'];
        $factory_confirm_send_back = $accessory['factory_confirm_receive_time'];

        $schedule = null;
        if (AccessoryService::STATUS_FACTORY_CHECKED <= $accessory_status) {
            $schedule = [
                AccessoryService::STATUS_ADMIN_CHECKED   => ['str' => '客服审核通过', 'is_arrivals' => 0, 'time' => $cs_checked],
                AccessoryService::STATUS_FACTORY_CHECKED => ['str' => '厂家审核通过', 'is_arrivals' => 0, 'time' => $factory_checked],
                AccessoryService::STATUS_FACTORY_SENT    => ['str' => '厂家已发件', 'is_arrivals' => 0, 'time' => $factory_confirm_send],
                AccessoryService::STATUS_WORKER_TAKE     => ['str' => '技工已确认收件', 'is_arrivals' => 0, 'time' => $worker_take],
            ];


            if (AccessoryService::RETURN_ACCESSORY_PASS == $is_giveup_return) {
                $schedule[AccessoryService::STATUS_WORKER_SEND_BACK] = ['str' => '技工已返旧件', 'is_arrivals' => 0, 'time' => $worker_send_back];
                $schedule[AccessoryService::STATUS_COMPLETE] = ['str' => '厂家已确认返件', 'is_arrivals' => 0, 'time' => $factory_confirm_send_back];

            }

            foreach ($schedule as $key => $val) {
                if ($key <= $accessory_status) {
                    $val['is_arrivals'] = 1;
                }
                $schedule[$key] = $val;
            }

            $schedule = array_values($schedule);
        }

        $this->schedule = [
            'stop'     => $stop_time,
            'complete' => $complete_time,
            'schedule' => $schedule,
        ];

        $this->record = $record;
    }

    protected function getFactories($factory_ids)
    {
        if (empty($factory_ids)) {
            return [];
        }

        $model = BaseModel::getInstance('factory');
        $where = ['factory_id' => ['in', $factory_ids]];
        $filed = 'factory_id,linkman,group_id';
        $list = $model->getList($where, $filed);

        $data = [];

        foreach ($list as $val) {
            $factory_id = $val['factory_id'];

            $data[$factory_id] = $val;
        }

        return $data;
    }

    protected function getFactoryAdmins($factory_admin_ids)
    {
        if (empty($factory_admin_ids)) {
            return [];
        }

        $model = BaseModel::getInstance('factory_admin');
        $opts = [
            'field' => 'fa.id,fa.nickout',
            'alias' => 'fa',
            'where' => ['fa.id' => ['in', $factory_admin_ids]],
            'join'  => ['left join factory_adtags as fat on fat.id=fa.tags_id'],
        ];
        $list = $model->getList($opts);

        $data = [];

        foreach ($list as $val) {
            $id = $val['id'];

            $data[$id] = $val;
        }

        return $data;

    }

    protected function getAdmins($admin_ids)
    {
        if (empty($admin_ids)) {
            return [];
        }

        $filed = 'id,nickout,user_name';
        $where = ['id' => ['in', $admin_ids]];
        $model = BaseModel::getInstance('admin');
        $list = $model->getList($where, $filed);

        $data = [];

        $role = AuthService::getModel();

        foreach ($list as $val) {
            $admin_id = $val['id'];

            if (AuthService::ROLE_ADMIN == $role) {
                $val['user_name'] = $val['nickout'];
            }
            unset($val['nickout']);

            $data[$admin_id] = $val;
        }

        return $data;
    }

    protected function getWorkers($worker_ids)
    {
        if (empty($worker_ids)) {
            return [];
        }

        $filed = 'worker_id,nickname';
        $where = ['worker_id' => ['in', $worker_ids]];
        $model = BaseModel::getInstance('worker');
        $list = $model->getList($where, $filed);

        $data = [];

        foreach ($list as $val) {
            $worker_id = $val['worker_id'];

            $data[$worker_id] = $val;
        }

        return $data;
    }


    public function getRecord()
    {
        return $this->record;
    }

    public function getSchedule()
    {
        return $this->schedule;
    }

    /**
     * 配件单日志
     *
     * @param int    $accessory_order_id 配件单id
     * @param int    $operate_type       操作类型,参考日志类型
     * @param string $content            内容
     * @param string $remark             备注
     * @param array  $extra              附加项
     *                                   |-operator_id int 操作者用户id
     *                                   |-operator_type int 操作人类型
     *
     * @throws \Exception
     */
    public static function create($accessory_order_id, $operate_type, $content, $remark, $extra = [])
    {
        //检查参数
        if (!in_array($operate_type, self::$operate_type)) {
            throw new \Exception('日志类型错误', ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }

        $user_id = 0;
        if (isset($extra['operator_id'])) {
            $user_id = $extra['operator_id'];
        } else {
            $user_id = AuthService::getAuthModel()->getPrimaryValue();
        }

        $user_type = 0;
        if (isset($extra['operator_type'])) {
            $user_type = $extra['operator_type'];
        } else {
            $role = AuthService::getModel();
            switch ($role) {
                case AuthService::ROLE_ADMIN:
                    $user_type = self::ROLE_CS;
                    break;
                case AuthService::ROLE_FACTORY:
                    $user_type = self::ROLE_FACTORY;
                    break;
                case AuthService::ROLE_FACTORY_ADMIN:
                    $user_type = self::ROLE_FACTORY_ADMIN;
                    break;
                case AuthService::ROLE_WORKER:
                    $user_type = self::ROLE_WORKER;
                    break;
                default:
                    $user_type = self::ROLE_SYSTEM; // 系统
            }
        }


        $insert_data = [
            'accessory_order_id' => $accessory_order_id,
            'type'               => $operate_type,
            'user_id'            => $user_id,
            'user_type'          => $user_type,
            'content'            => $content,
            'remark'             => $remark,
            'create_time'        => NOW_TIME,
        ];
        $record_db = BaseModel::getInstance('worker_order_apply_accessory_record');
        $record_db->insert($insert_data);
        // 全部操作都要通知
        $acce_order = BaseModel::getInstance('worker_order_apply_accessory')->getOne($accessory_order_id, 'worker_order_id');
        event(new PushXinYingYanOrderStatusChangeEvent($acce_order['worker_order_id']));
    }


}