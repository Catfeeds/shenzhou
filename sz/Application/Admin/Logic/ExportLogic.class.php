<?php
/**
 * Created by PhpStorm.
 * User: sakura
 * Date: 2017/12/3
 * Time: 11:42
 */

namespace Admin\Logic;


use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Common\Common\Service\AccessoryService;
use Common\Common\Service\AllowanceService;
use Common\Common\Service\ComplaintService;
use Common\Common\Service\CostService;
use Common\Common\Service\FactoryMoneyChangeRecordService;
use Common\Common\Service\OrderContactService;
use Common\Common\Service\OrderOperationRecordService;
use Common\Common\Service\OrderService;
use Common\Common\Service\WorkerOrderAppointRecordService;
use Common\Common\Service\WorkerOrderFeeService;
use Common\Common\Service\WorkerOrderOutWorkerAddFeeService;
use Common\Common\Service\WorkerOrderProductService;
use Common\Common\Service\WorkerQualityService;
use Common\Common\Service\WorkerWithdrawService;
use EasyWeChat\Payment\Order;
use function GuzzleHttp\Psr7\str;
use Library\Common\ExcelExport;
use Library\Common\Util;
use Library\Crypt\Hashids;
use Think\Exception;

class ExportLogic extends BaseLogic
{
    const EXPORT_LIMIT = 10000;

    const LIST_TYPE_WORKER                     = 1;
    const LIST_TYPE_ADMIN                      = 2;
    const LIST_TYPE_FACTORY                    = 3;
    const LIST_TYPE_FACTORY_ADMIN              = 4;
    const LIST_TYPE_WX_USER                    = 5;
    const LIST_TYPE_ORDER_USER_INFO            = 6;
    const LIST_TYPE_ORDER_PRODUCT              = 7;
    const LIST_TYPE_ORDER_FEE                  = 8;
    const LIST_TYPE_ORDER                      = 9;
    const LIST_TYPE_APPOINT_RECORD             = 10;
    const LIST_TYPE_ACCESSORY                  = 11;
    const LIST_TYPE_COST                       = 12;
    const LIST_TYPE_ALLOWANCE                  = 13;
    const LIST_TYPE_STATS                      = 14;
    const LIST_TYPE_FROZEN_RECORD              = 15;
    const LIST_TYPE_AREA                       = 16;
    const LIST_WITHDRAW_EXCEL                  = 17;
    const LIST_MASTER_CODE                     = 18;
    const LIST_PRODUCT_CATEGORY                = 19;
    const LIST_TYPE_ORDER_PRODUCT_JOIN_CM_LIST = 20;
    const LIST_TYPE_ORDER_FIRST_DISTRIBUTE     = 21;
    const LIST_TYPE_ORDER_OUT_WORKER_ADD_FEE   = 22;

    protected $param = [];

    protected function flush()
    {
        $this->param = [];
    }

    protected function setList($key, $list, $field)
    {
        if (is_string($field)) {
            $field = explode(',', $field);
        }
        $this->param[$key] = [
            'list'  => $list,
            'field' => $field,
        ];
    }

    protected function getListInfo($param_key, $list_key)
    {
        if (!array_key_exists($param_key, $this->param)) {
            return false;
        }

        if (array_key_exists($list_key, $this->param[$param_key]['list'])) {
            return $this->param[$param_key]['list'][$list_key];
        } else {
            $field = $this->param[$param_key]['field'];
            $return_data = [];
            foreach ($field as $val) {
                $return_data[$val] = '';
            }

            return $return_data;
        }
    }

    protected function getListData($param_key, $list_key)
    {
        if (!array_key_exists($param_key, $this->param)) {
            return [];
        }

        if (array_key_exists($list_key, $this->param[$param_key]['list'])) {
            return $this->param[$param_key]['list'][$list_key];
        } else {
            return [];
        }

    }

    public function exportEmptyValue($text)
    {
        $str = $text;
        preg_replace('#\s#', '', $str);
        if (0 == strlen($str)) {
            return '——';
        }

        return $text;
    }

    protected function collectWorker($worker_ids)
    {
        $field = 'worker_id,worker_telephone,nickname,name,phone,bank_city';
        $worker = [];
        if (!empty($worker_ids)) {
            $model = BaseModel::getInstance('worker');
            $where = ['worker_id' => ['in', $worker_ids]];
            $db_field = 'worker_id,worker_telephone,nickname,bank_city';
            $worker_list = $model->getList($where, $db_field);

            foreach ($worker_list as $worker_info) {
                $worker_id = $worker_info['worker_id'];

                $worker_info['name'] = $worker_info['nickname'];
                $worker_info['phone'] = $worker_info['worker_telephone'];

                $worker[$worker_id] = $worker_info;
            }

        }
        $this->setList(self::LIST_TYPE_WORKER, $worker, $field);
    }

    protected function collectAdmin($admin_ids)
    {
        $field = 'nickout,id,tell,name,phone,user_name';
        $admin = [];
        if (!empty($admin_ids)) {
            $model = BaseModel::getInstance('admin');
            $where = ['id' => ['in', $admin_ids]];
            $db_field = 'nickout,id,tell,user_name';
            $admin_list = $model->getList($where, $db_field);

            foreach ($admin_list as $admin_info) {
                $admin_id = $admin_info['id'];

                $admin_info['name'] = $admin_info['nickout'];
                $admin_info['phone'] = $admin_info['tell'];

                $admin[$admin_id] = $admin_info;
            }
        }
        $this->setList(self::LIST_TYPE_ADMIN, $admin, $field);

    }

    protected function collectFactory()
    {
        $field = 'factory_id,factory_full_name,linkman,linkphone,name,phone';
        $db_field = 'factory_id,factory_full_name,linkman,linkphone';
        $model = BaseModel::getInstance('factory');
        $factory_list = $model->getList([], $db_field);

        $factory = [];
        foreach ($factory_list as $factory_info) {
            $factory_id = $factory_info['factory_id'];

            $factory_info['name'] = $factory_info['linkman'];
            $factory_info['phone'] = $factory_info['linkphone'];

            $factory[$factory_id] = $factory_info;
        }
        $this->setList(self::LIST_TYPE_FACTORY, $factory, $field);
    }

    protected function collectFactoryAdmin($factory_admin_ids)
    {
        $field = 'nickout,id,factory_id,tell';
        $factory_admin = [];
        if (!empty($factory_admin_ids)) {
            $model = BaseModel::getInstance('factory_admin');
            $where = ['id' => ['in', $factory_admin_ids]];
            $factory_admin_list = $model->getList($where, $field);

            foreach ($factory_admin_list as $factory_admin_info) {
                $factory_admin_id = $factory_admin_info['id'];

                $factory_admin_info['name'] = $factory_admin_info['nickout'];
                $factory_admin_info['phone'] = $factory_admin_info['tell'];

                $factory_admin[$factory_admin_id] = $factory_admin_info;
            }
        }
        $this->setList(self::LIST_TYPE_FACTORY_ADMIN, $factory_admin, $field);
    }

    protected function collectOutWorkerAddFee($worker_order_ids)
    {
        $field = 'worker_order_id,pay_type';
        $data = [];
        if (!empty($worker_order_ids)) {
            $model = BaseModel::getInstance('worker_order_out_worker_add_fee');
            $where = ['worker_order_id' => ['in', $worker_order_ids]];
            $list = $model->getList($where, $field);

            foreach ($list as $info) {
                $worker_order_id = $info['worker_order_id'];

                $factory_admin[$worker_order_id][] = $info;
            }
        }
        $this->setList(self::LIST_TYPE_ORDER_OUT_WORKER_ADD_FEE, $data, $field);
    }

    protected function collectWxUser($user_ids)
    {
        $field = 'telephone,id,nickname,name,phone';
        $wx_user = [];
        if (!empty($user_ids)) {
            $model = BaseModel::getInstance('wx_user');
            $where = ['id' => ['in', $user_ids]];
            $db_field = 'telephone,id,nickname';
            $wx_user_list = $model->getList($where, $db_field);

            foreach ($wx_user_list as $user_info) {
                $user_id = $user_info['id'];

                $user_info['name'] = $user_info['nickname'];
                $user_info['phone'] = $user_info['telephone'];

                $wx_user[$user_id] = $user_info;
            }
        }
        $this->setList(self::LIST_TYPE_WX_USER, $wx_user, $field);
    }

    protected function collectOrderUserInfo($worker_order_ids)
    {
        $user_info_list = [];
        $field = 'worker_order_id,province,city,district,real_name,phone';
        if (!empty($worker_order_ids)) {
            $model = BaseModel::getInstance('worker_order_user_info');
            $db_field = 'worker_order_id,cp_area_names,real_name,phone';
            $where = ['worker_order_id' => ['in', $worker_order_ids]];
            $user_info = $model->getList($where, $db_field);

            foreach ($user_info as $info) {
                $worker_order_id = $info['worker_order_id'];
                $cp_area_names = $info['cp_area_names'];

                $area_name = explode('-', $cp_area_names);
                $info['province'] = $area_name[0] ?? '';
                $info['city'] = $area_name[1] ?? '';
                $info['district'] = $area_name[2] ?? '';

                $user_info_list[$worker_order_id] = $info;
            }
        }
        $this->setList(self::LIST_TYPE_ORDER_USER_INFO, $user_info_list, $field);
    }

    protected function collectOrderProduct($worker_order_ids)
    {
        $product_list = [];
        $field = 'worker_order_id,cp_category_name,cp_product_brand_name,cp_product_standard_name,cp_product_mode,cp_fault_name,factory_repair_fee,factory_repair_fee_modify,worker_repair_reason,service_fee_modify,service_reason,factory_repair_reason,worker_report_remark,is_complete,worker_repair_fee_modify,worker_repair_reason,user_service_request,product_nums';
        if (!empty($worker_order_ids)) {
            $model = BaseModel::getInstance('worker_order_product');
            $opts = [
                'where' => ['worker_order_id' => ['in', $worker_order_ids]],
                'field' => $field,
            ];
            $products = $model->getList($opts);

            foreach ($products as $product) {
                $worker_order_id = $product['worker_order_id'];

                $product_list[$worker_order_id][] = $product;
            }
        }
        $this->setList(self::LIST_TYPE_ORDER_PRODUCT, $product_list, $field);
    }

    protected function collectOrderProductJoinCmList($worker_order_ids)
    {
        $product_list = [];
        $field = 'worker_order_id,cp_category_name,cp_product_brand_name,cp_product_standard_name,cp_product_mode,cp_fault_name,factory_repair_fee,factory_repair_fee_modify,worker_repair_reason,service_fee_modify,service_reason,factory_repair_reason,worker_report_remark,is_complete,worker_repair_fee_modify,worker_repair_reason,product_category_id,item_parent';
        if (!empty($worker_order_ids)) {
            $model = BaseModel::getInstance('worker_order_product');
            $opts = [
                'alias' => 'wop',
                'where' => ['worker_order_id' => ['in', $worker_order_ids]],
                'field' => $field,
                'join'  => 'left join cm_list_item as cli on cli.list_item_id=wop.product_category_id',
            ];
            $products = $model->getList($opts);

            foreach ($products as $product) {
                $worker_order_id = $product['worker_order_id'];

                $product_list[$worker_order_id][] = $product;
            }
        }
        $this->setList(self::LIST_TYPE_ORDER_PRODUCT_JOIN_CM_LIST, $product_list, $field);
    }

    protected function collectCategories()
    {
        $field = 'list_item_id, item_desc';
        $where = ['list_id' => 12];

        $list = BaseModel::getInstance('cm_list_item')->getList($where, $field);

        $data = [];
        foreach ($list as $val) {
            $id = $val['list_item_id'];

            $data[$id] = $val;
        }

        $this->setList(self::LIST_PRODUCT_CATEGORY, $data, $field);
    }

    protected function collectOrderFee($worker_order_ids)
    {
        $order_fee = [];
        $field = 'worker_order_id,homefee_mode,factory_appoint_fee_modify,factory_total_fee_modify,worker_total_fee_modify,worker_net_receipts,service_fee_modify';
        if (!empty($worker_order_ids)) {
            $model = BaseModel::getInstance('worker_order_fee');
            $where = ['worker_order_id' => ['in', $worker_order_ids]];
            $fees = $model->getList($where, $field);

            foreach ($fees as $fee) {
                $worker_order_id = $fee['worker_order_id'];

                $order_fee[$worker_order_id] = $fee;
            }
        }
        $this->setList(self::LIST_TYPE_ORDER_FEE, $order_fee, $field);
    }

    protected function collectOrder($worker_order_ids)
    {
        $order_list = [];
        $field = 'orno,id,factory_id,worker_order_status,cancel_status,origin_type,add_id,service_type';
        if (!empty($worker_order_ids)) {
            $model = BaseModel::getInstance('worker_order');
            $where = ['id' => ['in', $worker_order_ids]];
            $orders = $model->getList($where, $field);

            foreach ($orders as $order) {
                $worker_order_id = $order['id'];

                $order_list[$worker_order_id] = $order;
            }
        }
        $this->setList(self::LIST_TYPE_ORDER, $order_list, $field);
    }

    protected function collectAppointRecord($worker_order_ids)
    {
        $records = [];
        $field = 'worker_order_id,factory_appoint_fee_modify,worker_appoint_fee_modify,worker_appoint_reason,factory_appoint_reason';
        if (!empty($worker_order_ids)) {
            $model = BaseModel::getInstance('worker_order_appoint_record');
            $where = ['worker_order_id' => ['in', $worker_order_ids], 'is_over' => WorkerOrderAppointRecordService::IS_OVER_YES];
            $appoints = $model->getList($where, $field);

            foreach ($appoints as $appoint) {
                $worker_order_id = $appoint['worker_order_id'];

                $records[$worker_order_id][] = $appoint;
            }
        }
        $this->setList(self::LIST_TYPE_APPOINT_RECORD, $records, $field);
    }

    protected function collectFirstDistribute($worker_order_ids)
    {
        $data = [];
        $field = 'worker_order_id,create_time';
        if (!empty($worker_order_ids)) {
            $model = BaseModel::getInstance('worker_order_operation_record');
            $where = [
                'worker_order_id' => ['in', $worker_order_ids],
                'operation_type'  => OrderOperationRecordService::CS_DISTRIBUTOR_DISTRIBUTE,
            ];
            $opts = [
                'field' => 'worker_order_id,min(create_time) as create_time',
                'where' => $where,
                'group' => 'worker_order_id',
            ];
            $list = $model->getList($opts);

            foreach ($list as $val) {
                $worker_order_id = $val['worker_order_id'];

                $data[$worker_order_id] = $val;
            }
        }
        $this->setList(self::LIST_TYPE_ORDER_FIRST_DISTRIBUTE, $data, $field);
    }

    protected function collectCost($worker_order_ids)
    {
        $cost = [];
        $field = 'worker_order_id,fee,type';
        if (!empty($worker_order_ids)) {
            $model = BaseModel::getInstance('worker_order_apply_cost');
            $opts = [
                'field' => $field,
                'where' => [
                    'worker_order_id' => ['in', $worker_order_ids],
                    'status'          => CostService::STATUS_FACTORY_PASS,
                ],
                'order' => 'id',
            ];
            $cost_list = $model->getList($opts);

            foreach ($cost_list as $cost_info) {
                $worker_order_id = $cost_info['worker_order_id'];

                $cost[$worker_order_id][] = $cost_info;
            }
        }
        $this->setList(self::LIST_TYPE_COST, $cost, $field);
    }

    protected function collectAccessory($worker_order_ids)
    {
        $accessory = [];
        $field = 'worker_order_id,worker_transport_fee,is_giveup_return,accessory_status';
        if (!empty($worker_order_ids)) {
            $model = BaseModel::getInstance('worker_order_apply_accessory');
            $where = ['worker_order_id' => ['in', $worker_order_ids], 'accessory_status' => ['in', [AccessoryService::STATUS_FACTORY_CHECKED, AccessoryService::STATUS_FACTORY_SENT, AccessoryService::STATUS_WORKER_TAKE, AccessoryService::STATUS_WORKER_SEND_BACK, AccessoryService::STATUS_COMPLETE]], 'cancel_status' => AccessoryService::CANCEL_STATUS_NORMAL];
            $accessory_list = $model->getList($where, $field);

            foreach ($accessory_list as $accessory_info) {
                $worker_order_id = $accessory_info['worker_order_id'];

                $accessory[$worker_order_id][] = $accessory_info;
            }
        }
        $this->setList(self::LIST_TYPE_ACCESSORY, $accessory, $field);
    }

    protected function collectAllowance($worker_order_ids)
    {
        $allow = [];
        $field = 'worker_order_id,type,apply_fee_modify';
        if (!empty($worker_order_ids)) {
            $model = BaseModel::getInstance('worker_order_apply_allowance');
            $where = ['worker_order_id' => ['in', $worker_order_ids], 'status' => AllowanceService::STATUS_PASS];
            $allow_list = $model->getList($where, $field);

            foreach ($allow_list as $allow_info) {
                $worker_order_id = $allow_info['worker_order_id'];

                $allow[$worker_order_id][] = $allow_info;
            }
        }
        $this->setList(self::LIST_TYPE_ALLOWANCE, $allow, $field);
    }

    protected function collectFrozenRecord($worker_order_ids, $type)
    {
        $list = [];
        $field = 'worker_order_id,frozen_money';
        if (!empty($worker_order_ids)) {
            $model = BaseModel::getInstance('factory_money_frozen');
            $where = ['worker_order_id' => ['in', $worker_order_ids], 'type' => $type];
            $data = $model->getList($where, $field);

            foreach ($data as $info) {
                $worker_order_id = $info['worker_order_id'];

                $list[$worker_order_id] = $info;
            }
        }
        $this->setList(self::LIST_TYPE_FROZEN_RECORD, $list, $field);
    }

    protected function collectArea($area_ids)
    {
        $list = [];
        $field = 'id,name';
        if (!empty($area_ids)) {
            $model = BaseModel::getInstance('area');
            $where = ['id' => ['in', $area_ids]];
            $data = $model->getList($where, $field);

            foreach ($data as $info) {
                $id = $info['id'];

                $list[$id] = $info;
            }
        }
        $this->setList(self::LIST_TYPE_AREA, $list, $field);
    }

    protected function collectExcelRecord($withdrawcash_excel_ids)
    {
        $list = [];
        $field = 'id,admin_id';
        if (!empty($withdrawcash_excel_ids)) {
            $admin_ids = [];
            $model = BaseModel::getInstance('worker_withdrawcash_excel');
            $where = ['id' => ['in', $withdrawcash_excel_ids]];
            $data = $model->getList($where, $field);

            foreach ($data as $info) {
                $id = $info['id'];
                $admin_id = $info['admin_id'];

                $admin_ids[] = $admin_id;

                $list[$id] = $info;
            }

            $this->collectAdmin($admin_ids);

        }
        $this->setList(self::LIST_WITHDRAW_EXCEL, $list, $field);

    }

    /**
     * 投诉单
     *
     * @param array $opts       筛选项
     *                          |-where 筛选项
     *                          |-join 连表
     *                          |-alias 表别名
     */
    public function adminComplaint($opts)
    {
        $where = $opts['where'] ?? null;
        $join = $opts['join'] ?? null;
        $alias = $opts['alias'] ?? null;
        $alias = trim($alias);
        $query_alias = strlen($alias) > 0 ? $alias . '.' : '';

        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $file_name = date('投诉单导出-(y年m月d日H时i分s秒)');
        $tpl_path = 'Public/投诉单导出模板.xls';
        $cached_path = $this->getTempFilePath($tpl_path, $file_name);
        $path_info = $this->getFileInfo($cached_path);
        $dir_path = $path_info['dirname'];
        $file_name = $path_info['filename'];
        $ext = $path_info['extension'];

        $export_obj = new ExcelExport();
        $export_obj->setSheet(0)->setTplPath($cached_path)
            ->setStartPlace('A', 2);
        $last_id = PHP_INT_MAX;
        $this->collectFactory();

        $model = BaseModel::getInstance('worker_order_complaint');

        $field = 'id,worker_order_id,create_time,complaint_from_id,complaint_from_type,complaint_to_id,complaint_to_type,content,reply_result,reply_time,response_type,response_type_id,verify_time,replier_id,contact_name,contact_tell';
        if (!empty($alias)) {
            $field = preg_replace('#(?=\b[a-zA-Z])#', $alias . '.', $field);
        }

        $where[$query_alias . 'id'][] = ['lt', &$last_id];
        $order_by = $query_alias . 'id desc';
        do {

            $export_opts = [
                'field' => $field,
                'where' => $where,
                'join'  => $join,
                'alias' => $alias,
                'order' => $order_by,
                'limit' => self::EXPORT_LIMIT,
            ];
            $complaints = $model->getList($export_opts);

            if (empty($complaints)) {
                break;
            }

            $admin_ids = [];
            $worker_ids = [];
            $wx_user_ids = [];
            $factory_admin_ids = [];
            $worker_order_ids = [];

            //收集id
            foreach ($complaints as $complaint) {
                $complaint_from_type = $complaint['complaint_from_type'];
                $complaint_from_id = $complaint['complaint_from_id'];
                $complaint_to_type = $complaint['complaint_to_type'];
                $complaint_to_id = $complaint['complaint_to_id'];
                $worker_order_id = $complaint['worker_order_id'];
                $replier_id = $complaint['replier_id'];
                $response_type = $complaint['response_type'];
                $response_type_id = $complaint['response_type_id'];

                $admin_ids[] = $replier_id;
                //投诉方
                if (ComplaintService::FROM_TYPE_CS == $complaint_from_type) {
                    $admin_ids[] = $complaint_from_id;
                } elseif (ComplaintService::FROM_TYPE_FACTORY_ADMIN == $complaint_from_type) {
                    $factory_admin_ids[] = $complaint_from_id;
                } elseif (ComplaintService::FROM_TYPE_WORKER == $complaint_from_type) {
                    $worker_ids[] = $complaint_from_id;
                } elseif (ComplaintService::FROM_TYPE_WX_USER == $complaint_from_type) {
                    $wx_user_ids[] = $complaint_from_id;
                }

                //被投诉
                if (ComplaintService::TO_TYPE_CS == $complaint_to_type) {
                    $admin_ids[] = $complaint_to_id;
                } elseif (ComplaintService::TO_TYPE_FACTORY_ADMIN == $complaint_to_type) {
                    $factory_admin_ids[] = $complaint_to_id;
                } elseif (ComplaintService::TO_TYPE_WORKER == $complaint_to_type) {
                    $worker_ids[] = $complaint_to_id;
                } elseif (ComplaintService::TO_TYPE_WX_USER == $complaint_to_type) {
                    $wx_user_ids[] = $complaint_to_id;
                }

                //核实
                if (ComplaintService::RESPONSE_TYPE_CS == $response_type) {
                    $admin_ids[] = $response_type_id;
                } elseif (ComplaintService::RESPONSE_TYPE_FACTORY_ADMIN == $response_type) {
                    $factory_admin_ids[] = $response_type_id;
                } elseif (ComplaintService::RESPONSE_TYPE_WORKER == $response_type) {
                    $worker_ids[] = $response_type_id;
                } elseif (ComplaintService::RESPONSE_TYPE_WX_USER == $response_type) {
                    $wx_user_ids[] = $response_type_id;
                }

                $worker_order_ids[] = $worker_order_id;

            }
            $this->collectFactory();
            $this->collectAdmin($admin_ids);
            $this->collectWorker($worker_ids);
            $this->collectWxUser($wx_user_ids);
            $this->collectFactoryAdmin($factory_admin_ids);
            $this->collectOrder($worker_order_ids);

            foreach ($complaints as $complaint) {
                $worker_order_id = $complaint['worker_order_id'];
                $create_time = $complaint['create_time'];
                $complaint_from_type = $complaint['complaint_from_type'];
                $complaint_from_id = $complaint['complaint_from_id'];
                $complaint_to_type = $complaint['complaint_to_type'];
                $complaint_to_id = $complaint['complaint_to_id'];
                $content = $complaint['content'];
                $reply_result = $complaint['reply_result'];
                $reply_time = $complaint['reply_time'];
                $response_type = $complaint['response_type'];
                $response_type_id = $complaint['response_type_id'];
                $verify_time = $complaint['verify_time'];
                $replier_id = $complaint['replier_id'];
                $contact_name = $complaint['contact_name'];
                $contact_tell = $complaint['contact_tell'];

                $order_info = $this->getListInfo(self::LIST_TYPE_ORDER, $worker_order_id);
                $orno = $order_info['orno'];
                $factory_id = $order_info['factory_id'];

                $factory_info = $this->getListInfo(self::LIST_TYPE_FACTORY, $factory_id);
                $factory_name = $factory_info['factory_full_name'];

                $create_time_str = $create_time > 0 ? date('Y.m.d H:i', $create_time) : '';
                $reply_time_str = $reply_time > 0 ? date('Y.m.d H:i', $reply_time) : '';
                $verify_time_str = $verify_time > 0 ? date('Y.m.d H:i', $verify_time) : '';

                //投诉发起人
                $from_user_name = '';
                $from_phone = '';
                if (ComplaintService::FROM_TYPE_CS == $complaint_from_type) {
                    $info = $this->getListInfo(self::LIST_TYPE_ADMIN, $complaint_from_id);
                    $from_user_name = $info['name'];
                    $from_phone = $info['phone'];
                } elseif (ComplaintService::FROM_TYPE_FACTORY == $complaint_from_type) {
                    $info = $this->getListInfo(self::LIST_TYPE_FACTORY, $complaint_from_id);
                    $from_user_name = $info['name'];
                    $from_phone = $info['phone'];
                } elseif (ComplaintService::FROM_TYPE_FACTORY_ADMIN == $complaint_from_type) {
                    $info = $this->getListInfo(self::LIST_TYPE_FACTORY_ADMIN, $complaint_from_id);
                    $from_user_name = $info['name'];
                    $from_phone = $info['phone'];
                } elseif (ComplaintService::FROM_TYPE_WORKER == $complaint_from_type) {
                    $info = $this->getListInfo(self::LIST_TYPE_WORKER, $complaint_from_id);
                    $from_user_name = $info['name'];
                    $from_phone = $info['phone'];
                } elseif (ComplaintService::FROM_TYPE_WX_USER == $complaint_from_type) {
                    $info = $this->getListInfo(self::LIST_TYPE_WX_USER, $complaint_from_id);
                    $from_user_name = $info['name'];
                    $from_phone = $info['phone'];
                }

                //被投诉对象
                $to_user_name = '';
                $to_phone = '';
                if (ComplaintService::TO_TYPE_CS == $complaint_to_type) {
                    $info = $this->getListInfo(self::LIST_TYPE_ADMIN, $complaint_to_id);
                    $to_user_name = $info['name'];
                    $to_phone = $info['phone'];
                } elseif (ComplaintService::TO_TYPE_FACTORY == $complaint_to_type) {
                    $info = $this->getListInfo(self::LIST_TYPE_FACTORY, $complaint_to_id);
                    $to_user_name = $info['name'];
                    $to_phone = $info['phone'];
                } elseif (ComplaintService::TO_TYPE_FACTORY_ADMIN == $complaint_to_type) {
                    $info = $this->getListInfo(self::LIST_TYPE_FACTORY_ADMIN, $complaint_to_id);
                    $to_user_name = $info['name'];
                    $to_phone = $info['phone'];
                } elseif (ComplaintService::TO_TYPE_WORKER == $complaint_to_type) {
                    $info = $this->getListInfo(self::LIST_TYPE_WORKER, $complaint_to_id);
                    $to_user_name = $info['name'];
                    $to_phone = $info['phone'];
                } elseif (ComplaintService::TO_TYPE_WX_USER == $complaint_to_type) {
                    $info = $this->getListInfo(self::LIST_TYPE_WX_USER, $complaint_to_id);
                    $to_user_name = $info['name'];
                    $to_phone = $info['phone'];
                }

                //处理客服
                $replier = $this->getListInfo(self::LIST_TYPE_ADMIN, $replier_id);
                $replier_name = $replier['name'];

                $response_user_name = '';
                $response_phone = '';
                if (ComplaintService::RESPONSE_TYPE_CS == $response_type) {
                    $info = $this->getListInfo(self::LIST_TYPE_ADMIN, $response_type_id);
                    $response_user_name = $info['name'];
                    $response_phone = $info['phone'];
                } elseif (ComplaintService::RESPONSE_TYPE_FACTORY == $response_type) {
                    $info = $this->getListInfo(self::LIST_TYPE_FACTORY, $response_type_id);
                    $response_user_name = $info['name'];
                    $response_phone = $info['phone'];
                } elseif (ComplaintService::RESPONSE_TYPE_FACTORY_ADMIN == $response_type) {
                    $info = $this->getListInfo(self::LIST_TYPE_FACTORY_ADMIN, $response_type_id);
                    $response_user_name = $info['name'];
                    $response_phone = $info['phone'];
                } elseif (ComplaintService::RESPONSE_TYPE_WORKER == $response_type) {
                    $info = $this->getListInfo(self::LIST_TYPE_WORKER, $response_type_id);
                    $response_user_name = $info['name'];
                    $response_phone = $info['phone'];
                } elseif (ComplaintService::RESPONSE_TYPE_WX_USER == $response_type) {
                    $info = $this->getListInfo(self::LIST_TYPE_WX_USER, $response_type_id);
                    $response_user_name = $info['name'];
                    $response_phone = $info['phone'];
                }

                $to_user = $to_user_name . ' ' . $to_phone;
                $response = $response_user_name . ' ' . $response_phone;
                $complaint_to_type_str = $complaint_to_id > 0 ? ComplaintService::getComplaintToTypeStr($complaint_to_type) : '';
                $to_user = $complaint_to_id > 0 ? $to_user : '';
                $response_type_str = $response_type_id > 0 ? ComplaintService::getComplaintResponseTypeStr($response_type) : '';
                $response = $response_type_id > 0 ? $response : '';

                $export_obj->setRowData([$orno, $factory_name, $create_time_str, $contact_name, $contact_tell, $complaint_to_type_str, $to_user, $content, $replier_name, $reply_result, $reply_time_str, $response_type_str, $response, $verify_time_str], [$this, 'exportEmptyValue']);
            }

            $export_obj->saveFile($dir_path, $file_name, $ext);

            $last = end($complaints);
            $last_id = $last['id'];
            $this->flush();
        } while (true);

        self::download($cached_path);
    }

    /**
     * 厂家充值记录
     *
     * @param array $opts       筛选项
     *                          |-where 筛选项
     *                          |-join 连表
     *                          |-alias 表别名
     */
    public function adminFactoryRecharge($opts)
    {
        $where = $opts['where'] ?? null;
        $join = $opts['join'] ?? null;
        $alias = $opts['alias'] ?? null;
        $alias = trim($alias);
        $query_alias = strlen($alias) > 0 ? $alias . '.' : '';

        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $file_name = date('厂家充值记录导出-(y年m月d日H时i分s秒)');
        $tpl_path = 'Public/厂家充值记录导出模板.xls';
        $cached_path = $this->getTempFilePath($tpl_path, $file_name);
        $path_info = $this->getFileInfo($cached_path);
        $dir_path = $path_info['dirname'];
        $file_name = $path_info['filename'];
        $ext = $path_info['extension'];

        $export_obj = new ExcelExport();
        $export_obj->setSheet(0)->setTplPath($cached_path)
            ->setStartPlace('A', 2);
        $last_id = PHP_INT_MAX;
        $this->collectFactory();

        $model = BaseModel::getInstance('factory_money_change_record');
        $field = 'id,factory_id,operator_id,operator_type,operation_remark,change_type,money,change_money,last_money,create_time';
        if (!empty($alias)) {
            $field = preg_replace('#(?=\b[a-zA-Z])#', $query_alias, $field);
        }

        $where[$query_alias . 'id'][] = ['lt', &$last_id];
        $order_by = $query_alias . 'id desc';
        do {
            $export_opts = [
                'field' => $field,
                'where' => $where,
                'join'  => $join,
                'alias' => $alias,
                'order' => $order_by,
                'limit' => self::EXPORT_LIMIT,
            ];
            $records = $model->getList($export_opts);
            if (empty($records)) {
                break;
            }

            $admin_ids = [];
            $factory_admin_ids = [];

            foreach ($records as $record) {
                $operator_id = $record['operator_id'];
                $operator_type = $record['operator_type'];

                if (FactoryMoneyChangeRecordService::OPERATOR_TYPE_ADMIN == $operator_type) {
                    $admin_ids[] = $operator_id;
                } elseif (FactoryMoneyChangeRecordService::OPERATOR_TYPE_FACTORY_ADMIN == $operator_type) {
                    $factory_admin_ids[] = $operator_id;
                }
            }

            $this->collectAdmin($admin_ids);
            $this->collectFactoryAdmin($factory_admin_ids);

            foreach ($records as $record) {
                $factory_id = $record['factory_id'];
                $operator_id = $record['operator_id'];
                $operator_type = $record['operator_type'];
                $operation_remark = $record['operation_remark'];
                $change_type = $record['change_type'];
                $money = $record['money'];
                $change_money = $record['change_money'];
                $last_money = $record['last_money'];
                $create_time = $record['create_time'];

                $factory_info = $this->getListInfo(self::LIST_TYPE_FACTORY, $factory_id);

                $factory_name = $factory_info['factory_full_name'];

                $operator = '';
                if (FactoryMoneyChangeRecordService::OPERATOR_TYPE_FACTORY == $operator_type) {
                    $info = $this->getListInfo(self::LIST_TYPE_FACTORY, $operator_id);
                    $operator = $info['name'];
                } elseif (FactoryMoneyChangeRecordService::OPERATOR_TYPE_ADMIN == $operator_type) {
                    $info = $this->getListInfo(self::LIST_TYPE_ADMIN, $operator_id);
                    $operator = $info['name'];
                } elseif (FactoryMoneyChangeRecordService::OPERATOR_TYPE_FACTORY_ADMIN == $operator_type) {
                    $info = $this->getListInfo(self::LIST_TYPE_FACTORY_ADMIN, $operator_id);
                    $operator = $info['name'];
                } elseif (FactoryMoneyChangeRecordService::OPERATOR_TYPE_SYSTEM == $operator_type) {
                    $operator = '系统主动调整';
                }

                $change_type_str = FactoryMoneyChangeRecordService::getChangeTypeStr($change_type);

                $create_time_str = $create_time > 0 ? date('Y.m.d H:i', $create_time) : '';

                $export_obj->setRowData([$create_time_str, $factory_name, $change_money, $money, $last_money, $change_type_str, $operator, $operation_remark], [$this, 'exportEmptyValue']);

            }
            $export_obj->saveFile($dir_path, $file_name, $ext);

            $last = end($records);
            $last_id = $last['id'];
            $this->flush();
        } while (true);

        self::download($cached_path);
    }

    /**
     * 单厂家充值记录
     *
     * @param array $opts       筛选项
     *                          |-where 筛选项
     *                          |-join 连表
     *                          |-alias 表别名
     * @param int   $factory_id 厂家id
     */
    public function adminFactoryRechargeOne($opts, $factory_id)
    {
        $where = $opts['where'] ?? null;
        $join = $opts['join'] ?? null;
        $alias = $opts['alias'] ?? null;
        $alias = trim($alias);
        $query_alias = strlen($alias) > 0 ? $alias . '.' : '';

        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        //设置标题
        $factory_model = BaseModel::getInstance('factory');
        $factory_field = 'factory_full_name,linkphone,linkman';
        $factory_info = $factory_model->getOneOrFail($factory_id, $factory_field);
        $factory_name = $factory_info['factory_full_name'];
        $linkman = $factory_info['linkman'];
        $linkphone = $factory_info['linkphone'];

        $file_name = date($factory_name . ' 厂家充值记录导出(y年m月d日H时i分s秒)');
        $tpl_path = 'Public/(单个厂家)厂家充值记录导出模板.xls';
        $cached_path = $this->getTempFilePath($tpl_path, $file_name);
        $path_info = $this->getFileInfo($cached_path);
        $dir_path = $path_info['dirname'];
        $file_name = $path_info['filename'];
        $ext = $path_info['extension'];

        $export_obj = new ExcelExport();
        $export_obj->setSheet(0)->setTplPath($cached_path);
        $last_id = PHP_INT_MAX;

        $export_obj->setStartPlace('A', 1);
        $title = "厂家名称：{$factory_name}  厂家账号：{$linkphone}";
        $export_obj->setRowData([$title]);
        $export_obj->saveFile($dir_path, $file_name, $ext);
        $export_obj->setStartPlace('A', 3);

        $model = BaseModel::getInstance('factory_money_change_record');
        $field = 'id,factory_id,operator_id,operator_type,operation_remark,change_type,money,change_money,last_money,create_time';
        if (!empty($alias)) {
            $field = preg_replace('#(?=\b[a-zA-Z])#', $query_alias, $field);
        }

        $where[$query_alias . 'id'] = ['lt', &$last_id];
        $where[$query_alias . 'factory_id'] = $factory_id;
        $order_by = $query_alias . 'id desc';
        do {

            $export_opts = [
                'field' => $field,
                'where' => $where,
                'join'  => $join,
                'alias' => $alias,
                'order' => $order_by,
                'limit' => self::EXPORT_LIMIT,
            ];
            $records = $model->getList($export_opts);
            if (empty($records)) {
                break;
            }

            $admin_ids = [];
            $factory_admin_ids = [];

            foreach ($records as $record) {
                $operator_id = $record['operator_id'];
                $operator_type = $record['operator_type'];

                if (FactoryMoneyChangeRecordService::OPERATOR_TYPE_ADMIN == $operator_type) {
                    $admin_ids[] = $operator_id;
                } elseif (FactoryMoneyChangeRecordService::OPERATOR_TYPE_FACTORY_ADMIN == $operator_type) {
                    $factory_admin_ids[] = $operator_id;
                }
            }

            $this->collectAdmin($admin_ids);
            $this->collectFactoryAdmin($factory_admin_ids);

            foreach ($records as $record) {
                $operator_id = $record['operator_id'];
                $operator_type = $record['operator_type'];
                $operation_remark = $record['operation_remark'];
                $change_type = $record['change_type'];
                $money = $record['money'];
                $change_money = $record['change_money'];
                $last_money = $record['last_money'];
                $create_time = $record['create_time'];

                $operator = '';
                if (FactoryMoneyChangeRecordService::OPERATOR_TYPE_FACTORY == $operator_type) {
                    $operator = $linkman;
                } elseif (FactoryMoneyChangeRecordService::OPERATOR_TYPE_ADMIN == $operator_type) {
                    $info = $this->getListInfo(self::LIST_TYPE_ADMIN, $operator_id);
                    $operator = $info['name'];
                } elseif (FactoryMoneyChangeRecordService::OPERATOR_TYPE_FACTORY_ADMIN == $operator_type) {
                    $info = $this->getListInfo(self::LIST_TYPE_FACTORY_ADMIN, $operator_id);
                    $operator = $info['name'];
                } elseif (FactoryMoneyChangeRecordService::OPERATOR_TYPE_SYSTEM == $operator_type) {
                    $operator = '系统主动调整';
                }

                $change_type_str = FactoryMoneyChangeRecordService::getChangeTypeStr($change_type);

                $create_time_str = $create_time > 0 ? date('Y.m.d H:i', $create_time) : '';

                $export_obj->setRowData([$create_time_str, $change_money, $money, $last_money, $change_type_str, $operator, $operation_remark], [$this, 'exportEmptyValue']);

            }

            $export_obj->saveFile($dir_path, $file_name, $ext);

            $last = end($records);
            $last_id = $last['id'];
            $this->flush();
        } while (true);

        self::download($cached_path);
    }

    /**
     * 补贴单
     *
     * @param array $opts       筛选项
     *                          |-where 筛选项
     *                          |-join 连表
     *                          |-alias 表别名
     */
    public function adminAllowance($opts)
    {
        $where = $opts['where'] ?? null;
        $join = $opts['join'] ?? null;
        $alias = $opts['alias'] ?? null;
        $query_alias = strlen($alias) > 0 ? $alias . '.' : '';

        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $file_name = date("补贴单导出-(Y年m月d日H时i分s秒)");
        $tpl_path = 'Public/补贴单导出模板.xls';
        $cached_path = $this->getTempFilePath($tpl_path, $file_name);
        $path_info = $this->getFileInfo($cached_path);
        $dir_path = $path_info['dirname'];
        $file_name = $path_info['filename'];
        $ext = $path_info['extension'];

        $export_obj = new ExcelExport();
        $export_obj->setSheet(0)->setTplPath($cached_path)
            ->setStartPlace('A', 2);
        $last_id = PHP_INT_MAX;

        $model = BaseModel::getInstance('worker_order_apply_allowance');

        $field = 'id,admin_id,auditor_id,apply_fee,apply_remark,create_time,status,check_time,type,worker_order_id';
        if (!empty($alias)) {
            $field = preg_replace('#(?=\b[a-zA-Z])#', $query_alias, $field);
        }

        $where[$query_alias . 'id'][] = ['lt', &$last_id];
        $order_by = $query_alias . 'id desc';
        do {

            $export_opts = [
                'field' => $field,
                'where' => $where,
                'join'  => $join,
                'alias' => $alias,
                'order' => $order_by,
                'limit' => self::EXPORT_LIMIT,
            ];
            $list = $model->getList($export_opts);
            if (empty($list)) {
                break;
            }

            $admin_ids = [];
            $worker_order_ids = [];

            foreach ($list as $val) {
                $admin_id = $val['admin_id'];
                $auditor_id = $val['auditor_id'];
                $worker_order_id = $val['worker_order_id'];

                $admin_ids[] = $admin_id;
                $admin_ids[] = $auditor_id;
                $worker_order_ids[] = $worker_order_id;
            }

            $this->collectOrder($worker_order_ids);
            $this->collectAdmin($admin_ids);

            foreach ($list as $key => $val) {
                $worker_order_id = $val['worker_order_id'];
                $admin_id = $val['admin_id'];
                $auditor_id = $val['auditor_id'];
                $check_time = $val['check_time'];
                $create_time = $val['create_time'];
                $type = $val['type'];
                $status = $val['status'];
                $apply_fee = $val['apply_fee'];
                $apply_remark = $val['apply_remark'];

                $order = $this->getListInfo(self::LIST_TYPE_ORDER, $worker_order_id);
                $orno = $order['orno'];
                $worker_order_status = $order['worker_order_status'];
                $cancel_status = $order['cancel_status'];

                $admin_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $admin_id);
                $auditor_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $auditor_id);

                $order_status_str = OrderService::getStatusStr($worker_order_status, $cancel_status);
                $status_str = AllowanceService::getStatusStr($status);
                $type_str = AllowanceService::getTypeStr($type);
                $create_time_str = $create_time > 0 ? date('Y-m-d H:i', $create_time) : '';
                $admin_name = $admin_info['name'];
                $auditor_name = $auditor_info['name'];
                $check_time_str = $check_time > 0 ? date('Y-m-d H:i', $check_time) : '';

                $export_obj->setRowData([$orno, $type_str, $apply_fee, $apply_remark, $status_str, $order_status_str, $admin_name, $create_time_str, $auditor_name, $check_time_str], [$this, 'exportEmptyValue']);

            }
            $export_obj->saveFile($dir_path, $file_name, $ext);

            $last = end($list);
            $last_id = $last['id'];
            $this->flush();
        } while (true);

        self::download($cached_path);
    }

    /**
     * 工单
     *
     * @param $opts
     *
     * @throws Exception
     * @throws \Exception
     */
    public function adminOrder($opts)
    {
        $where = $opts['where'] ?? null;
        $join = $opts['join'] ?? null;
        $alias = $opts['alias'] ?? null;
        $query_alias = strlen($alias) > 0 ? $alias . '.' : '';

        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $worker_order_model = BaseModel::getInstance('worker_order');

        $file_name = date("工单数据导出-(Y年m月d日H时i分s秒)");
        $tpl_path = 'Public/工单数据导出模板.xls';
        $cached_path = $this->getTempFilePath($tpl_path, $file_name);
        $path_info = $this->getFileInfo($cached_path);
        $dir_path = $path_info['dirname'];
        $file_name = $path_info['filename'];
        $ext = $path_info['extension'];

        $export_obj = new ExcelExport();
        $export_obj->setSheet(0)->setTplPath($cached_path);
        $last_id = PHP_INT_MAX;
        $this->collectFactory();
        $order_row_no = 3;

        $field = 'id,orno,worker_id,factory_id,checker_id,distributor_id,returnee_id,auditor_id,worker_order_status,service_type,create_time,cancel_status,cancel_remark,origin_type,add_id,create_remark,worker_first_sign_time,return_time,audit_time,factory_audit_time,canceler_id,cancel_type,worker_order_type,check_time,worker_repair_time';
        if (!empty($alias)) {
            $field = preg_replace('#(?=\b[a-zA-Z])#', $alias . '.', $field);
        }

        $settle_show_worker_order_status = [OrderService::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT, OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT, OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED]; // 可以显示费用的工单状态表列表

        $accessory_show_return_status = [
            AccessoryService::STATUS_FACTORY_SENT, AccessoryService::STATUS_WORKER_TAKE,
            AccessoryService::STATUS_WORKER_SEND_BACK, AccessoryService::STATUS_COMPLETE,
        ];

        $where[$query_alias . 'id'] = ['lt', &$last_id];
        $order_by = $query_alias . 'id DESC';
        do {

            $opts = [
                'field' => $field,
                'where' => $where,
                'join'  => $join,
                'alias' => $alias,
                'order' => $order_by,
                'limit' => self::EXPORT_LIMIT,
            ];
            $orders = $worker_order_model->getList($opts);

            if (empty($orders)) {
                break;
            }


            $export_obj->setStartPlace('A', $order_row_no);

            $worker_order_ids = [];
            $worker_ids = [];
            $admin_ids = [];
            $factory_admin_ids = [];
            $wx_user_ids = [];

            $show_result_valid_status = [
                OrderService::STATUS_WORKER_FINISH_SERVICE_AND_NEED_RETURNEE_RECEIVE,
                OrderService::STATUS_RETURNEE_RECEIVED_AND_NEED_RETURNEE_VISIT,
                OrderService::STATUS_RETURNEE_VISITED_NOT_PASS_AND_NEED_WORKER_FINISH_SERVICE,
                OrderService::STATUS_RETURNEE_VISITED_AND_NEED_AUDITOR_RECEIVE,
                OrderService::STATUS_AUDITOR_RECEIVED_AND_NEED_AUDITOR_AUDIT,
                OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_RETURNEE_VISIT,
                OrderService::STATUS_AUDITOR_AUDITED_AND_NEED_FACTORY_AUDIT,
                OrderService::STATUS_AUDITOR_AUDITED_NOT_PASS_AND_NEED_AUDITOR_AUDIT,
                OrderService::STATUS_FACTORY_AUDITED_AND_FINISHED,
            ]; // 允许显示维修结果对应的工单状态

            foreach ($orders as $order) {
                $origin_type = $order['origin_type'];
                $add_id = $order['add_id'];
                $worker_id = $order['worker_id'];
                $distributor_id = $order['distributor_id'];
                $checker_id = $order['checker_id'];
                $returnee_id = $order['returnee_id'];
                $auditor_id = $order['auditor_id'];
                $id = $order['id'];
                $cancel_status = $order['cancel_status'];
                $canceler_id = $order['canceler_id'];

                $worker_order_ids[] = $id;
                $worker_ids[] = $worker_id;
                $admin_ids = array_merge($admin_ids, [$checker_id, $returnee_id, $auditor_id, $distributor_id]);

                if (OrderService::ORIGIN_TYPE_FACTORY_ADMIN == $origin_type) {
                    $factory_admin_ids[] = $add_id;
                } elseif (OrderService::ORIGIN_TYPE_OUTER_USER == $origin_type) {

                } elseif (OrderService::ORIGIN_TYPE_WX_USER == $origin_type) {
                    $wx_user_ids[] = $add_id;
                } elseif (OrderService::ORIGIN_TYPE_WX_DEALER == $origin_type) {
                    $wx_user_ids[] = $add_id;
                }

                if (
                    OrderService::CANCEL_TYPE_CS == $cancel_status ||
                    OrderService::CANCEL_TYPE_CS_STOP == $cancel_status
                ) {
                    $admin_ids[] = $canceler_id;
                } elseif (
                    OrderService::CANCEL_TYPE_WX_USER == $cancel_status ||
                    OrderService::CANCEL_TYPE_WX_DEALER == $cancel_status
                ) {
                    $wx_user_ids[] = $canceler_id;
                } elseif (OrderService::CANCEL_TYPE_FACTORY_ADMIN == $cancel_status) {
                    $factory_admin_ids[] = $canceler_id;
                }
            }

            $admin_ids = array_unique($admin_ids);
            $worker_ids = array_unique($worker_ids);
            $wx_user_ids = array_unique($wx_user_ids);

            $this->collectAdmin($admin_ids);
            $this->collectWorker($worker_ids);
            $this->collectOrderUserInfo($worker_order_ids);
            $this->collectOrderProduct($worker_order_ids);
            $this->collectAppointRecord($worker_order_ids);
            $this->collectOrderFee($worker_order_ids);
            $this->collectAccessory($worker_order_ids);
            $this->collectCost($worker_order_ids);
            $this->collectAllowance($worker_order_ids);
            $this->collectWxUser($wx_user_ids);
            $this->collectFirstDistribute($worker_order_ids);
            $this->collectOutWorkerAddFee($worker_order_ids);

            foreach ($orders as $order) {
                $worker_order_id = $order['id'];
                $orno = $order['orno'];
                $worker_id = $order['worker_id'];
                $factory_id = $order['factory_id'];
                $checker_id = $order['checker_id'];
                $distributor_id = $order['distributor_id'];
                $returnee_id = $order['returnee_id'];
                $auditor_id = $order['auditor_id'];
                $worker_order_status = $order['worker_order_status'];
                $service_type = $order['service_type'];
                $create_time = $order['create_time'];
                $cancel_status = $order['cancel_status'];
                $origin_type = $order['origin_type'];
                $add_id = $order['add_id'];
                $cancel_type = $order['cancel_type'];
                $canceler_id = $order['canceler_id'];
                $worker_order_type = $order['worker_order_type'];

                $offset = 1;

                //工单
                $status_str = OrderService::getStatusStr($worker_order_status, $cancel_status);
                $worker_order_type_str = OrderService::getOrderTypeName($worker_order_type);

                //客服信息
                $checker_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $checker_id);
                $checker_name = $checker_info['name'];

                $distributor_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $distributor_id);
                $distributor_name = $distributor_info['name'];

                $returnee_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $returnee_id);
                $returnee_name = $returnee_info['name'];

                $auditor_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $auditor_id);
                $auditor_name = $auditor_info['name'];

                //下单用户信息
                $user_info = $this->getListInfo(self::LIST_TYPE_ORDER_USER_INFO, $worker_order_id);
                $province = $user_info['province'];
                $city = $user_info['city'];
                $district = $user_info['district'];
                $real_name = $user_info['real_name'];
                $user_phone = $user_info['phone'];

                //技工信息
                $worker_info = $this->getListInfo(self::LIST_TYPE_WORKER, $worker_id);
                $worker_name = $worker_info['name'];
                $worker_phone = $worker_info['phone'];

                $create_time_str = $create_time > 0 ? date('Y.m.d H:i', $create_time) : '';

                //厂家信息
                $factory_info = $this->getListInfo(self::LIST_TYPE_FACTORY, $factory_id);
                $factory_name = $factory_info['factory_full_name'];

                $add_user_name = '';
                if (OrderService::ORIGIN_TYPE_FACTORY == $origin_type) {
                    $factory_info = $this->getListInfo(self::LIST_TYPE_FACTORY, $add_id);
                    $add_user_name = $factory_info['name'];
                } elseif (OrderService::ORIGIN_TYPE_FACTORY_ADMIN == $origin_type) {
                    $factory_admin_info = $this->getListInfo(self::LIST_TYPE_FACTORY_ADMIN, $add_id);
                    $add_user_name = $factory_admin_info['name'];
                } elseif (OrderService::ORIGIN_TYPE_OUTER_USER == $origin_type) {

                } elseif (OrderService::ORIGIN_TYPE_WX_USER == $origin_type) {
                    $wx_info = $this->getListInfo(self::LIST_TYPE_WX_USER, $add_id);
                    $add_user_name = $wx_info['name'];
                } elseif (OrderService::ORIGIN_TYPE_WX_DEALER == $origin_type) {
                    $wx_info = $this->getListInfo(self::LIST_TYPE_WX_USER, $add_id);
                    $add_user_name = $wx_info['name'];
                }

                $service_type_str = OrderService::getServiceType($service_type);

                $products = $this->getListData(self::LIST_TYPE_ORDER_PRODUCT, $worker_order_id);
                $product_num = array_sum(array_column($products, 'product_nums'));

                $export_obj->setRowData([$orno, $status_str, $worker_order_type_str, $checker_name, $distributor_name, $returnee_name, $auditor_name, $province, $city, $district, $real_name, $user_phone, $worker_name, $worker_phone, $create_time_str, $factory_name, $add_user_name, $service_type_str, $product_num], [$this, 'exportEmptyValue']);

                //工单产品
                $export_obj->setStartPlace('T', $order_row_no);
                foreach ($products as $product) {
                    $user_service_request = $product['user_service_request'];
                    $cp_category_name = $product['cp_category_name'];
                    $cp_product_brand_name = $product['cp_product_brand_name'];
                    $cp_product_standard_name = $product['cp_product_standard_name'];
                    $cp_product_mode = $product['cp_product_mode'];
                    $cp_fault_name = $product['cp_fault_name'];
                    $worker_report_remark = $product['worker_report_remark'];
                    $is_complete = $product['is_complete'];

                    $is_complete_str = WorkerOrderProductService::getIsCompleteStr($is_complete);

                    $export_obj->setRowData([$user_service_request, $cp_category_name, $cp_product_standard_name, $cp_product_brand_name, $cp_product_mode, $cp_fault_name, $is_complete_str . '-'.$worker_report_remark], [$this, 'exportEmptyValue']);
                }

                $offset = max($offset, count($products));

                if (in_array($worker_order_status, $settle_show_worker_order_status)) {
                    $export_obj->setStartPlace('AA', $order_row_no);
                    foreach ($products as $product) {

                        $factory_repair_fee_modify = $product['factory_repair_fee_modify'];
                        $service_fee_modify = $product['service_fee_modify'];
                        $service_reason = $product['service_reason'];
                        $factory_repair_reason = $product['factory_repair_reason'];

                        $export_obj->setRowData([$factory_repair_fee_modify, $factory_repair_reason, $service_fee_modify, $service_reason], [$this, 'exportEmptyValue']);
                    }

                    $offset = max($offset, count($products));

                    //厂家费用
                    $order_fee_info = $this->getListInfo(self::LIST_TYPE_ORDER_FEE, $worker_order_id);
                    $factory_total_fee_modify = $order_fee_info['factory_total_fee_modify'];
                    $worker_repair_fee_modify = $order_fee_info['worker_repair_fee_modify'];
                    $service_fee_modify = $order_fee_info['service_fee_modify'];
                    $accessory_out_fee_modify = $order_fee_info['accessory_out_fee_modify'];
                    $worker_net_receipts = $order_fee_info['worker_net_receipts'];

                    //厂家上门费
                    $export_obj->setStartPlace('AE', $order_row_no);

                    $appoints = $this->getListData(self::LIST_TYPE_APPOINT_RECORD, $worker_order_id);

                    $appoint_times = count($appoints);
                    $appoint_fees = array_column($appoints, 'factory_appoint_fee_modify');
                    $total_appoint_fee = array_sum($appoint_fees);

                    $appoint_str = implode('+', $appoint_fees);
                    $appoint_str = empty($appoint_str) ? '' : $appoint_str . ';';
                    $reason_str = implode(';', array_filter(array_column($appoints, 'factory_appoint_reason')));

                    $export_obj->setRowData([$total_appoint_fee, $appoint_times, $appoint_str . $reason_str], [$this, 'exportEmptyValue']);

                    //厂家费用单
                    $export_obj->setStartPlace('AH', $order_row_no);

                    $cost = $this->getListData(self::LIST_TYPE_COST, $worker_order_id);

                    $total_cost = array_sum(array_column($cost, 'fee'));

                    $export_obj->setRowData([$total_cost]);

                    $export_obj->setStartPlace('AI', $order_row_no);

                    $type_str = empty($cost) ? '' : CostService::getTypeStr($cost[0]['type']);

                    $export_obj->setRowData([$type_str], [$this, 'exportEmptyValue']);

                    //$offset = max($offset, count($cost));

                    //厂家配件单
                    $export_obj->setStartPlace('AJ', $order_row_no);
                    $accessory = $this->getListData(self::LIST_TYPE_ACCESSORY, $worker_order_id);
                    $accessory_fees = array_column($accessory, 'worker_transport_fee');
                    $total_accessory_fee = array_sum($accessory_fees);
                    $export_obj->setRowData([$total_accessory_fee]);

                    //$offset = max($offset, count($accessory));

                    //厂家合计
                    $export_obj->setStartPlace('AK', $order_row_no);
                    $export_obj->setRowData([$factory_total_fee_modify], [$this, 'exportEmptyValue']);

                    //技工工单产品
                    $export_obj->setStartPlace('AL', $order_row_no);
                    foreach ($products as $product) {
                        $worker_repair_fee_modify = $product['worker_repair_fee_modify'];
                        $worker_repair_reason = $product['worker_repair_reason'];

                        $export_obj->setRowData([$worker_repair_fee_modify, $worker_repair_reason]);
                    }
                    $offset = max($offset, count($products));

                    //技工上门费
                    $export_obj->setStartPlace('AN', $order_row_no);

                    $appoints = $this->getListData(self::LIST_TYPE_APPOINT_RECORD, $worker_order_id);
                    $appoint_times = count($appoints);
                    $appoint_fees = array_column($appoints, 'worker_appoint_fee_modify');
                    $total_appoint_fee = array_sum($appoint_fees);

                    $appoint_str = implode('+', $appoint_fees);
                    $appoint_str = empty($appoint_str) ? '' : $appoint_str . ';';
                    $reason_str = implode(';', array_filter(array_column($appoints, 'worker_appoint_reason')));

                    $export_obj->setRowData([$total_appoint_fee, $appoint_times, $appoint_str . $reason_str], [$this, 'exportEmptyValue']);

                    //技工费用单
                    $export_obj->setStartPlace('AQ', $order_row_no);
                    $cost = $this->getListData(self::LIST_TYPE_COST, $worker_order_id);

                    $total_cost = array_sum(array_column($cost, 'fee'));
                    $export_obj->setRowData([$total_cost], [$this, 'exportEmptyValue']);

                    //技工配件单
                    $export_obj->setStartPlace('AR', $order_row_no);
                    $total_accessory = array_sum(array_column($accessory, 'worker_transport_fee'));
                    $export_obj->setRowData([$total_accessory], [$this, 'exportEmptyValue']);

                    //补贴单
                    $export_obj->setStartPlace('AS', $order_row_no);
                    $allow = $this->getListData(self::LIST_TYPE_ALLOWANCE, $worker_order_id);
                    $sum = 0;
                    $fee = [];
                    $type_arr = [];
                    foreach ($allow as $val) {
                        $type = $val['type'];
                        $sum += $val['apply_fee_modify'];
                        $fee[] = $val['apply_fee_modify'];
                        $type_arr[] = AllowanceService::getTypeStr($type);
                    }

                    $fee_str = implode('+', $fee);
                    $fee_str = empty($fee_str)? '': $fee_str.';';

                    $export_obj->setRowData([$sum,  $fee_str . implode('+', $type_arr)], [$this, 'exportEmptyValue']);

                    //保外单
                    if (!in_array($worker_order_type, OrderService::ORDER_TYPE_OUT_INSURANCE_LIST)) {
                        $export_obj->setStartPlace('AU', $order_row_no);

                        $add_fees = $this->getListData(self::LIST_TYPE_ORDER_OUT_WORKER_ADD_FEE, $worker_order_id);

                        $pay_type = empty($add_fees) ? 0 : $add_fees[0]['pay_type'];

                        $pay_type_str = WorkerOrderOutWorkerAddFeeService::getPayType($pay_type);

                        $export_obj->setRowData([$worker_repair_fee_modify, $accessory_out_fee_modify, $pay_type_str, $service_fee_modify], [$this, 'exportEmptyValue']);
                    }

                    $export_obj->setStartPlace('AY', $order_row_no);
                    $export_obj->setRowData([$worker_net_receipts], [$this, 'exportEmptyValue']);
                }

                //完结时间
                $worker_first_sign_time = $order['worker_first_sign_time'];
                $return_time = $order['return_time'];
                $audit_time = $order['audit_time'];
                $factory_audit_time = $order['factory_audit_time'];
                $complete_time_str = $factory_audit_time > 0 ? date('Y.m.d H:i', $factory_audit_time) : '';

                $sign_time_len = ($worker_first_sign_time - $create_time) / 86400;
                $return_time_len = ($return_time - $create_time) / 86400;
                $auditor_time_len = ($audit_time - $create_time) / 86400;
                $factory_auditor_time_len = ($factory_audit_time - $create_time) / 86400;

                $sign_time_len_str = $sign_time_len > 0 ? round($sign_time_len, 1) : '';
                $auditor_time_len_str = $auditor_time_len > 0 ? round($auditor_time_len, 1) : '';
                $return_time_str = $return_time_len > 0 ? round($return_time_len, 1) : '';
                $factory_auditor_time_str = $factory_auditor_time_len > 0 ? round($factory_auditor_time_len, 1) : '';
                $cancel_remark = $order['cancel_remark'];

                $role = '';
                $cancel_user_name = '';
                $cancel_reason = '';
                if (
                    OrderService::CANCEL_TYPE_CS == $cancel_status ||
                    OrderService::CANCEL_TYPE_CS_STOP == $cancel_status
                ) {
                    $role = '客服';
                    $info = $this->getListInfo(self::LIST_TYPE_ADMIN, $canceler_id);
                    $cancel_user_name = $info['name'];
                } elseif (OrderService::CANCEL_TYPE_WX_USER == $cancel_status) {
                    $role = 'C端用户';
                    $info = $this->getListInfo(self::LIST_TYPE_WX_USER, $canceler_id);
                    $cancel_user_name = $info['name'];
                } elseif (OrderService::CANCEL_TYPE_FACTORY == $cancel_status) {
                    $role = '厂家';
                    $info = $this->getListInfo(self::LIST_TYPE_FACTORY, $canceler_id);
                    $cancel_user_name = $info['name'];
                } elseif (OrderService::CANCEL_TYPE_WX_DEALER == $cancel_status) {
                    $role = '经销商';
                    $info = $this->getListInfo(self::LIST_TYPE_WX_USER, $canceler_id);
                    $cancel_user_name = $info['name'];
                } elseif (OrderService::CANCEL_TYPE_FACTORY_ADMIN == $cancel_status) {
                    $role = '厂家';
                    $info = $this->getListInfo(self::LIST_TYPE_FACTORY_ADMIN, $canceler_id);
                    $cancel_user_name = $info['name'];
                }

                $cancel_reason = (OrderService::CS_CANCEL_REASON[$cancel_type] ?? '') . ' ' . $cancel_remark;

                $accessory_stats = $this->getListData(self::LIST_TYPE_ACCESSORY, $worker_order_id);
                $accessory_return_sum = 0;
                $accessory_give_up_sum = 0;
                foreach ($accessory_stats as $val) {
                    $status = $val['accessory_status'];
                    $is_giveup = $val['is_giveup_return'];
                    if (in_array($status, $accessory_show_return_status)) {
                        if (AccessoryService::RETURN_ACCESSORY_PASS == $is_giveup) {
                            $accessory_return_sum++;
                        } else {
                            $accessory_give_up_sum++;
                        }
                    }
                }

                $check_time_len = $order['check_time'] - $create_time;
                $check_time_len_str = $check_time_len > 0 ? round($check_time_len / 3600, 1) : 0;

                $first_distribute = $this->getListInfo(self::LIST_TYPE_ORDER_FIRST_DISTRIBUTE, $worker_order_id);
                $distribute_time = empty($first_distribute['create_time']) ? 0 : $first_distribute['create_time'];

                $distribute_time_len = $distribute_time - $create_time;
                $distribute_time_len_str = $distribute_time_len > 0 ? round($distribute_time_len / 3600, 1) : 0;

                $complete_appoint_len = $order['worker_repair_time'] - $create_time;
                $complete_appoint_len_str = $complete_appoint_len > 0 ? round($complete_appoint_len / 3600, 1) : 0;

                $export_obj->setStartPlace('AZ', $order_row_no);
                $export_obj->setRowData([$accessory_return_sum, $accessory_give_up_sum, $complete_time_str, $check_time_len_str, $distribute_time_len_str, $sign_time_len_str, $return_time_str, $complete_appoint_len_str, $auditor_time_len_str, $factory_auditor_time_str, $role, $cancel_user_name, $cancel_reason], [$this, 'exportEmptyValue']);

                $order_row_no += $offset;
                $export_obj->setStartPlace('A', $order_row_no);

            }

            $export_obj->saveFile($dir_path, $file_name, $ext);

            $last_id = end($worker_order_ids);
            $this->flush();
        } while (true);

        self::download($cached_path);

    }

    /**
     * 已结算工单
     *
     * @param $opts
     * @param $factory_id
     *
     * @throws Exception
     * @throws \Exception
     */
    public function adminOrderSettled($opts, $factory_id)
    {
        $where = $opts['where'] ?? null;
        $join = $opts['join'] ?? null;
        $alias = $opts['alias'] ?? null;
        $query_alias = strlen($alias) > 0 ? $alias . '.' : '';

        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $factory_model = BaseModel::getInstance('factory');
        $field = 'factory_short_name,factory_full_name,linkman';
        $factory_info = $factory_model->getOneOrFail($factory_id, $field);
        $factory_name = $factory_info['factory_full_name'];
        $linkman = $factory_info['linkman'];

        $file_name = date("{$factory_name}已结算工单数据导出-(Y年m月d日H时i分s秒)");
        $tpl_path = 'Public/厂家已结算工单数据导出模板.xls';
        $cached_path = $this->getTempFilePath($tpl_path, $file_name);
        $path_info = $this->getFileInfo($cached_path);
        $dir_path = $path_info['dirname'];
        $file_name = $path_info['filename'];
        $ext = $path_info['extension'];

        $export_obj = new ExcelExport();
        $export_obj->setSheet(0)->setTplPath($cached_path)
            ->setStartPlace('A', 3);
        $last_id = PHP_INT_MAX;

        $worker_order_model = BaseModel::getInstance('worker_order');
        $field = 'id,orno,worker_id,factory_id,checker_id,distributor_id,returnee_id,auditor_id,worker_order_status,worker_order_type,service_type,create_time,cancel_status,cancel_remark,origin_type,add_id,create_remark,worker_first_sign_time,return_time,audit_time,factory_audit_time,worker_order_type';
        if (!empty($alias)) {
            $field = preg_replace('#(?=\b[a-zA-Z])#', $query_alias, $field);
        }
        $order_row_no = 3;

        $where[$query_alias . 'id'][] = ['lt', &$last_id];
        $where[$query_alias . 'factory_id'] = $factory_id;
        $order_by = $query_alias . 'id DESC';
        do {

            $opts = [
                'field' => $field,
                'where' => $where,
                'join'  => $join,
                'alias' => $alias,
                'order' => $order_by,
                'limit' => self::EXPORT_LIMIT,
            ];
            $orders = $worker_order_model->getList($opts);
            if (empty($orders)) {
                break;
            }

            $worker_order_ids = [];
            $worker_ids = [];
            $admin_ids = [];
            $factory_admin_ids = [];
            $wx_user_ids = [];

            foreach ($orders as $order) {
                $id = $order['id'];
                $worker_id = $order['worker_id'];
                $checker_id = $order['checker_id'];
                $returnee_id = $order['returnee_id'];
                $auditor_id = $order['auditor_id'];
                $distributor_id = $order['distributor_id'];
                $origin_type = $order['origin_type'];
                $add_id = $order['add_id'];

                if (OrderService::ORIGIN_TYPE_FACTORY_ADMIN == $origin_type) {
                    $factory_admin_ids[] = $add_id;
                } elseif (OrderService::ORIGIN_TYPE_OUTER_USER == $origin_type) {

                } elseif (OrderService::ORIGIN_TYPE_WX_USER == $origin_type) {
                    $wx_user_ids[] = $add_id;
                } elseif (OrderService::ORIGIN_TYPE_WX_DEALER == $origin_type) {
                    $wx_user_ids[] = $add_id;
                }

                $worker_order_ids[] = $id;
                $worker_ids[] = $worker_id;
                $admin_ids[] = $checker_id;
                $admin_ids[] = $returnee_id;
                $admin_ids[] = $auditor_id;
                $admin_ids[] = $distributor_id;
            }

            $this->collectAdmin($admin_ids);
            $this->collectFactoryAdmin($factory_admin_ids);
            $this->collectWxUser($wx_user_ids);
            $this->collectWorker($worker_ids);
            $this->collectOrderUserInfo($worker_order_ids);
            $this->collectOrderProduct($worker_order_ids);
            $this->collectAppointRecord($worker_order_ids);
            $this->collectOrderFee($worker_order_ids);
            $this->collectAccessory($worker_order_ids);
            $this->collectCost($worker_order_ids);

            foreach ($orders as $order) {
                $worker_order_id = $order['id'];
                $orno = $order['orno'];
                $checker_id = $order['checker_id'];
                $distributor_id = $order['distributor_id'];
                $returnee_id = $order['returnee_id'];
                $auditor_id = $order['auditor_id'];
                $worker_order_status = $order['worker_order_status'];
                $service_type = $order['service_type'];
                $create_time = $order['create_time'];
                $cancel_status = $order['cancel_status'];
                $origin_type = $order['origin_type'];
                $add_id = $order['add_id'];
                $worker_order_type = $order['worker_order_type'];

                $export_obj->setStartPlace('A', $order_row_no);

                //工单
                $status_str = OrderService::getStatusStr($worker_order_status, $cancel_status);
                $worker_order_type_str = OrderService::getOrderTypeName($worker_order_type);

                $checker_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $checker_id);
                $checker_name = $checker_info['name'];

                $distributor_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $distributor_id);
                $distributor_name = $distributor_info['name'];

                $returnee_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $returnee_id);
                $returnee_name = $returnee_info['name'];

                $auditor_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $auditor_id);
                $auditor_name = $auditor_info['name'];

                $user_info = $this->getListInfo(self::LIST_TYPE_ORDER_USER_INFO, $worker_order_id);
                $province = $user_info['province'];
                $city = $user_info['city'];
                $district = $user_info['district'];
                $real_name = $user_info['real_name'];
                $user_phone = $user_info['phone'];

                $create_time_str = $create_time > 0 ? date('Y.m.d H:i', $create_time) : '';

                $add_user_name = '';
                if (OrderService::ORIGIN_TYPE_FACTORY == $origin_type) {
                    $add_user_name = $linkman;
                } elseif (OrderService::ORIGIN_TYPE_FACTORY_ADMIN == $origin_type) {
                    $factory_admin_info = $this->getListInfo(self::LIST_TYPE_FACTORY_ADMIN, $add_id);
                    $add_user_name = $factory_admin_info['name'];
                } elseif (OrderService::ORIGIN_TYPE_OUTER_USER == $origin_type) {

                } elseif (OrderService::ORIGIN_TYPE_WX_USER == $origin_type) {
                    $wx_info = $this->getListInfo(self::LIST_TYPE_WX_USER, $add_id);
                    $add_user_name = $wx_info['name'];
                } elseif (OrderService::ORIGIN_TYPE_WX_DEALER == $origin_type) {
                    $wx_info = $this->getListInfo(self::LIST_TYPE_WX_USER, $add_id);
                    $add_user_name = $wx_info['name'];
                }

                $service_type_str = OrderService::getServiceType($service_type);

                $products = $this->getListData(self::LIST_TYPE_ORDER_PRODUCT, $worker_order_id);

                $product_num = array_sum(array_column($products, 'product_nums'));

                $export_obj->setRowData([$orno, $status_str, $worker_order_type_str, $checker_name, $distributor_name, $returnee_name, $auditor_name, $province, $city, $district, $real_name, $user_phone, $create_time_str, $factory_name, $add_user_name, $service_type_str, $product_num], [$this, 'exportEmptyValue']);


                $product_len = count($products);

                //工单产品
                $export_obj->setStartPlace('R', $order_row_no);
                foreach ($products as $product) {
                    $user_service_request = $product['user_service_request'];
                    $cp_category_name = $product['cp_category_name'];
                    $cp_product_brand_name = $product['cp_product_brand_name'];
                    $cp_product_standard_name = $product['cp_product_standard_name'];
                    $cp_product_mode = $product['cp_product_mode'];
                    $cp_fault_name = $product['cp_fault_name'];
                    $factory_repair_fee_modify = $product['factory_repair_fee_modify'];
                    $factory_repair_reason = $product['factory_repair_reason'];
                    $service_fee_modify = $product['service_fee_modify'];
                    $service_reason = $product['service_reason'];

                    $worker_report_remark = $product['worker_report_remark'];
                    $is_complete = $product['is_complete'];

                    $is_complete_str = WorkerOrderProductService::getIsCompleteStr($is_complete);

                    $export_obj->setRowData([$user_service_request, $cp_category_name, $cp_product_standard_name, $cp_product_brand_name, $cp_product_mode, $cp_fault_name, $is_complete_str . $worker_report_remark, $factory_repair_fee_modify, $factory_repair_reason, $service_fee_modify, $service_reason], [$this, 'exportEmptyValue']);
                }

                //厂家费用
                $order_fee_info = $this->getListInfo(self::LIST_TYPE_ORDER_FEE, $worker_order_id);
                $factory_total_fee_modify = $order_fee_info['factory_total_fee_modify'];

                //厂家上门费
                $export_obj->setStartPlace('AC', $order_row_no);

                $appoints = $this->getListData(self::LIST_TYPE_APPOINT_RECORD, $worker_order_id);

                $appoint_times = count($appoints);
                $appoint_fees = array_column($appoints, 'factory_appoint_fee_modify');
                $total_appoint_fee = array_sum($appoint_fees);

                $appoint_str = implode('+', $appoint_fees);
                $appoint_str = empty($appoint_str) ? '' : $appoint_str . ';';
                $reason_str = implode(';', array_filter(array_column($appoints, 'worker_appoint_reason')));

                $export_obj->setRowData([$total_appoint_fee, $appoint_times, $appoint_str . $reason_str], [$this, 'exportEmptyValue']);

                //厂家费用单
                $export_obj->setStartPlace('AF', $order_row_no);
                $cost = $this->getListData(self::LIST_TYPE_COST, $worker_order_id);

                $total_cost = array_sum(array_column($cost, 'fee'));

                $export_obj->setRowData([$total_cost]);

                $export_obj->setStartPlace('AG', $order_row_no);

                $type_str = empty($cost) ? '' : CostService::getTypeStr($cost[0]['type']);

                $export_obj->setRowData([$type_str], [$this, 'exportEmptyValue']);

                $cost_len = count($cost);

                //厂家配件单
                $export_obj->setStartPlace('AH', $order_row_no);
                $accessory = $this->getListData(self::LIST_TYPE_ACCESSORY, $worker_order_id);
                $accessory_fees = array_column($accessory, 'worker_transport_fee');
                $total_accessory_fee = array_sum($accessory_fees);
                $export_obj->setRowData([$total_accessory_fee]);

                $accessory_len = count($accessory);

                //厂家合计
                $export_obj->setStartPlace('AI', $order_row_no);
                $export_obj->setRowData([$factory_total_fee_modify], [$this, 'exportEmptyValue']);

                $export_obj->setStartPlace('AJ', $order_row_no);
                $factory_audit_time = $order['factory_audit_time'];
                $complete_time_str = $factory_audit_time > 0 ? date('Y.m.d H:i', $factory_audit_time) : '';

                $export_obj->setRowData([$complete_time_str], [$this, 'exportEmptyValue']);

                $offset = max(1, $accessory_len, $cost_len, $product_len);
                $order_row_no += $offset;

            }

            $export_obj->saveFile($dir_path, $file_name, $ext);

            $last = end($orders);
            $last_id = $last['id'];
            $this->flush();
        } while (true);
        self::download($cached_path);

    }

    /**
     * 待结算,冻结工单
     *
     * @param array $opts
     * @param int   $factory_id
     * @param int   $search_type
     */
    public function adminOrderFrozen($opts, $factory_id, $search_type)
    {
        $where = $opts['where'] ?? null;
        $join = $opts['join'] ?? null;
        $alias = $opts['alias'] ?? null;
        $query_alias = strlen($alias) > 0 ? $alias . '.' : '';

        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $factory_model = BaseModel::getInstance('factory');
        $field = 'factory_short_name,linkphone,factory_full_name,linkman';
        $factory_info = $factory_model->getOneOrFail($factory_id, $field);
        $factory_name = $factory_info['factory_full_name'];
        $factory_full_name = $factory_info['factory_full_name'];
        $linkphone = $factory_info['linkphone'];
        $linkman = $factory_info['linkman'];

        $file_name = date("{$factory_name}待结算工单数据导出-(Y年m月d日H时i分s秒)");
        if (1 == $search_type) {
            $file_name = date("{$factory_name}暂冻结工单数据导出-(Y年m月d日H时i分s秒)");
        }
        $tpl_path = 'Public/厂家暂冻结、待结算工单数据导出模板.xls';
        $cached_path = $this->getTempFilePath($tpl_path, $file_name);
        $path_info = $this->getFileInfo($cached_path);
        $dir_path = $path_info['dirname'];
        $file_name = $path_info['filename'];
        $ext = $path_info['extension'];

        $export_obj = new ExcelExport();
        $export_obj->setSheet(0)->setTplPath($cached_path);
        $last_id = PHP_INT_MAX;
        $order_row_no = 3;

        $export_obj->setCellValue('V', 2, '待结算金额');
        $frozen_type = 1;
        if (1 == $search_type) {
            $export_obj->setCellValue('V', 2, '冻结金额');
            $frozen_type = 0;
        }
        $export_obj->saveFile($dir_path, $file_name, $ext);

        $export_obj->setStartPlace('A', 1);
        $title = "厂家名称：{$factory_name}  厂家账号：{$linkphone}";
        $export_obj->setRowData([$title]);
        $export_obj->saveFile($dir_path, $file_name, $ext);

        $worker_order_model = BaseModel::getInstance('worker_order');

        $field = 'id,orno,worker_id,factory_id,checker_id,distributor_id,returnee_id,auditor_id,worker_order_status,worker_order_type,service_type,create_time,cancel_status,cancel_remark,origin_type,add_id,create_remark,worker_first_sign_time,return_time,audit_time,factory_audit_time';
        if (!empty($alias)) {
            $field = preg_replace('#(?=\b[a-zA-Z])#', $query_alias, $field);
        }

        $where[$query_alias . 'id'][] = ['lt', &$last_id];
        $order_by = $query_alias . 'id DESC';
        do {
            $opts = [
                'field' => $field,
                'where' => $where,
                'join'  => $join,
                'alias' => $alias,
                'order' => $order_by,
                'limit' => self::EXPORT_LIMIT,
            ];
            $orders = $worker_order_model->getList($opts);
            if (empty($orders)) {
                break;
            }


            $worker_order_ids = [];
            $worker_ids = [];
            $admin_ids = [];
            $factory_admin_ids = [];
            $wx_user_ids = [];

            foreach ($orders as $order) {
                $id = $order['id'];
                $worker_id = $order['worker_id'];
                $checker_id = $order['checker_id'];
                $returnee_id = $order['returnee_id'];
                $auditor_id = $order['auditor_id'];
                $distributor_id = $order['distributor_id'];

                $origin_type = $order['origin_type'];
                $add_id = $order['add_id'];

                if (OrderService::ORIGIN_TYPE_FACTORY_ADMIN == $origin_type) {
                    $factory_admin_ids[] = $add_id;
                } elseif (OrderService::ORIGIN_TYPE_OUTER_USER == $origin_type) {

                } elseif (OrderService::ORIGIN_TYPE_WX_USER == $origin_type) {
                    $wx_user_ids[] = $add_id;
                } elseif (OrderService::ORIGIN_TYPE_WX_DEALER == $origin_type) {
                    $wx_user_ids[] = $add_id;
                }

                $origin_type = $order['origin_type'];
                $add_id = $order['add_id'];

                if (OrderService::ORIGIN_TYPE_FACTORY_ADMIN == $origin_type) {
                    $factory_admin_ids[] = $add_id;
                } elseif (OrderService::ORIGIN_TYPE_OUTER_USER == $origin_type) {

                } elseif (OrderService::ORIGIN_TYPE_WX_USER == $origin_type) {
                    $wx_user_ids[] = $add_id;
                } elseif (OrderService::ORIGIN_TYPE_WX_DEALER == $origin_type) {
                    $wx_user_ids[] = $add_id;
                }

                $worker_order_ids[] = $id;
                $worker_ids[] = $worker_id;
                $admin_ids[] = $checker_id;
                $admin_ids[] = $returnee_id;
                $admin_ids[] = $auditor_id;
                $admin_ids[] = $distributor_id;
            }

            $this->collectWxUser($wx_user_ids);
            $this->collectFactoryAdmin($factory_admin_ids);
            $this->collectAdmin($admin_ids);
            $this->collectWorker($worker_ids);
            $this->collectOrderUserInfo($worker_order_ids);
            $this->collectOrderProduct($worker_order_ids);
            $this->collectAppointRecord($worker_order_ids);
            $this->collectOrderFee($worker_order_ids);
            $this->collectAccessory($worker_order_ids);
            $this->collectCost($worker_order_ids);
            $this->collectFrozenRecord($worker_order_ids, $frozen_type);

            foreach ($orders as $order) {
                $worker_order_id = $order['id'];
                $orno = $order['orno'];
                $checker_id = $order['checker_id'];
                $distributor_id = $order['distributor_id'];
                $returnee_id = $order['returnee_id'];
                $auditor_id = $order['auditor_id'];
                $worker_order_status = $order['worker_order_status'];
                $worker_order_type = $order['worker_order_type'];
                $service_type = $order['service_type'];
                $create_time = $order['create_time'];
                $cancel_status = $order['cancel_status'];
                $origin_type = $order['origin_type'];
                $add_id = $order['add_id'];

                $export_obj->setStartPlace('A', $order_row_no);

                //工单
                $status_str = OrderService::getStatusStr($worker_order_status, $cancel_status);
                $worker_order_type_str = OrderService::getOrderTypeName($worker_order_type);

                $checker_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $checker_id);
                $checker_name = $checker_info['name'];

                $distributor_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $distributor_id);
                $distributor_name = $distributor_info['name'];

                $returnee_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $returnee_id);
                $returnee_name = $returnee_info['name'];

                $auditor_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $auditor_id);
                $auditor_name = $auditor_info['name'];

                $user_info = $this->getListInfo(self::LIST_TYPE_ORDER_USER_INFO, $worker_order_id);
                $province = $user_info['province'];
                $city = $user_info['city'];
                $district = $user_info['district'];
                $real_name = $user_info['real_name'];
                $user_phone = $user_info['phone'];

                $create_time_str = $create_time > 0 ? date('Y.m.d H:i', $create_time) : '';

                $add_user_name = '';
                if (OrderService::ORIGIN_TYPE_FACTORY == $origin_type) {
                    $add_user_name = $linkman;
                } elseif (OrderService::ORIGIN_TYPE_FACTORY_ADMIN == $origin_type) {
                    $factory_admin_info = $this->getListInfo(self::LIST_TYPE_FACTORY_ADMIN, $add_id);
                    $add_user_name = $factory_admin_info['name'];
                } elseif (OrderService::ORIGIN_TYPE_OUTER_USER == $origin_type) {

                } elseif (OrderService::ORIGIN_TYPE_WX_USER == $origin_type) {
                    $wx_info = $this->getListInfo(self::LIST_TYPE_WX_USER, $add_id);
                    $add_user_name = $wx_info['name'];
                } elseif (OrderService::ORIGIN_TYPE_WX_DEALER == $origin_type) {
                    $wx_info = $this->getListInfo(self::LIST_TYPE_WX_USER, $add_id);
                    $add_user_name = $wx_info['name'];
                }

                $service_type_str = OrderService::getServiceType($service_type);

                $export_obj->setRowData([$orno, $status_str, $worker_order_type_str, $checker_name, $distributor_name, $returnee_name, $auditor_name, $province, $city, $district, $real_name, $user_phone, $create_time_str, $factory_full_name, $add_user_name, $service_type_str], [$this, 'exportEmptyValue']);

                $products = $this->getListData(self::LIST_TYPE_ORDER_PRODUCT, $worker_order_id);

                $product_len = count($products);

                //工单产品
                $export_obj->setStartPlace('Q', $order_row_no);
                foreach ($products as $product) {
                    $user_service_request = $product['user_service_request'];
                    $cp_category_name = $product['cp_category_name'];
                    $cp_product_brand_name = $product['cp_product_brand_name'];
                    $cp_product_standard_name = $product['cp_product_standard_name'];
                    $cp_product_mode = $product['cp_product_mode'];

                    $export_obj->setRowData([$user_service_request, $cp_category_name, $cp_product_standard_name, $cp_product_brand_name, $cp_product_mode], [$this, 'exportEmptyValue']);
                }

                $export_obj->setStartPlace('V', $order_row_no);

                $frozen_info = $this->getListInfo(self::LIST_TYPE_FROZEN_RECORD, $worker_order_id);
                $frozen = empty($frozen_info['frozen_money']) ? 0 : $frozen_info['frozen_money'];

                $export_obj->setRowData([$frozen]);

                $offset = max(1, $product_len);

                $order_row_no += $offset;

            }

            $export_obj->saveFile($dir_path, $file_name, $ext);

            $last_id = end($worker_order_ids);
            $this->flush();
        } while (true);

        self::download($cached_path);
    }

    /**
     * 厂家资金充值记录
     *
     * @param array $opts       筛选项
     *                          |-where 筛选项
     *                          |-join 连表
     *                          |-alias 表别名
     * @param int   $factory_id 厂家id
     */
    public function factoryRechargeOne($opts, $factory_id)
    {
        $where = $opts['where'] ?? null;
        $join = $opts['join'] ?? null;
        $alias = $opts['alias'] ?? null;
        $query_alias = strlen($alias) > 0 ? $alias . '.' : '';

        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $factory_model = BaseModel::getInstance('factory');
        $field = 'factory_full_name,linkphone,linkman';
        $factory_info = $factory_model->getOneOrFail($factory_id, $field);
        $factory_name = $factory_info['factory_full_name'];
        $linkman = $factory_info['linkman'];

        $file_name = date($factory_name . ' 资金充值记录导出-(y年m月d日H时i分s秒)');
        $tpl_path = 'Public/（厂家简称）资金充值记录导出模板.xls';
        $cached_path = $this->getTempFilePath($tpl_path, $file_name);
        $path_info = $this->getFileInfo($cached_path);
        $dir_path = $path_info['dirname'];
        $file_name = $path_info['filename'];
        $ext = $path_info['extension'];

        $export_obj = new ExcelExport();
        $export_obj->setSheet(0);

        $export_obj->setTplPath($cached_path);
        $last_id = PHP_INT_MAX;
        $order_row_no = 2;

        $model = BaseModel::getInstance('factory_money_change_record');
        $field = 'id,factory_id,operator_id,operator_type,operation_remark,change_type,money,change_money,last_money,create_time';
        if (!empty($alias)) {
            $field = preg_replace('#(?=\b[a-zA-Z])#', $query_alias, $field);
        }
        $where[$query_alias . 'id'][] = ['lt', &$last_id];
        $where[$query_alias . 'factory_id'] = $factory_id;
        $order_by = $query_alias . 'id desc';
        $export_opts = [
            'field' => $field,
            'where' => $where,
            'join'  => $join,
            'alias' => $alias,
            'order' => $order_by,
        ];
        do {
            $records = $model->getList($export_opts);
            if (empty($records)) {
                break;
            }

            $export_obj->setStartPlace('A', $order_row_no);

            $factory_admin_ids = [];

            foreach ($records as $record) {
                $operator_id = $record['operator_id'];
                $operator_type = $record['operator_type'];

                if (FactoryMoneyChangeRecordService::OPERATOR_TYPE_ADMIN == $operator_type) {
                    //$admin_ids[] = $operator_id;
                } elseif (FactoryMoneyChangeRecordService::OPERATOR_TYPE_FACTORY_ADMIN == $operator_type) {
                    $factory_admin_ids[] = $operator_id;
                }
            }

            $this->collectFactoryAdmin($factory_admin_ids);

            foreach ($records as $record) {
                $operator_id = $record['operator_id'];
                $operator_type = $record['operator_type'];
                $operation_remark = $record['operation_remark'];
                $change_type = $record['change_type'];
                $money = $record['money'];
                $change_money = $record['change_money'];
                $last_money = $record['last_money'];
                $create_time = $record['create_time'];

                $operator = '';
                if (FactoryMoneyChangeRecordService::OPERATOR_TYPE_FACTORY == $operator_type) {
                    $operator = $linkman;
                } elseif (FactoryMoneyChangeRecordService::OPERATOR_TYPE_ADMIN == $operator_type) {
                    //$info = $this->getListInfo(self::LIST_TYPE_ADMIN, $operator_id);
                    //$operator = $info['name'];
                    $operator = '神州财务';
                } elseif (FactoryMoneyChangeRecordService::OPERATOR_TYPE_FACTORY_ADMIN == $operator_type) {
                    $info = $this->getListInfo(self::LIST_TYPE_FACTORY_ADMIN, $operator_id);
                    $operator = $info['name'];
                } elseif (FactoryMoneyChangeRecordService::OPERATOR_TYPE_SYSTEM == $operator_type) {
                    $operator = '系统主动调整';
                }

                $change_type_str = FactoryMoneyChangeRecordService::getChangeTypeStr($change_type);

                $create_time_str = $create_time > 0 ? date('Y.m.d H:i', $create_time) : '';

                $export_obj->setRowData([$create_time_str, $change_money, $money, $last_money, $change_type_str, $operator, $operation_remark], [$this, 'exportEmptyValue']);

            }

            $export_obj->saveFile($dir_path, $file_name, $ext);

            $last = end($records);
            $last_id = $last['id'];
            $this->flush();
        } while (true);

        self::download($cached_path);
    }

    /**
     * 资金暂冻结工单
     *
     * @param $opts
     * @param $factory_id
     * @param $search_type
     */
    public function factoryOrderFrozen($opts, $factory_id, $search_type)
    {
        $where = $opts['where'] ?? null;
        $join = $opts['join'] ?? null;
        $alias = $opts['alias'] ?? null;
        $query_alias = strlen($alias) > 0 ? $alias . '.' : '';

        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $factory_model = BaseModel::getInstance('factory');
        $field = 'factory_short_name,linkphone,factory_full_name,linkman';
        $factory_info = $factory_model->getOneOrFail($factory_id, $field);
        $factory_name = $factory_info['factory_full_name'];
        $linkman = $factory_info['linkman'];

        $file_name = date("{$factory_name}资金待结算工单数据导出-(Y年m月d日H时i分s秒)");
        if (1 == $search_type) {
            $file_name = date("{$factory_name}资金暂冻结工单数据导出-(Y年m月d日H时i分s秒)");
        }
        $tpl_path = 'Public/（厂家简称）资金暂冻结工单导出模板.xls';
        $cached_path = $this->getTempFilePath($tpl_path, $file_name);
        $path_info = $this->getFileInfo($cached_path);
        $dir_path = $path_info['dirname'];
        $file_name = $path_info['filename'];
        $ext = $path_info['extension'];

        $export_obj = new ExcelExport();
        $export_obj->setSheet(0)->setTplPath($cached_path);
        $last_id = PHP_INT_MAX;
        $order_row_no = 2;

        $export_obj->setCellValue('U', 1, '待结算金额');
        $frozen_type = 1;
        if (1 == $search_type) {
            $export_obj->setCellValue('U', 1, '冻结金额');
            $frozen_type = 0;
        }
        $export_obj->saveFile($dir_path, $file_name, $ext);

        $worker_order_model = BaseModel::getInstance('worker_order');

        $field = 'id,orno,worker_id,factory_id,checker_id,distributor_id,returnee_id,auditor_id,worker_order_status,worker_order_type,service_type,create_time,cancel_status,cancel_remark,origin_type,add_id,create_remark,worker_first_sign_time,return_time,audit_time,factory_audit_time';
        if (!empty($alias)) {
            $field = preg_replace('#(?=\b[a-zA-Z])#', $query_alias, $field);
        }

        $where[$query_alias . 'id'][] = ['lt', &$last_id];
        $order_by = $query_alias . 'id DESC';
        $opts = [
            'field' => $field,
            'where' => $where,
            'join'  => $join,
            'alias' => $alias,
            'order' => $order_by,
            'limit' => self::EXPORT_LIMIT,
        ];
        do {
            $orders = $worker_order_model->getList($opts);
            if (empty($orders)) {
                break;
            }

            $worker_order_ids = [];
            $worker_ids = [];
            $admin_ids = [];
            $wx_user_ids = [];
            $factory_admin_ids = [];

            foreach ($orders as $order) {
                $id = $order['id'];
                $worker_id = $order['worker_id'];
                $checker_id = $order['checker_id'];
                $returnee_id = $order['returnee_id'];
                $auditor_id = $order['auditor_id'];
                $distributor_id = $order['distributor_id'];
                $origin_type = $order['origin_type'];
                $add_id = $order['add_id'];

                if (OrderService::ORIGIN_TYPE_FACTORY_ADMIN == $origin_type) {
                    $factory_admin_ids[] = $add_id;
                } elseif (OrderService::ORIGIN_TYPE_OUTER_USER == $origin_type) {

                } elseif (OrderService::ORIGIN_TYPE_WX_USER == $origin_type) {
                    $wx_user_ids[] = $add_id;
                } elseif (OrderService::ORIGIN_TYPE_WX_DEALER == $origin_type) {
                    $wx_user_ids[] = $add_id;
                }

                $distributor_id = $order['distributor_id'];
                $origin_type = $order['origin_type'];
                $add_id = $order['add_id'];

                if (OrderService::ORIGIN_TYPE_FACTORY_ADMIN == $origin_type) {
                    $factory_admin_ids[] = $add_id;
                } elseif (OrderService::ORIGIN_TYPE_OUTER_USER == $origin_type) {

                } elseif (OrderService::ORIGIN_TYPE_WX_USER == $origin_type) {
                    $wx_user_ids[] = $add_id;
                } elseif (OrderService::ORIGIN_TYPE_WX_DEALER == $origin_type) {
                    $wx_user_ids[] = $add_id;
                }

                $worker_order_ids[] = $id;
                $worker_ids[] = $worker_id;
                $admin_ids[] = $checker_id;
                $admin_ids[] = $returnee_id;
                $admin_ids[] = $auditor_id;
                $admin_ids[] = $distributor_id;
            }

            $this->collectFactoryAdmin($factory_admin_ids);
            $this->collectWxUser($wx_user_ids);
            $this->collectAdmin($admin_ids);
            $this->collectWorker($worker_ids);
            $this->collectOrderUserInfo($worker_order_ids);
            $this->collectOrderProduct($worker_order_ids);
            $this->collectAppointRecord($worker_order_ids);
            $this->collectOrderFee($worker_order_ids);
            $this->collectAccessory($worker_order_ids);
            $this->collectCost($worker_order_ids);
            $this->collectFrozenRecord($worker_order_ids, $frozen_type);

            foreach ($orders as $order) {
                $worker_order_id = $order['id'];
                $orno = $order['orno'];
                $checker_id = $order['checker_id'];
                $distributor_id = $order['distributor_id'];
                $returnee_id = $order['returnee_id'];
                $auditor_id = $order['auditor_id'];
                $worker_order_status = $order['worker_order_status'];
                $worker_order_type = $order['worker_order_type'];
                $service_type = $order['service_type'];
                $create_time = $order['create_time'];
                $cancel_status = $order['cancel_status'];
                $origin_type = $order['origin_type'];
                $add_id = $order['add_id'];

                $export_obj->setStartPlace('A', $order_row_no);

                //工单
                $status_str = OrderService::getStatusStr($worker_order_status, $cancel_status);
                $worker_order_type_str = OrderService::getOrderTypeName($worker_order_type);

                $checker_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $checker_id);
                $checker_name = $checker_info['user_name']; // 厂家显示对外昵称

                $distributor_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $distributor_id);
                $distributor_name = $distributor_info['user_name']; // 厂家显示对外昵称

                $returnee_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $returnee_id);
                $returnee_name = $returnee_info['user_name']; // 厂家显示对外昵称

                $auditor_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $auditor_id);
                $auditor_name = $auditor_info['user_name']; // 厂家显示对外昵称

                $user_info = $this->getListInfo(self::LIST_TYPE_ORDER_USER_INFO, $worker_order_id);
                $province = $user_info['province'];
                $city = $user_info['city'];
                $district = $user_info['district'];
                $real_name = $user_info['real_name'];
                $user_phone = $user_info['phone'];

                $create_time_str = $create_time > 0 ? date('Y.m.d H:i', $create_time) : '';

                $add_user_name = '';
                if (OrderService::ORIGIN_TYPE_FACTORY == $origin_type) {
                    $add_user_name = $linkman;
                } elseif (OrderService::ORIGIN_TYPE_FACTORY_ADMIN == $origin_type) {
                    $factory_admin_info = $this->getListInfo(self::LIST_TYPE_FACTORY_ADMIN, $add_id);
                    $add_user_name = $factory_admin_info['name'];
                } elseif (OrderService::ORIGIN_TYPE_OUTER_USER == $origin_type) {

                } elseif (OrderService::ORIGIN_TYPE_WX_USER == $origin_type) {
                    $wx_info = $this->getListInfo(self::LIST_TYPE_WX_USER, $add_id);
                    $add_user_name = $wx_info['name'];
                } elseif (OrderService::ORIGIN_TYPE_WX_DEALER == $origin_type) {
                    $wx_info = $this->getListInfo(self::LIST_TYPE_WX_USER, $add_id);
                    $add_user_name = $wx_info['name'];
                }


                $service_type_str = OrderService::getServiceType($service_type);

                $export_obj->setRowData([$orno, $status_str, $worker_order_type_str, $province, $city, $district, $real_name, $user_phone, $create_time_str, $add_user_name, $service_type_str], [$this, 'exportEmptyValue']);

                $products = $this->getListData(self::LIST_TYPE_ORDER_PRODUCT, $worker_order_id);

                $product_len = count($products);

                //工单产品
                $export_obj->setStartPlace('L', $order_row_no);
                foreach ($products as $product) {
                    $user_service_request = $product['user_service_request'];
                    $cp_category_name = $product['cp_category_name'];
                    $cp_product_brand_name = $product['cp_product_brand_name'];
                    $cp_product_standard_name = $product['cp_product_standard_name'];
                    $cp_product_mode = $product['cp_product_mode'];

                    $export_obj->setRowData([$user_service_request, $cp_category_name, $cp_product_standard_name, $cp_product_brand_name, $cp_product_mode], [$this, 'exportEmptyValue']);
                }

                $export_obj->setStartPlace('Q', $order_row_no);

                $frozen_info = $this->getListInfo(self::LIST_TYPE_FROZEN_RECORD, $worker_order_id);
                $frozen = empty($frozen_info['frozen_money']) ? 0 : $frozen_info['frozen_money'];

                $export_obj->setRowData([$frozen]);

                $offset = max(1, $product_len);

                $order_row_no += $offset;

            }

            $export_obj->saveFile($dir_path, $file_name, $ext);

            $last_id = end($worker_order_ids);
            $this->flush();
        } while (true);

        self::download($cached_path);
    }

    /**
     * 已结算工单
     *
     * @param $opts
     * @param $factory_id
     *
     * @throws Exception
     * @throws \Exception
     */
    public function factoryOrderSettled($opts, $factory_id)
    {
        $where = $opts['where'] ?? null;
        $join = $opts['join'] ?? null;
        $alias = $opts['alias'] ?? null;
        $query_alias = strlen($alias) > 0 ? $alias . '.' : '';

        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $factory_model = BaseModel::getInstance('factory');
        $field = 'factory_short_name,factory_full_name,linkman';
        $factory_info = $factory_model->getOneOrFail($factory_id, $field);
        $factory_name = $factory_info['factory_full_name'];
        $linkman = $factory_info['linkman'];

        $file_name = date("{$factory_name}已结算工单导出-(Y年m月d日H时i分s秒)");
        $tpl_path = 'Public/（厂家简称）已结算工单导出模板.xls';
        $cached_path = $this->getTempFilePath($tpl_path, $file_name);
        $path_info = $this->getFileInfo($cached_path);
        $dir_path = $path_info['dirname'];
        $file_name = $path_info['filename'];
        $ext = $path_info['extension'];

        $export_obj = new ExcelExport();
        $export_obj->setSheet(0)->setTplPath($cached_path)
            ->setStartPlace('A', 3);
        $last_id = PHP_INT_MAX;

        $worker_order_model = BaseModel::getInstance('worker_order');
        $field = 'id,orno,worker_id,factory_id,checker_id,distributor_id,returnee_id,auditor_id,worker_order_status,worker_order_type,service_type,create_time,cancel_status,cancel_remark,origin_type,add_id,create_remark,worker_first_sign_time,return_time,audit_time,factory_audit_time';
        if (!empty($alias)) {
            $field = preg_replace('#(?=\b[a-zA-Z])#', $query_alias, $field);
        }
        $order_row_no = 3;

        $where[$query_alias . 'id'][] = ['lt', &$last_id];
        $where[$query_alias . 'factory_id'] = $factory_id;
        $order_by = $query_alias . 'id DESC';
        $opts = [
            'field' => $field,
            'where' => $where,
            'join'  => $join,
            'alias' => $alias,
            'order' => $order_by,
            'limit' => self::EXPORT_LIMIT,
        ];
        do {

            $orders = $worker_order_model->getList($opts);
            if (empty($orders)) {
                break;
            }

            $worker_order_ids = [];
            $worker_ids = [];
            $admin_ids = [];
            $wx_user_ids = [];
            $factory_admin_ids = [];

            foreach ($orders as $order) {
                $id = $order['id'];
                $worker_id = $order['worker_id'];
                $checker_id = $order['checker_id'];
                $returnee_id = $order['returnee_id'];
                $auditor_id = $order['auditor_id'];
                $distributor_id = $order['distributor_id'];
                $origin_type = $order['origin_type'];
                $add_id = $order['add_id'];

                if (OrderService::ORIGIN_TYPE_FACTORY_ADMIN == $origin_type) {
                    $factory_admin_ids[] = $add_id;
                } elseif (OrderService::ORIGIN_TYPE_OUTER_USER == $origin_type) {

                } elseif (OrderService::ORIGIN_TYPE_WX_USER == $origin_type) {
                    $wx_user_ids[] = $add_id;
                } elseif (OrderService::ORIGIN_TYPE_WX_DEALER == $origin_type) {
                    $wx_user_ids[] = $add_id;
                }

                $worker_order_ids[] = $id;
                $worker_ids[] = $worker_id;
                $admin_ids[] = $checker_id;
                $admin_ids[] = $returnee_id;
                $admin_ids[] = $auditor_id;
                $admin_ids[] = $distributor_id;
            }

            $this->collectFactoryAdmin($factory_admin_ids);
            $this->collectWxUser($wx_user_ids);
            $this->collectAdmin($admin_ids);
            $this->collectWorker($worker_ids);
            $this->collectOrderUserInfo($worker_order_ids);
            $this->collectOrderProduct($worker_order_ids);
            $this->collectAppointRecord($worker_order_ids);
            $this->collectOrderFee($worker_order_ids);
            $this->collectAccessory($worker_order_ids);
            $this->collectCost($worker_order_ids);

            foreach ($orders as $order) {
                $worker_order_id = $order['id'];
                $worker_id = $order['worker_id'];
                $orno = $order['orno'];
                $checker_id = $order['checker_id'];
                $distributor_id = $order['distributor_id'];
                $returnee_id = $order['returnee_id'];
                $auditor_id = $order['auditor_id'];
                $worker_order_status = $order['worker_order_status'];
                $worker_order_type = $order['worker_order_type'];
                $service_type = $order['service_type'];
                $create_time = $order['create_time'];
                $cancel_status = $order['cancel_status'];
                $origin_type = $order['origin_type'];
                $add_id = $order['add_id'];

                $export_obj->setStartPlace('A', $order_row_no);

                //工单
                $status_str = OrderService::getStatusStr($worker_order_status, $cancel_status);
                $worker_order_type_str = OrderService::getOrderTypeName($worker_order_type);

                $checker_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $checker_id);
                $checker_name = $checker_info['user_name']; // 厂家显示对外昵称

                $distributor_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $distributor_id);
                $distributor_name = $distributor_info['user_name']; // 厂家显示对外昵称

                $returnee_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $returnee_id);
                $returnee_name = $returnee_info['user_name']; // 厂家显示对外昵称

                $auditor_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $auditor_id);
                $auditor_name = $auditor_info['user_name']; // 厂家显示对外昵称

                $worker_info = $this->getListInfo(self::LIST_TYPE_WORKER, $worker_id);
                $worker_name = $worker_info['nickname'];
                $worker_telephone = $worker_info['worker_telephone'];

                $user_info = $this->getListInfo(self::LIST_TYPE_ORDER_USER_INFO, $worker_order_id);
                $province = $user_info['province'];
                $city = $user_info['city'];
                $district = $user_info['district'];
                $real_name = $user_info['real_name'];
                $user_phone = $user_info['phone'];

                $create_time_str = $create_time > 0 ? date('Y.m.d H:i', $create_time) : '';

                $add_user_name = '';
                if (OrderService::ORIGIN_TYPE_FACTORY == $origin_type) {
                    $add_user_name = $linkman;
                } elseif (OrderService::ORIGIN_TYPE_FACTORY_ADMIN == $origin_type) {
                    $factory_admin_info = $this->getListInfo(self::LIST_TYPE_FACTORY_ADMIN, $add_id);
                    $add_user_name = $factory_admin_info['name'];
                } elseif (OrderService::ORIGIN_TYPE_OUTER_USER == $origin_type) {

                } elseif (OrderService::ORIGIN_TYPE_WX_USER == $origin_type) {
                    $wx_info = $this->getListInfo(self::LIST_TYPE_WX_USER, $add_id);
                    $add_user_name = $wx_info['name'];
                } elseif (OrderService::ORIGIN_TYPE_WX_DEALER == $origin_type) {
                    $wx_info = $this->getListInfo(self::LIST_TYPE_WX_USER, $add_id);
                    $add_user_name = $wx_info['name'];
                }

                $service_type_str = OrderService::getServiceType($service_type);

                $products = $this->getListData(self::LIST_TYPE_ORDER_PRODUCT, $worker_order_id);

                $product_num = array_sum(array_column($products, 'product_nums'));
                $product_len = count($products);

                $export_obj->setRowData([$orno, $status_str, $worker_order_type_str, $province, $city, $district, $real_name, $user_phone, $worker_name, $worker_telephone, $create_time_str, $add_user_name, $service_type_str, $product_num], [$this, 'exportEmptyValue']);

                //工单产品
                $export_obj->setStartPlace('O', $order_row_no);
                foreach ($products as $product) {
                    $user_service_request = $product['user_service_request'];
                    $cp_category_name = $product['cp_category_name'];
                    $cp_product_brand_name = $product['cp_product_brand_name'];
                    $cp_product_standard_name = $product['cp_product_standard_name'];
                    $cp_product_mode = $product['cp_product_mode'];
                    $cp_fault_name = $product['cp_fault_name'];
                    $factory_repair_fee_modify = $product['factory_repair_fee_modify'];
                    $service_fee_modify = $product['service_fee_modify'];

                    $worker_report_remark = $product['worker_report_remark'];
                    $is_complete = $product['is_complete'];

                    $is_complete_str = WorkerOrderProductService::getIsCompleteStr($is_complete);

                    $export_obj->setRowData([$user_service_request, $cp_category_name, $cp_product_standard_name, $cp_product_brand_name, $cp_product_mode, $cp_fault_name, $is_complete_str . $worker_report_remark, $factory_repair_fee_modify, $service_fee_modify], [$this, 'exportEmptyValue']);
                }

                //厂家费用
                $order_fee_info = $this->getListInfo(self::LIST_TYPE_ORDER_FEE, $worker_order_id);
                $factory_total_fee_modify = $order_fee_info['factory_total_fee_modify'];

                //厂家上门费
                $export_obj->setStartPlace('X', $order_row_no);

                $appoints = $this->getListData(self::LIST_TYPE_APPOINT_RECORD, $worker_order_id);
                $appoint_times = count($appoints);

                $appoint_fees = array_column($appoints, 'factory_appoint_fee_modify');
                $total_appoint_fee = array_sum($appoint_fees);

                $appoint_str = implode('+', $appoint_fees);
                $appoint_str = empty($appoint_str) ? '' : $appoint_str . ';';
                $reason_str = implode(';', array_filter(array_column($appoints, 'factory_appoint_reason')));

                $export_obj->setRowData([$total_appoint_fee, $appoint_times, $appoint_str . $reason_str], [$this, 'exportEmptyValue']);

                //厂家费用单
                $export_obj->setStartPlace('AA', $order_row_no);
                $cost = $this->getListData(self::LIST_TYPE_COST, $worker_order_id);

                $total_cost = array_sum(array_column($cost, 'fee'));

                $export_obj->setRowData([$total_cost]);

                $export_obj->setStartPlace('AB', $order_row_no);

                $type_str = empty($cost) ? '' : CostService::getTypeStr($cost[0]['type']);

                $export_obj->setRowData([$type_str], [$this, 'exportEmptyValue']);

                //$cost_len = count($cost);

                //厂家配件单
                $export_obj->setStartPlace('AC', $order_row_no);
                $accessory = $this->getListData(self::LIST_TYPE_ACCESSORY, $worker_order_id);
                $accessory_fees = array_column($accessory, 'worker_transport_fee');
                $total_accessory_fee = array_sum($accessory_fees);
                $export_obj->setRowData([$total_accessory_fee]);

                $accessory_len = count($accessory);

                //厂家合计
                $export_obj->setStartPlace('AD', $order_row_no);
                $export_obj->setRowData([$factory_total_fee_modify], [$this, 'exportEmptyValue']);

                $export_obj->setStartPlace('AE', $order_row_no);
                $factory_audit_time = $order['factory_audit_time'];
                $complete_time_str = $factory_audit_time > 0 ? date('Y.m.d H:i', $factory_audit_time) : '';

                $export_obj->setRowData([$complete_time_str], [$this, 'exportEmptyValue']);

                $offset = max(1, $accessory_len, $product_len);
                $order_row_no += $offset;

            }

            $export_obj->saveFile($dir_path, $file_name, $ext);

            $last = end($orders);
            $last_id = $last['id'];
            $this->flush();
        } while (true);

        self::download($cached_path);
    }

    protected static function download($filename)
    {
        if (!is_file($filename)) {
            throw new Exception('下载文件不存在！');
        }

        $root = Util::getServerFileUrl(pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME));

        $filename = $root . '/' . $filename;

        header('Location: ' . $filename);
    }

    /**
     * 提现
     *
     * @param $opts
     * @param $worker_id
     */
    public function adminWorkerWithdraw($opts, $worker_id)
    {
        $where = $opts['where'] ?? null;
        $join = $opts['join'] ?? null;
        $alias = $opts['alias'] ?? null;
        $query_alias = strlen($alias) > 0 ? $alias . '.' : '';

        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $worker_model = BaseModel::getInstance('worker');
        $field = 'nickname';
        $worker_info = $worker_model->getOneOrFail($worker_id, $field);
        $nickname = $worker_info['nickname'];

        $file_name = date("{$nickname}维修商提现记录导出-(Y年m月d日H时i分s秒)");
        $tpl_path = 'Public/维修商提现记录导出模板.xls';
        $cached_path = $this->getTempFilePath($tpl_path, $file_name);
        $path_info = $this->getFileInfo($cached_path);
        $dir_path = $path_info['dirname'];
        $file_name = $path_info['filename'];
        $ext = $path_info['extension'];

        $export_obj = new ExcelExport();
        $export_obj->setSheet(0)->setTplPath($cached_path);

        $export_obj->setStartPlace('A', 2);
        $last_id = PHP_INT_MAX;

        $model = BaseModel::getInstance('worker_withdrawcash_record');

        $where[$query_alias . 'id'][] = ['lt', &$last_id];
        $order_by = $query_alias . 'id DESC';
        $opts = [
            'where' => $where,
            'join'  => $join,
            'alias' => $alias,
            'order' => $order_by,
            'limit' => self::EXPORT_LIMIT,
        ];
        do {
            $list = $model->getList($opts);
            if (empty($list)) {
                break;
            }

            $withdrawcash_excel_ids = [];
            foreach ($list as $val) {
                $withdrawcash_excel_id = $val['withdrawcash_excel_id'];

                $withdrawcash_excel_ids[] = $withdrawcash_excel_id;
            }

            $this->collectExcelRecord($withdrawcash_excel_ids);

            $other_bank_id = 659004728; // 银行其他id,取自cm_list_item的list_item_id

            foreach ($list as $val) {
                $card_number = $val['card_number'];
                $out_money = $val['out_money'];
                $complete_time = $val['complete_time'];
                $create_time = $val['create_time'];
                $bank_name = $val['bank_name'];
                $other_bank_name = $val['other_bank_name'];
                $withdrawcash_excel_id = $val['withdrawcash_excel_id'];
                $status = $val['status'];
                $withdraw_cash_number = $val['withdraw_cash_number'];
                $fail_reason = $val['fail_reason'];
                $bank_id = $val['bank_id'];

                $status_str = WorkerWithdrawService::getStatusStr($status, $withdrawcash_excel_id);
                if ($other_bank_id == $bank_id) {
                    $bank_name .= ' ' . $other_bank_name;
                }

                $excel_info = $this->getListInfo(self::LIST_WITHDRAW_EXCEL, $withdrawcash_excel_id);
                $admin_id = $excel_info['admin_id'];

                $admin_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $admin_id);
                $admin_name = $admin_info['name'];

                $complete_time_str = $complete_time > 0 ? date('Y.m.d H:i', $complete_time) : '';
                $create_time_str = $create_time > 0 ? date('Y.m.d H:i', $create_time) : '';

                $export_obj->setRowData([$withdraw_cash_number, $status_str, $out_money, $bank_name, $card_number, $create_time_str, $complete_time_str, $fail_reason, $admin_name], [$this, 'exportEmptyValue']);

            }

            $export_obj->saveFile($dir_path, $file_name, $ext);

            $last = end($list);
            $last_id = $last['id'];
            $this->flush();

        } while (true);

        self::download($cached_path);

    }

    /**
     * 联系记录
     *
     * @param $opts
     */
    public function adminWorkerContact($opts)
    {
        $where = $opts['where'] ?? null;
        $join = $opts['join'] ?? null;
        $alias = $opts['alias'] ?? null;
        $query_alias = strlen($alias) > 0 ? $alias . '.' : '';

        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $file_name = date("技工联系记录导出-(Y年m月d日H时i分s秒)");
        $tpl_path = 'Public/技工联系记录导出模板.xls';
        $cached_path = $this->getTempFilePath($tpl_path, $file_name);
        $path_info = $this->getFileInfo($cached_path);
        $dir_path = $path_info['dirname'];
        $file_name = $path_info['filename'];
        $ext = $path_info['extension'];

        $export_obj = new ExcelExport();
        $export_obj->setSheet(0)->setTplPath($cached_path)
            ->setStartPlace('A', 2);
        $last_id = PHP_INT_MAX;

        $model = BaseModel::getInstance('worker_contact_record');

        $where[$query_alias . 'id'][] = ['lt', &$last_id];
        $order_by = $query_alias . 'id DESC';
        $opts = [
            'where' => $where,
            'join'  => $join,
            'alias' => $alias,
            'order' => $order_by,
            'limit' => self::EXPORT_LIMIT,
        ];
        do {
            $list = $model->getList($opts);
            if (empty($list)) {
                break;
            }

            $worker_ids = [];
            $admin_ids = [];

            foreach ($list as $val) {
                $admin_id = $val['admin_id'];
                $worker_id = $val['worker_id'];

                $worker_ids[] = $worker_id;
                $admin_ids[] = $admin_id;
            }

            $this->collectWorker($worker_ids);
            $this->collectAdmin($admin_ids);

            foreach ($list as $val) {
                $admin_id = $val['admin_id'];
                $worker_id = $val['worker_id'];
                $contact_object = $val['contact_object'];
                $contact_object_other = $val['contact_object_other'];
                $contact_method = $val['contact_method'];
                $contact_type = $val['contact_type'];
                $contact_result = $val['contact_result'];
                $contact_report = $val['contact_report'];
                $contact_remark = $val['contact_remark'];
                $create_time = $val['create_time'];

                $worker_info = $this->getListInfo(self::LIST_TYPE_WORKER, $worker_id);
                $worker_name = $worker_info['name'];
                $worker_telephone = $worker_info['worker_telephone'];

                $admin_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $admin_id);
                $admin_name = $admin_info['name'];

                $object_str = OrderContactService::getObjectType($contact_object) . ' ' . $contact_object_other;
                $method_str = OrderContactService::getMethodStr($contact_method);
                $type_str = OrderContactService::getTypeStr($contact_type);
                $result_str = OrderContactService::getResultStr($contact_result);
                $admin_evaluate_str = OrderContactService::getResultStr($contact_report);
                $create_time_str = $create_time > 0 ? date('Y.m.d H:i', $create_time) : '';

                $export_obj->setRowData([$admin_name, $worker_name, $worker_telephone, $object_str, $method_str, $type_str, $result_str, $admin_evaluate_str, $contact_remark, $create_time_str], [$this, 'exportEmptyValue']);

            }

            $export_obj->saveFile($dir_path, $file_name, $ext);

            $last = end($list);
            $last_id = $last['id'];
            $this->flush();

        } while (true);

        self::download($cached_path);
    }

    /**
     * 技工收入
     *
     * @param $opts
     * @param $worker_id
     */
    public function adminWorkerIncome($opts, $worker_id)
    {
        $where = $opts['where'] ?? null;
        $join = $opts['join'] ?? null;
        $alias = $opts['alias'] ?? null;
        $query_alias = strlen($alias) > 0 ? $alias . '.' : '';

        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $factory_model = BaseModel::getInstance('worker');
        $field = 'worker_telephone,nickname';
        $factory_info = $factory_model->getOneOrFail($worker_id, $field);
        $worker_telephone = $factory_info['worker_telephone'];
        $nickname = $factory_info['nickname'];

        $file_name = date("{$nickname}维修商收入记录导出-(Y年m月d日H时i分s秒)");
        $tpl_path = 'Public/维修商收入记录导出模板.xls';
        $cached_path = $this->getTempFilePath($tpl_path, $file_name);
        $path_info = $this->getFileInfo($cached_path);
        $dir_path = $path_info['dirname'];
        $file_name = $path_info['filename'];
        $ext = $path_info['extension'];

        $export_obj = new ExcelExport();
        $export_obj->setSheet(0)->setTplPath($cached_path);
        $last_id = PHP_INT_MAX;

        $title = "维修商名称：{$nickname}   电话：{$worker_telephone}";
        $export_obj->setStartPlace('A', 1)->setRowData([$title]);
        $export_obj->saveFile($dir_path, $file_name, $ext);

        $record_model = BaseModel::getInstance('worker_repair_money_record');
        $order_row_no = 3;

        $where[$query_alias . 'id'][] = ['lt', &$last_id];
        $where[$query_alias . 'worker_id'] = $worker_id;
        $order_by = $query_alias . 'id DESC';
        $opts = [
            'where' => $where,
            'join'  => $join,
            'alias' => $alias,
            'order' => $order_by,
            'limit' => self::EXPORT_LIMIT,
        ];

        $this->collectCategories();
        do {
            $records = $record_model->getList($opts);
            if (empty($records)) {
                break;
            }

            $worker_order_ids = array_column($records, 'worker_order_id');

            $this->collectOrder($worker_order_ids);
            $this->collectOrderProductJoinCmList($worker_order_ids);

            foreach ($records as $record) {
                $worker_order_id = $record['worker_order_id'];
                $create_time = $record['create_time'];
                $order_money = $record['order_money'];

                $export_obj->setStartPlace('A', $order_row_no);

                $order_info = $this->getListInfo(self::LIST_TYPE_ORDER, $worker_order_id);
                $orno = $order_info['orno'];
                $service_type = $order_info['service_type'];
                $service_type_str = OrderService::getServiceType($service_type);

                $create_time_str = $create_time > 0 ? date('Y.m.d H:i', $create_time) : '';

                $export_obj->setRowData([$orno, $service_type_str], [$this, 'exportEmptyValue']);

                $export_obj->setStartPlace('C', $order_row_no);

                $products = $this->getListInfo(self::LIST_TYPE_ORDER_PRODUCT_JOIN_CM_LIST, $worker_order_id);
                foreach ($products as $product) {
                    $cp_category_name = $product['cp_category_name'];
                    $cp_product_standard_name = $product['cp_product_standard_name'];
                    $parent_id = $product['item_parent'];
                    $category_info = $this->getListInfo(self::LIST_PRODUCT_CATEGORY, $parent_id);
                    $category_name = $category_info['item_desc'];

                    $export_obj->setRowData([$category_name, $cp_category_name, $cp_product_standard_name], [$this, 'exportEmptyValue']);
                }
                $product_len = count($products);

                $export_obj->setStartPlace('F', $order_row_no);
                $export_obj->setRowData([$create_time_str, $order_money], [$this, 'exportEmptyValue']);

                $offset = max(1, $product_len);
                $order_row_no += $offset;

            }

            $export_obj->saveFile($dir_path, $file_name, $ext);

            $last = end($orders);
            $last_id = $last['id'];
            $this->flush();
        } while (true);

        self::download($cached_path);
    }

    /**
     * 技工质保金
     *
     * @param $opts
     */
    public function adminWorkerQuality($opts)
    {
        $where = $opts['where'] ?? null;
        $join = $opts['join'] ?? null;
        $alias = $opts['alias'] ?? null;
        $query_alias = strlen($alias) > 0 ? $alias . '.' : '';

        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $file_name = date("维修商质保金调整导出-(Y年m月d日H时i分s秒)");
        $tpl_path = 'Public/维修商质保金调整导出模板.xls';
        $cached_path = $this->getTempFilePath($tpl_path, $file_name);
        $path_info = $this->getFileInfo($cached_path);
        $dir_path = $path_info['dirname'];
        $file_name = $path_info['filename'];
        $ext = $path_info['extension'];

        $export_obj = new ExcelExport();
        $export_obj->setSheet(0)->setTplPath($cached_path)
            ->setStartPlace('A', 2);
        $last_id = PHP_INT_MAX;

        $model = BaseModel::getInstance('worker_quality_money_record');

        $where[$query_alias . 'id'][] = ['lt', &$last_id];
        $order_by = $query_alias . 'id DESC';
        $opts = [
            'where' => $where,
            'join'  => $join,
            'alias' => $alias,
            'order' => $order_by,
            'limit' => self::EXPORT_LIMIT,
        ];
        do {
            $list = $model->getList($opts);
            if (empty($list)) {
                break;
            }

            $worker_ids = [];
            $admin_ids = [];
            $worker_order_ids = [];

            foreach ($list as $val) {
                $admin_id = $val['admin_id'];
                $worker_id = $val['worker_id'];
                $worker_order_id = $val['worker_order_id'];

                $worker_ids[] = $worker_id;
                $admin_ids[] = $admin_id;
                $worker_order_ids[] = $worker_order_id;
            }

            $this->collectWorker($worker_ids);
            $this->collectAdmin($admin_ids);
            $this->collectOrder($worker_order_ids);

            foreach ($list as $val) {
                $admin_id = $val['admin_id'];
                $worker_id = $val['worker_id'];
                $type = $val['type'];
                $quality_money = $val['quality_money'];
                $create_time = $val['create_time'];
                $remark = $val['remark'];
                $worker_order_id = $val['worker_order_id'];

                $worker_info = $this->getListInfo(self::LIST_TYPE_WORKER, $worker_id);
                $worker_name = $worker_info['name'];
                $worker_telephone = $worker_info['worker_telephone'];

                $admin_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $admin_id);
                $admin_name = $admin_info['name'];

                $order_info = $this->getListInfo(self::LIST_TYPE_ORDER, $worker_order_id);
                $orno = $order_info['orno'];

                $type_str = WorkerQualityService::getTypeStr($type);
                if (WorkerQualityService::TYPE_SYSTEM == $type) {
                    $type_str .= '—' . $orno;
                    $admin_name = '系统';
                }

                $create_time_str = $create_time > 0 ? date('Y.m.d H:i', $create_time) : '';

                $export_obj->setRowData([$create_time_str, $admin_name, $worker_name, $worker_telephone, $type_str, $quality_money, $remark], [$this, 'exportEmptyValue']);

            }

            $export_obj->saveFile($dir_path, $file_name, $ext);

            $last = end($list);
            $last_id = $last['id'];
            $this->flush();

        } while (true);

        self::download($cached_path);
    }

    /**
     * 技工奖惩
     *
     * @param $opts
     */
    public function adminWorkerAdjust($opts)
    {
        $where = $opts['where'] ?? null;
        $join = $opts['join'] ?? null;
        $alias = $opts['alias'] ?? null;
        $query_alias = strlen($alias) > 0 ? $alias . '.' : '';

        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $file_name = date("技工奖惩导出-(Y年m月d日H时i分s秒)");
        $tpl_path = 'Public/技工奖惩导出模板.xls';
        $cached_path = $this->getTempFilePath($tpl_path, $file_name);
        $path_info = $this->getFileInfo($cached_path);
        $dir_path = $path_info['dirname'];
        $file_name = $path_info['filename'];
        $ext = $path_info['extension'];

        $export_obj = new ExcelExport();
        $export_obj->setSheet(0)->setTplPath($cached_path)
            ->setStartPlace('A', 2);
        $last_id = PHP_INT_MAX;

        $model = BaseModel::getInstance('worker_money_adjust_record');

        $where[$query_alias . 'id'][] = ['lt', &$last_id];
        $order_by = $query_alias . 'id DESC';
        $opts = [
            'where' => $where,
            'join'  => $join,
            'alias' => $alias,
            'order' => $order_by,
            'limit' => self::EXPORT_LIMIT,
        ];
        do {
            $list = $model->getList($opts);
            if (empty($list)) {
                break;
            }

            $worker_ids = [];
            $admin_ids = [];
            $worker_order_ids = [];

            foreach ($list as $val) {
                $admin_id = $val['admin_id'];
                $worker_id = $val['worker_id'];
                $worker_order_id = $val['worker_order_id'];

                $worker_ids[] = $worker_id;
                $admin_ids[] = $admin_id;
                $worker_order_ids[] = $worker_order_id;
            }

            $this->collectWorker($worker_ids);
            $this->collectAdmin($admin_ids);
            $this->collectOrder($worker_order_ids);

            foreach ($list as $val) {
                $admin_id = $val['admin_id'];
                $worker_id = $val['worker_id'];
                $adjust_remark = $val['adjust_remark'];
                $adjust_money = $val['adjust_money'];
                $create_time = $val['create_time'];
                $worker_order_id = $val['worker_order_id'];

                $worker_info = $this->getListInfo(self::LIST_TYPE_WORKER, $worker_id);
                $worker_name = $worker_info['name'];
                $worker_telephone = $worker_info['worker_telephone'];

                $admin_info = $this->getListInfo(self::LIST_TYPE_ADMIN, $admin_id);
                $admin_name = $admin_info['name'];

                $order_info = $this->getListInfo(self::LIST_TYPE_ORDER, $worker_order_id);
                $orno = $order_info['orno'];

                $create_time_str = $create_time > 0 ? date('Y.m.d H:i', $create_time) : '';

                $export_obj->setRowData([$create_time_str, $worker_name, $worker_telephone, $adjust_money, $orno, $adjust_remark, $admin_name], [$this, 'exportEmptyValue']);

            }

            $export_obj->saveFile($dir_path, $file_name, $ext);

            $last = end($list);
            $last_id = $last['id'];
            $this->flush();

        } while (true);

        self::download($cached_path);
    }

    /**
     * 提现处理
     *
     * @param $opts
     */
    public function processedWithdraw($opts)
    {
        $where = $opts['where'] ?? null;
        $join = $opts['join'] ?? null;
        $alias = $opts['alias'] ?? null;
        $query_alias = strlen($alias) > 0 ? $alias . '.' : '';

        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $file_name = '技工提现-' . date("(Y年m月d日H时i分s秒)");
        $tpl_path = 'Public/提现单导出模板.xls';
        $cached_path = $this->getTempFilePath($tpl_path, $file_name);
        $path_info = $this->getFileInfo($cached_path);
        $dir_path = $path_info['dirname'];
        $file_name = $path_info['filename'];
        $ext = $path_info['extension'];

        $export_obj = new ExcelExport();
        $export_obj->setSheet(0)->setTplPath($cached_path)
            ->setStartPlace('A', 2);
        $last_id = PHP_INT_MAX;

        $field = 'id,card_number,out_money,province_id,city_id,worker_id,bank_name,other_bank_name,real_name,bank_id';
        if (!empty($alias)) {
            $field = preg_replace('#(?=\b[a-zA-Z])#', $query_alias, $field);
        }
        $model = BaseModel::getInstance('worker_withdrawcash_record');

        $where[$query_alias . 'id'][] = ['lt', &$last_id];
        $order_by = $query_alias . 'id DESC';
        do {
            $opts = [
                'field' => $field,
                'where' => $where,
                'join'  => $join,
                'alias' => $alias,
                'order' => $order_by,
                'limit' => self::EXPORT_LIMIT,
            ];
            $list = $model->getList($opts);
            if (empty($list)) {
                break;
            }

            $worker_ids = [];
            $area_ids = [];

            $other_bank_id = 659004728; // 银行其他id,取自cm_list_item的list_item_id

            foreach ($list as $val) {
                $province_id = $val['province_id'];
                $city_id = $val['city_id'];
                $worker_id = $val['worker_id'];

                $worker_ids[] = $worker_id;
                $area_ids[] = $province_id;
                $area_ids[] = $city_id;
            }

            $this->collectWorker($worker_ids);
            $this->collectArea($area_ids);

            foreach ($list as $val) {
                $card_number = ' ' . $val['card_number'];
                $out_money = $val['out_money'];
                $province_id = $val['province_id'];
                $city_id = $val['city_id'];
                $worker_id = $val['worker_id'];
                $bank_name = $val['bank_name'];
                $other_bank_name = $val['other_bank_name'];
                $real_name = $val['real_name'];
                $bank_id = $val['bank_id'];

                $remark = '';
                if ($other_bank_id == $bank_id) {
                    $remark = $other_bank_name;
                }

                $province = $this->getListInfo(self::LIST_TYPE_AREA, $province_id);
                $province_name = $province['name'];
                $city = $this->getListInfo(self::LIST_TYPE_AREA, $city_id);
                $city_name = $city['name'];
                $worker_info = $this->getListInfo(self::LIST_TYPE_WORKER, $worker_id);
                $worker_bank_city = $worker_info['bank_city'];
                $worker_telephone = $worker_info['worker_telephone'];

                $bank_area = explode('-', $worker_bank_city);
                $bank_province = empty($bank_area[0]) ? '' : $bank_area[0];
                $bank_city = empty($bank_area[1]) ? '' : $bank_area[1];

                $export_obj->setRowData([$card_number, $real_name, $out_money, $remark, $bank_name, $province_name, $city_name, $bank_province, $bank_city, $worker_telephone]);

            }

            $export_obj->saveFile($dir_path, $file_name, $ext);

            $last = end($list);
            $last_id = $last['id'];
            $this->flush();

        } while (true);

        self::download($cached_path);
    }


    public function masterCode($opts)
    {
        $where = $opts['where'] ?? null;
        $join = $opts['join'] ?? null;
        $alias = $opts['alias'] ?? null;
        $query_alias = strlen($alias) > 0 ? $alias . '.' : '';

        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $file_name = '师傅码源数据导出-' . date("(Y年m月d日H时i分s秒)");
        $tpl_path = 'Public/师傅码源数据导出模板.xls';
        $cached_path = $this->getTempFilePath($tpl_path, $file_name);
        $path_info = $this->getFileInfo($cached_path);
        $dir_path = $path_info['dirname'];
        $file_name = $path_info['filename'];
        $ext = $path_info['extension'];

        $export_obj = new ExcelExport();
        $export_obj->setSheet(0)->setTplPath($cached_path)
            ->setStartPlace('A', 2);
        $last_id = PHP_INT_MAX;

        $field = 'worker_id,worker_telephone,nickname';
        if (!empty($alias)) {
            $field = preg_replace('#(?=\b[a-zA-Z])#', $query_alias, $field);
        }
        $model = BaseModel::getInstance('worker');

        $where[$query_alias . 'worker_id'][] = ['lt', &$last_id];
        $order_by = $query_alias . 'worker_id DESC';

        $opts = [
            'field' => $field,
            'where' => $where,
            'join'  => $join,
            'alias' => $alias,
            'order' => $order_by,
            'limit' => self::EXPORT_LIMIT,
        ];

        $hashids = new Hashids(C('WORKER_CODE_KEY'), C('WORKER_CODE_MIN_LENGTH'));
        $scan_url = C('MASTER_SCAN_URL');
        do {
            $list = $model->getList($opts);
            if (empty($list)) {
                break;
            }

            $worker_ids = [];

            foreach ($list as $val) {
                $worker_id = $val['worker_id'];

                $worker_ids[] = $worker_id;
            }

            $this->collectWorker($worker_ids);
            $this->collectScanStats($worker_ids);

            foreach ($list as $val) {
                $worker_id = $val['worker_id'];

                $worker_info = $this->getListInfo(self::LIST_TYPE_WORKER, $worker_id);
                $worker_name = $worker_info['name'];
                $worker_telephone = $worker_info['worker_telephone'];

                $url = $scan_url . $hashids->encode($worker_id);

                $stats = $this->getListInfo(self::LIST_MASTER_CODE, $worker_id);
                $num = strlen($stats['nums']) > 0 ? $stats['nums'] : '0';

                $export_obj->setRowData([$worker_name, $worker_telephone, $url, $num], [$this, 'exportEmptyValue']);

            }

            $export_obj->saveFile($dir_path, $file_name, $ext);

            $last = end($list);
            $last_id = $last['worker_id'];
            $this->flush();

        } while (true);

        self::download($cached_path);
    }

    protected function collectScanStats($worker_ids)
    {
        $stats = [];
        $field = 'worker_id,nums';
        if (!empty($worker_ids)) {

            $model = BaseModel::getInstance('worker_qr_scanning');
            $opts = [
                'field' => 'worker_id, SUM(nums) as nums',
                'where' => ['worker_id' => ['in', $worker_ids]],
                'group' => 'worker_id',
            ];
            $data = $model->getList($opts);

            foreach ($data as $val) {
                $worker_id = $val['worker_id'];

                $stats[$worker_id] = $val;
            }
        }
        $this->setList(self::LIST_MASTER_CODE, $stats, $field);
    }

    protected static function getTempDirName()
    {
        $random = mt_rand(0, 10000);
        $random_str = sprintf('%05d', $random);
        $dir_name = uniqid() . $random_str;

        return $dir_name;
    }

    protected function getTempFilePath($tpl_path, $file_name)
    {
        if (!file_exists($tpl_path)) {
            $this->throwException(ErrorCode::SYS_SYSTEM_ERROR, '模板文件不存在');
        }

        $ext = pathinfo($tpl_path, PATHINFO_EXTENSION);
        $dir_path = APP_PATH . '/Runtime/Temp/export/';
        $dir_name = self::getTempDirName();
        $dir_path = $dir_path . '/' . $dir_name;
        $cached_path = $dir_path . '/' . $file_name . '.' . $ext;

        if (!mkdir($dir_path, 0777, true)) {
            $this->throwException(ErrorCode::SYS_SYSTEM_ERROR, '创建目录失败');
        }

        if (!copy($tpl_path, $cached_path)) {
            $this->throwException(ErrorCode::SYS_SYSTEM_ERROR, '复制文件模板失败');
        }

        return $cached_path;
    }

    protected function getFileInfo($path)
    {
        $path_info = pathinfo($path);

        $file = explode('/', $path);
        $file_name = preg_replace("#\..*$#", '', end($file));

        return array_merge($path_info, ['filename' => $file_name]);
    }

}