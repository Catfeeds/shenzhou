<?php
/**
 * File: SystemReceiveOrderController.class.php
 * Function:
 * User: sakura
 * Date: 2018/2/6
 */

namespace Admin\Controller;


use Admin\Common\ErrorCode;
use Admin\Logic\AdminLogic;
use Admin\Logic\SystemReceiveOrderCacheLogic;
use Admin\Logic\SystemReceiveOrderLogic;
use Admin\Model\BaseModel;
use Carbon\Carbon;
use Common\Common\Job\CheckReceiveOrderJob;
use Common\Common\Service\AdminConfigReceiveService;
use Common\Common\Service\AdminConfigReceiveWorkdayService;
use Common\Common\Service\AdminRoleService;
use Common\Common\Service\OrderService;
use Library\Common\Util;
use Think\Upload;

class SystemReceiveOrderController extends BaseController
{

    /**
     * 初始化
     */
    public function init()
    {
        try {
            $weekday = Carbon::now()->dayOfWeek;

            //时间长度为1天+30分钟,保证队列在 边界时间点(23:59:59) 消费的时候工单 能获取缓存信息
            $expire = strtotime(date('Ymd')) + 86400 + 1800;
            (new SystemReceiveOrderCacheLogic())->init($weekday, $expire);

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /**
     * 预加载未来的数据,跨度是1天
     */
    public function periodInit()
    {
        try {
            $tomorrow = Carbon::tomorrow()->dayOfWeek;
            // 事件长度为2天+30分钟,保证队列在 边界时间点(23:59:59) 消费的工单 能获取缓存信息
            $expire = strtotime(date('Ymd')) + 86400 * 2 + 1800;
            (new SystemReceiveOrderCacheLogic())->init($tomorrow, $expire);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /**
     * 检查尚未进入队列的工单
     */
    public function checkUnReceiveOrder()
    {
        try {

            (new SystemReceiveOrderLogic())->checkUnReceiveOrder();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /**
     * 财务客服偏移量恢复
     */
    public function resetAuditorOffset()
    {
        try {
            $logic = new SystemReceiveOrderCacheLogic();
            $obj = Carbon::now();
            $day = $obj->dayOfWeek;
            $logic->resetAuditorOffset($day);
            $to = $obj->addDay(1)->dayOfWeek;
            $logic->resetAuditorOffset($to);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function notificationAuditor()
    {
        try {
            (new SystemReceiveOrderLogic())->notificationAuditor();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function notificationWorkerOrder()
    {
        try {
            (new SystemReceiveOrderLogic())->notificationWorkerOrder();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    /**
    public function exportExcelAdmin()
    {
        try {
            $upload = new Upload();
            $info = $upload->upload();

            if (!$info) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, $upload->getError());
            }

            $excel_info = $info['excel'];
            $path = $upload->rootPath . $excel_info['savepath'] . $excel_info['savename'];

            $excel_obj = \PHPExcel_IOFactory::load($path);
            $sheet_obj = $excel_obj->getActiveSheet();
            $raw_data = $sheet_obj->toArray();

            $data = [];
            foreach ($raw_data as $val) {
                $val = array_map(function ($v) {
                    return trim($v);
                }, $val);

                $temp = [
                    'tell'              => $val[2], // 手机号
                    'partner_tell'      => $val[4], // 角色名
                    'is_auto_receive'   => $val[7], // 是否接单 是/否
                    'type'              => $val[8], // 类型 厂家/品类/地区/财务/厂家组别
                    'max_receive_times' => $val[9], // 接单量
                    'receive_mode'      => $val[10], // 接单量
                    'factory_groups'    => $val[11], // 接单量
                    'factories'         => $val[13], // 厂家
                    'categories'        => $val[14], // 品类
                    'areas'             => $val[15], // 地区
                    'weekdays'          => $val[16], // 接单日
                ];

                $check = array_filter($temp);
                if (empty($check)) {
                    continue;
                }

                $data[] = $temp;
            }
            array_shift($data); // 删除第一行title

            $tells = empty($data) ? [] : array_column($data, 'tell');
            $tells = array_filter($tells);
            $admins = $this->getAdmins($tells);

            $config_data = [];
            $config_factory_data = [];
            $config_category_data = [];
            $config_area_data = [];
            $config_workday_data = [];
            $config_partner_data = [];
            $config_factory_group_data = [];

            $all_factories = $this->getFactories();
            $all_categories = $this->getCategories();
            $all_areas = $this->getAreas();
            $all_groups = $this->getGroups();

            foreach ($data as $val) {
                $tell = $val['tell'];
                $type = $val['type'];
                $weekdays = $val['weekdays'];
                $is_auto_receive = $val['is_auto_receive'];
                $max_receive_times = $val['max_receive_times'];
                $factory_str = $val['factories'];
                $category_str = $val['categories'];
                $area_str = $val['areas'];
                $factory_group_str = $val['factory_groups'];
                $partner_tell = $val['partner_tell'];
                $receive_mode = $val['receive_mode'];

                $admin_info = $admins[$tell] ?? null;
                if (!$admin_info) {
                    $str = $tell . ' 客服不存在';
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, $str);
                }
                $admin_id = $admin_info['id'];

                $workdays = [];
                for ($i = 0; $i < 7; $i++) {
                    $workdays[$i] = [
                        'admin_id'   => $admin_id,
                        'workday'    => $i,
                        'is_on_duty' => AdminConfigReceiveWorkdayService::IS_ON_DUTY_NO,
                    ];
                }

                $is_auto_receive = '是' == $is_auto_receive ? AdminConfigReceiveService::IS_AUTO_RECEIVE_YES : AdminConfigReceiveService::IS_AUTO_RECEIVE_NO;

                //管理员
                $config = [
                    'admin_id'          => $admin_id,
                    'type'              => 0,
                    'is_auto_receive'   => $is_auto_receive,
                    'max_receive_times' => $max_receive_times,
                ];
                if (AdminConfigReceiveService::IS_AUTO_RECEIVE_NO == $is_auto_receive) {
                    $config_data[] = $config;
                    $config_workday_data = array_merge($config_workday_data, $workdays);
                    continue;
                }
                if (!in_array($type, ['厂家', '品类', '地区', '财务'])) {
                    $str = $admin_id . ' ' . $tell . ' ' . $type . ' 接单类型错误';
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, $str);
                }

                if (!empty($partner_tell)) {
                    $partner_admin_info = $admins[$partner_tell] ?? null;
                    if (!$partner_admin_info) {
                        $str = $partner_tell . ' 对接客服不存在';
                        $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, $str);
                    }
                    $partner_admin_id = $partner_admin_info['id'];
                    $config_partner_data[] = [
                        'partner_admin_id' => $partner_admin_id,
                        'admin_id'         => $admin_id,
                    ];
                }

                if ('厂家' == $type) {
                    //对接厂家
                    if ('厂家组别' == $receive_mode) {
                        if (empty($factory_group_str)) {
                            $str = $admin_id . ' ' . $tell . ' 厂家组别为空';
                            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, $str);
                        }

                        $config['type'] = AdminConfigReceiveService::TYPE_FACTORY_GROUP;

                        $factory_groups = explode('|', $factory_group_str);
                        $factory_groups = array_filter($factory_groups);
                        foreach ($factory_groups as $factory_group) {
                            if (!array_key_exists($factory_group, $all_groups)) {
                                $str = $admin_id . ' ' . $tell . ' ' . $factory_group . ' 厂家组别不存在';
                                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, $str);
                            }
                            $config_factory_group_data[] = [
                                'admin_id' => $admin_id,
                                'group_id' => $all_groups[$factory_group],
                            ];
                        }

                    } else {
                        //单独厂家
                        if (empty($factory_str)) {
                            $str = $admin_id . ' ' . $tell . ' 厂家为空';
                            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, $str);
                        }

                        $config['type'] = AdminConfigReceiveService::TYPE_FACTORY;

                        $factories = explode('|', $factory_str);
                        $factories = array_filter($factories);
                        foreach ($factories as $link_phone) {
                            if (!array_key_exists($link_phone, $all_factories)) {
                                $str = $admin_id . ' ' . $tell . ' ' . $link_phone . ' 厂家不存在';
                                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, $str);
                            }
                            $config_factory_data[] = [
                                'admin_id'   => $admin_id,
                                'factory_id' => $all_factories[$link_phone]['id'],
                            ];
                        }
                    }

                    //地区匹配
                    if (empty($area_str)) {
                        //空代表全部
                        foreach ($all_areas as $area) {
                            $config_area_data[] = [
                                'admin_id'  => $admin_id,
                                'parent_id' => 0,
                                'area_id'   => $area['id'],
                            ];
                        }
                    } else {
                        $areas = explode('|', $area_str);
                        foreach ($areas as $area_name) {
                            $area_name = trim($area_name);
                            if (!array_key_exists($area_name, $all_areas)) {
                                $str = $admin_id . ' ' . $tell . ' ' . $area_name . ' 地区不存在';
                                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, $str);
                            }
                            $config_area_data[] = [
                                'admin_id'  => $admin_id,
                                'parent_id' => 0,
                                'area_id'   => $all_areas[$area_name]['id'],
                            ];
                        }
                    }
                }
                if ('品类' == $type) {
                    //对接品类
                    //品类匹配
                    if (empty($category_str)) {
                        $str = $admin_id . ' ' . $tell . ' 品类为空';
                        $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, $str);
                    }

                    $config['type'] = AdminConfigReceiveService::TYPE_CATEGORY;

                    $categories = explode('|', $category_str);
                    $categories = array_filter($categories);
                    foreach ($categories as $category_name) {
                        if (!array_key_exists($category_name, $all_categories)) {
                            $str = $admin_id . ' ' . $tell . ' ' . $category_name . ' 品类不存在';
                            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, $str);
                        }
                        $config_category_data[] = [
                            'admin_id'    => $admin_id,
                            'category_id' => $all_categories[$category_name]['id'],
                            'parent_id'   => 0,
                        ];
                    }
                    //地区匹配
                    if (empty($area_str)) {
                        //空代表全部
                        foreach ($all_areas as $area) {
                            $config_area_data[] = [
                                'admin_id'  => $admin_id,
                                'parent_id' => 0,
                                'area_id'   => $area['id'],
                            ];
                        }
                    } else {
                        $areas = explode('|', $area_str);
                        $areas = array_filter($areas);
                        foreach ($areas as $area_name) {
                            if (!array_key_exists($area_name, $all_areas)) {
                                $str = $admin_id . ' ' . $tell . ' ' . $area_name . ' 地区不存在';
                                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, $str);
                            }
                            $config_area_data[] = [
                                'admin_id'  => $admin_id,
                                'parent_id' => 0,
                                'area_id'   => $all_areas[$area_name]['id'],
                            ];
                        }
                    }
                }
                if ('地区' == $type) {
                    //对接地区
                    //地区匹配
                    if (empty($area_str)) {
                        //空代表全部
                        foreach ($all_areas as $area) {
                            $config_area_data[] = [
                                'admin_id'  => $admin_id,
                                'parent_id' => 0,
                                'area_id'   => $area['id'],
                            ];
                        }
                    } else {
                        $areas = explode('|', $area_str);
                        $areas = array_filter($areas);
                        foreach ($areas as $area_name) {
                            if (!array_key_exists($area_name, $all_areas)) {
                                $str = $admin_id . ' ' . $tell . ' ' . $area_name . ' 地区不存在';
                                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, $str);
                            }
                            $config_area_data[] = [
                                'admin_id'  => $admin_id,
                                'parent_id' => 0,
                                'area_id'   => $all_areas[$area_name]['id'],
                            ];
                        }
                    }
                }

                $config_data[] = $config;

                //工作日
                if (!empty($weekdays)) {
                    $weekdays = explode('|', $weekdays);
                    $weekdays = array_filter($weekdays);

                    foreach ($weekdays as $weekday) {
                        $weekday = 7 == $weekday ? 0 : $weekday;
                        if (array_key_exists($weekday, $workdays)) {
                            $workdays[$weekday]['is_on_duty'] = AdminConfigReceiveWorkdayService::IS_ON_DUTY_YES;
                        }
                    }
                }
                $config_workday_data = array_merge($config_workday_data, $workdays);

            }

            $model = M();

            $model->execute('truncate admin_config_receive');
            $model->execute('truncate admin_config_receive_factory');
            $model->execute('truncate admin_config_receive_category');
            $model->execute('truncate admin_config_receive_area');
            $model->execute('truncate admin_config_receive_workday');
            $model->execute('truncate admin_config_receive_partner');
            $model->execute('truncate admin_config_receive_factory_group');

            M()->startTrans();
            if (!empty($config_data)) {
                BaseModel::getInstance('admin_config_receive')
                    ->insertAll($config_data);
            }
            if (!empty($config_factory_data)) {
                BaseModel::getInstance('admin_config_receive_factory')
                    ->insertAll($config_factory_data);
            }
            if (!empty($config_category_data)) {
                BaseModel::getInstance('admin_config_receive_category')
                    ->insertAll($config_category_data);
            }
            if (!empty($config_area_data)) {
                BaseModel::getInstance('admin_config_receive_area')
                    ->insertAll($config_area_data);
            }
            if (!empty($config_workday_data)) {
                BaseModel::getInstance('admin_config_receive_workday')
                    ->insertAll($config_workday_data);
            }
            if (!empty($config_partner_data)) {
                BaseModel::getInstance('admin_config_receive_partner')
                    ->insertAll($config_partner_data);
            }
            if (!empty($config_factory_group_data)) {
                BaseModel::getInstance('admin_config_receive_factory_group')
                    ->insertAll($config_factory_group_data);
            }

            M()->commit();

            $this->okNull();

        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    protected function getAdmins($tells)
    {
        if (empty($tells)) {
            return [];
        }

        $opts = [
            'field' => 'id,tell',
            'where' => ['tel' => ['in', $tells]],
        ];

        $data = [];

        $model = BaseModel::getInstance('admin');
        $list = $model->getList($opts);

        foreach ($list as $val) {
            $tell = $val['tell'];

            $data[$tell] = $val;
        }

        return $data;

    }

    protected function getAreas()
    {
        $opts = [
            'field' => 'id,name',
            'where' => ['parent_id' => 0],
        ];

        $data = [];

        $model = BaseModel::getInstance('area');
        $list = $model->getList($opts);

        foreach ($list as $val) {
            $name = trim($val['name']);

            $data[$name] = $val;
        }

        return $data;
    }

    protected function getCategories()
    {
        $opts = [
            'field' => 'list_item_id as id,item_desc as name',
            'where' => ['list_id' => 12, 'item_parent' => 0],
        ];

        $data = [];

        $model = BaseModel::getInstance('cm_list_item');
        $list = $model->getList($opts);

        foreach ($list as $val) {
            $name = trim($val['name']);

            $data[$name] = $val;
        }

        return $data;
    }

    protected function getFactories()
    {
        $opts = [
            'field' => 'factory_id as id,linkphone',
        ];

        $data = [];

        $model = BaseModel::getInstance('factory');
        $list = $model->getList($opts);

        foreach ($list as $val) {
            $linkphone = trim($val['linkphone']);

            $data[$linkphone] = $val;
        }

        return $data;
    }

    protected function getGroups()
    {
        return [
            'A组' => 0,
            'B组' => 1,
            'C组' => 2,
            'D组' => 3,
            'E组' => 4,
            'F组' => 5,
        ];
    }
    **/

}