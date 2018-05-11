<?php
/**
 * File: AdminLogic.class.php
 * Function:
 * User: sakura
 * Date: 2018/2/12
 */

namespace Admin\Logic;

use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Carbon\Carbon;
use Common\Common\CacheModel\AdminCacheModel;
use Common\Common\CacheModel\AdminGroupCacheModel;
use Common\Common\CacheModel\AdminRoleCacheModel;
use Common\Common\CacheModel\AreaCacheModel;
use Common\Common\CacheModel\CacheModel;
use Common\Common\CacheModel\CmListItemCacheModel;
use Common\Common\CacheModel\FactoryCacheModel;
use Common\Common\ResourcePool\RedisPool;
use Common\Common\Service\AdminConfigReceiveService;
use Common\Common\Service\AdminConfigReceiveWorkdayService;
use Common\Common\Service\AdminGroupService;
use Common\Common\Service\AdminRoleService;
use Common\Common\Service\AdminService;
use Common\Common\Service\AuthService;
use Common\Common\Service\FactoryService;
use Common\Common\Service\FrontendRoutingService;
use Illuminate\Support\Arr;
use Library\Common\Util;

class AdminLogic extends BaseLogic
{
    /**
     * @param $admin_id
     * @return array|null
     */
    public function getAdminReveiceType($admin_id)
    {
        $receive_order_type = null;
        $admin_roels_id = AdminRoleCacheModel::getRelation($admin_id, 'rel_admin_roles', 'admin_id', 'admin_roles_id');
        foreach ($admin_roels_id as $v) {
            $role = AdminRoleCacheModel::getOne($v, 'type');
            if (AdminRoleService::AUTO_RECEIVE_TYPE_CHECKER & $role['type']) {
                $receive_order_type[] = AdminRoleService::AUTO_RECEIVE_TYPE_CHECKER;
            } elseif (AdminRoleService::AUTO_RECEIVE_TYPE_DISTRIBUTOR & $role['type']) {
                $receive_order_type[] = AdminRoleService::AUTO_RECEIVE_TYPE_DISTRIBUTOR;
            } elseif (AdminRoleService::AUTO_RECEIVE_TYPE_RETURNEE & $role['type']) {
                $receive_order_type[] = AdminRoleService::AUTO_RECEIVE_TYPE_RETURNEE;
            } elseif (AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR & $role['type']) {
                $receive_order_type[] = AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR;
            }
        }

        return $receive_order_type;
    }

    /**
     * @param $id
     */
    public function getFrontendRoutingTrees($id)
    {
        $tree = [];
        $is_super = false;
        $frontend_routings = AdminCacheModel::getAdminAllFrontendRoutings($id, $is_super);

        if ($is_super) {
            $key_arr = [];
            foreach ($frontend_routings as $v) {
                $key_arr[$v['parent_id']][] = $v;
            }

            $tree = $key_arr[0];
            keyArrTreeData($key_arr, $tree);
        } elseif ($frontend_routings) {
            $all_frontend_routings = BaseModel::getInstance('frontend_routing')
                ->getList([
                    'field' => 'id,routing,name,is_show,is_menu,parent_id,serial,create_time',
                    'where' => [
                        'is_delete' => FrontendRoutingService::IS_DELETE_NO,
                    ],
                    'index' => 'id',
                ]);

            $data = [];
            foreach ($frontend_routings as $k => $v) {
                $data[$v['id']] = $v;
            }
            $tree = keyArrFindNeedTreeData($all_frontend_routings, $data);
        }

        return $tree;
    }

    public function add($param)
    {
        $tell = $param['tell'];
        $tell_out = $param['tell_out'];
        $nickout = $param['nickout'];
        $user_name = $param['user_name'];
        $thumb = empty($param['thumb']) ? '' : $param['thumb'];
        $state = $param['state'];
        $agent = $param['agent'];
        $is_limit_ip = $param['is_limit_ip'];

        $is_auto_receive = $param['is_auto_receive'];

        //角色
        $role_ids = empty($param['role_ids']) ? [] : array_unique($param['role_ids']);

        //组别
        $group_ids = empty($param['group_ids']) ? [] : array_unique($param['group_ids']);

        if (empty($tell) || empty($nickout) || empty($role_ids) || empty($user_name)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }
        if (!in_array($state, AdminRoleService::STATE_VALID_ARRAY)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '状态错误');
        }
        if (!Util::isPhone($tell)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '手机号格式错误');
        }
        if (!in_array($is_auto_receive, AdminConfigReceiveService::IS_AUTO_RECEIVE_VALID_ARRAY)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }

        //检查账号是否已注册
        $admin_model = BaseModel::getInstance('admin');
        $admin_info = $admin_model->getOne([
            'tell' => $tell,
        ]);
        if (!empty($admin_info)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '手机号已注册');
        }

        $password = md5(substr($tell, -6));

        //检查角色
        $roles = $this->getRoles($role_ids);
        if (empty($roles)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '角色不存在');
        }
        $role_ids = array_column($roles, 'id'); // 过滤不存在角色id

        $total_type = 0; // 当前客服可接单总类型
        foreach ($roles as $info) {
            $type = $info['type'];

            $total_type = $total_type | $type;
        }

        //检查组别
        $groups = $this->getGroups($group_ids);
        $group_ids = empty($groups) ? [] : array_column($groups, 'id');

        //不使用CacheModel的方法插入是防止 程序中途报错回滚缓存无法删除,留下脏数据
        $admin_id = $admin_model->insert([
            'user_name' => $user_name,
            'nickout'   => $nickout,
            'tell'      => $tell,
            'tell_out'  => $tell_out,
            'password'  => $password,
            'add_time'  => NOW_TIME,
            'thumb'     => $thumb,
            'state'     => $state,
            'agent'     => $agent,
            'is_limit_ip' => $is_limit_ip,
        ]);
        $admin_info = $admin_model->getOneOrFail($admin_id);

        //角色关系
        $insert_data = [];
        foreach ($role_ids as $role_id) {
            $insert_data[] = [
                'admin_id'       => $admin_id,
                'admin_roles_id' => $role_id,
            ];
        }
        $role_model = BaseModel::getInstance('rel_admin_roles');
        $role_model->insertAll($insert_data);

        //组别关系
        $group_model = BaseModel::getInstance('rel_admin_group');
        if (!empty($group_ids)) {
            $insert_data = [];
            foreach ($group_ids as $group_id) {
                $insert_data[] = [
                    'admin_id'       => $admin_id,
                    'admin_group_id' => $group_id,
                ];
            }
            $group_model->insertAll($insert_data);
        }

        $max_receive_times = 0;
        $receive_type = 0;
        $workdays = [];
        for ($i = 0; $i < 7; $i++) {
            $workdays[$i] = [
                'workday'    => $i,
                'admin_id'   => $admin_id,
                'is_on_duty' => AdminConfigReceiveWorkdayService::IS_ON_DUTY_NO,
            ];
        }
        if (AdminConfigReceiveService::IS_AUTO_RECEIVE_YES == $is_auto_receive) {
            //设置接单
            $receive_type = $param['receive_type'];
            if (!in_array($receive_type, AdminConfigReceiveService::TYPE_VALID_ARRAY)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '接单模式错误');
            }
            //检查客服所属角色是否工单客服和财务客服重叠
            if (
                (AdminConfigReceiveService::TYPE_TAKE_TURN == $receive_type &&
                    $total_type > AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR) ||
                (AdminConfigReceiveService::TYPE_TAKE_TURN != $receive_type &&
                    $total_type >= AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR)
            ) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '客服不能同时属于工单客服和财务客服');
            }

            $custom_workdays = $param['workdays'];

            $auditor_type = AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR;
            $worker_order_type = AdminRoleService::AUTO_RECEIVE_TYPE_CHECKER | AdminRoleService::AUTO_RECEIVE_TYPE_DISTRIBUTOR | AdminRoleService::AUTO_RECEIVE_TYPE_RETURNEE;
            if (AdminConfigReceiveService::TYPE_TAKE_TURN == $receive_type) {
                //财务客服
                if (0 == ($total_type & $auditor_type)) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '客服所属角色不能接工单');
                }

            } else {
                //工单客服
                if (0 == ($total_type & $worker_order_type)) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '客服所属角色不能接工单');
                }
                $max_receive_times = $param['max_receive_times'];
                if (0 > $max_receive_times) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '最大接单量错误');
                }

                //检查地区
                $area_ids = empty($param['area_ids'])? []: array_unique($param['area_ids']);
                if (empty($area_ids)) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '地区为空');
                }
                $area_ids = BaseModel::getInstance('area')
                    ->getFieldVal(['id' => ['in', $area_ids]], 'id', true);
                if (empty($area_ids)) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '地区不存在');
                }

                $insert_data = [];
                foreach ($area_ids as $area_id) {
                    $insert_data[] = [
                        'area_id'   => $area_id,
                        'admin_id'  => $admin_id,
                        'parent_id' => 0,
                    ];
                }
                BaseModel::getInstance('admin_config_receive_area')
                    ->insertAll($insert_data);

                $partner_ids = empty($param['partner_ids']) ? [] : array_unique($param['partner_ids']);
                if (!empty($partner_ids)) {
                    $rel_table_name = 'rel_admin_roles';
                    foreach ($partner_ids as $partner_id) {
                        $partner_role_ids = AdminCacheModel::getRelation($partner_id, $rel_table_name, 'admin_id', 'admin_roles_id');
                        $intersect = array_intersect($role_ids, $partner_role_ids);
                        if (empty($intersect)) {
                            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '对接人客服角色不对应');
                        }
                    }
                    $insert_data = [];
                    foreach ($partner_ids as $partner_id) {
                        $insert_data[] = [
                            'partner_admin_id' => $partner_id,
                            'admin_id'         => $admin_id,
                        ];
                    }
                    BaseModel::getInstance('admin_config_receive_partner')
                        ->insertAll($insert_data);
                }

                if (AdminConfigReceiveService::TYPE_FACTORY == $receive_type) {
                    //对接厂家
                    //检查厂家
                    $factory_ids = $param['factory_ids'];
                    if (empty($factory_ids)) {
                        $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '厂家为空');
                    }
                    $factory_ids = BaseModel::getInstance('factory')
                        ->getFieldVal(['factory_id' => ['in', $factory_ids]], 'factory_id', true);
                    if (empty($factory_ids)) {
                        $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '厂家不存在');
                    }

                    $insert_data = [];
                    foreach ($factory_ids as $factory_id) {
                        $insert_data[] = [
                            'factory_id' => $factory_id,
                            'admin_id'   => $admin_id,
                        ];
                    }
                    BaseModel::getInstance('admin_config_receive_factory')
                        ->insertAll($insert_data);

                } elseif (AdminConfigReceiveService::TYPE_CATEGORY == $receive_type) {
                    //对接品类

                    //检查品类
                    $category_ids = $param['category_ids'];
                    if (empty($category_ids)) {
                        $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '品类为空');
                    }
                    $category_ids = BaseModel::getInstance('cm_list_item')
                        ->getFieldVal([
                            'list_item_id' => ['in', $category_ids],
                        ], 'list_item_id', true);
                    if (empty($category_ids)) {
                        $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '品类不存在');
                    }

                    $insert_data = [];
                    foreach ($category_ids as $category_id) {
                        $insert_data[] = [
                            'category_id' => $category_id,
                            'admin_id'    => $admin_id,
                            'parent_id'   => 0, // 默认是顶级
                        ];
                    }
                    BaseModel::getInstance('admin_config_receive_category')
                        ->insertAll($insert_data);

                } elseif (AdminConfigReceiveService::TYPE_AREA == $receive_type) {
                    //对接地区

                } elseif (AdminConfigReceiveService::TYPE_FACTORY_GROUP == $receive_type) {
                    //对接厂家组别
                    $factory_group_ids = empty($param['factory_group_ids']) ? [] : array_unique($param['factory_group_ids']);
                    if (empty($factory_group_ids)) {
                        $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '厂家组别为空');
                    }

                    $valid_factory_group_ids = empty(FactoryService::FACTORY_GROUP) ? [] : array_column(FactoryService::FACTORY_GROUP, 'id');
                    $diff = array_diff($factory_group_ids, $valid_factory_group_ids);
                    if (!empty($diff)) {
                        $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '部分厂家组别不存在');
                    }

                    $insert_data = [];
                    foreach ($factory_group_ids as $factory_group_id) {
                        $insert_data[] = [
                            'group_id' => $factory_group_id,
                            'admin_id' => $admin_id,
                        ];
                    }
                    BaseModel::getInstance('admin_config_receive_factory_group')
                        ->insertAll($insert_data);
                }

            }

            if (!empty($custom_workdays)) {
                foreach ($custom_workdays as $custom_workday) {
                    if (isset($workdays[$custom_workday])) {
                        $workdays[$custom_workday]['is_on_duty'] = AdminConfigReceiveWorkdayService::IS_ON_DUTY_YES;
                    }
                }
            }

        }

        BaseModel::getInstance('admin_config_receive')->insert([
            'admin_id'          => $admin_id,
            'type'              => $receive_type,
            'is_auto_receive'   => $is_auto_receive,
            'max_receive_times' => $max_receive_times,
        ]);

        BaseModel::getInstance('admin_config_receive_workday')
            ->insertAll($workdays);

        //建立缓存
        CacheModel::startTrans();
        AdminCacheModel::addCache($admin_id, $admin_info); // 客服
        AdminCacheModel::addAdminRoleRelation($admin_id); // 角色关系表
        AdminCacheModel::addAdminGroupRelation($admin_id); // 组别关系表
        CacheModel::commit();

    }

    protected function getRoles($role_ids)
    {
        if (empty($role_ids)) {
            return [];
        }

        $data = [];
        $field = 'id,is_disable,is_delete,type';
        foreach ($role_ids as $role_id) {
            $data[$role_id] = AdminRoleCacheModel::getOne($role_id, $field);
        }

        return $data;

    }

    protected function getGroups($group_ids)
    {
        if (empty($group_ids)) {
            return [];
        }

        $data = [];
        $field = 'id,is_disable,is_delete,type';
        foreach ($group_ids as $group_id) {
            $data[$group_id] = AdminGroupCacheModel::getOne($group_id, $field);
        }

        return $data;

    }

    public function edit($param, $receive_config_key = [])
    {
        $admin_id = $param['id'];

        $admin_info = BaseModel::getInstance('admin')->getOne($admin_id);

        $tell        = isset($param['tell'])      ? $param['tell']      : $admin_info['tell'];
        $tell_out    = isset($param['tell_out'])  ? $param['tell_out']  : $admin_info['tell_out'];
        $nickout     = isset($param['nickout'])   ? $param['nickout']   : $admin_info['nickout'];
        $user_name   = isset($param['user_name']) ? $param['user_name'] : $admin_info['user_name'];
        $thumb       = isset($param['thumb'])     ? $param['thumb']     : $admin_info['thumb'];
        $state       = isset($param['state'])     ? $param['state']     : $admin_info['state'];
        $role_ids    = isset($param['role_ids'])  ? $param['role_ids']  : BaseModel::getInstance('rel_admin_roles')->getFieldVal(['admin_id' => $admin_id], 'admin_roles_id', true);
        $agent       = isset($param['agent'])     ? $param['agent']     : $admin_info['agent'];

        $is_limit_ip = isset($param['is_limit_ip']) ? $param['is_limit_ip'] : $admin_info['is_limit_ip'];

        $receive_model = BaseModel::getInstance('admin_config_receive');
        $is_auto_receive = isset($param['is_auto_receive']) ? $param['is_auto_receive'] : $receive_model->getFieldVal($admin_id, 'is_auto_receive');

        $role_ids = empty($role_ids) ? [] : array_unique($role_ids);

        $group_ids = isset($param['group_ids']) ? $param['group_ids'] : BaseModel::getInstance('rel_admin_group')->getFieldVal(['admin_id' => $admin_id], 'admin_group_id', true);
        $group_ids = empty($group_ids) ? [] : array_unique($group_ids);

        if (empty($admin_id)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        if (empty($tell) || empty($nickout) || empty($role_ids) || empty($user_name)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
        }

        if (!in_array($state, AdminRoleService::STATE_VALID_ARRAY)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '状态错误');
        }

        if (!in_array($is_auto_receive, AdminConfigReceiveService::IS_AUTO_RECEIVE_VALID_ARRAY)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR);
        }

        if (BaseModel::getInstance('admin')->dataExist([
            'tell' => $tell,
            'id' => ['neq', $admin_id],
        ])
        ) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '手机号已占用');
        }

        //获取管理员,检查管理员是否存在
        $admin_info = AdminCacheModel::getOneOrFail($admin_id, 'id');

        $roles = $this->getRoles($role_ids);
        if (empty($roles)) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '角色为空');
        }
        $role_ids = array_column($roles, 'id'); // 过滤不存在角色id

        $total_type = 0; // 当前客服可接单总类型
        foreach ($roles as $info) {

            $type = $info['type'];

            $total_type = $total_type | $type;
        }

        //检查组别
        $groups = $this->getGroups($group_ids);
        $group_ids = empty($groups) ? [] : array_column($groups, 'id'); // 过滤不存在组别

        $admin_model = BaseModel::getInstance('admin');
        $admin_model->update($admin_id, [
            'user_name' => $user_name,
            'nickout' => $nickout,
            'tell_out' => $tell_out,
            'thumb' => $thumb,
            'state' => $state,
            'agent' => $agent,
            'is_limit_ip' => $is_limit_ip,
        ]);
        AdminCacheModel::removeCache($admin_id);
        $admin_info = $admin_model->getOneOrFail($admin_id);

        //角色
        $role_model = BaseModel::getInstance('rel_admin_roles');
        $opts = [
            'where' => [
                'admin_id' => $admin_id,
            ],
        ];
        $prev_role_ids = $role_model->getFieldVal($opts, 'admin_roles_id', true); // 旧角色
        $prev_role_ids = empty($prev_role_ids) ? [] : $prev_role_ids;

        $new_role_ids = array_diff($role_ids, $prev_role_ids); // 新角色
        $del_role_ids = array_diff($prev_role_ids, $role_ids); // 删除角色
        if (!empty($new_role_ids)) {
            $insert_data = [];
            foreach ($new_role_ids as $role_id) {
                $insert_data[] = [
                    'admin_id' => $admin_id,
                    'admin_roles_id' => $role_id,
                ];
            }
            $role_model->insertAll($insert_data);
        }
        if (!empty($del_role_ids)) {
            $where = [
                'admin_id' => $admin_id,
                'admin_roles_id' => ['in', $del_role_ids],
            ];
            $role_model->remove($where);
        }
        if (!empty($new_role_ids) || !empty($del_role_ids)) {
            AdminCacheModel::removeAdminRoleRelation($admin_id);
        }

        //组别
        $group_model = BaseModel::getInstance('rel_admin_group');
        $opts = [
            'where' => [
                'admin_id' => $admin_id,
            ],
        ];
        $prev_group_ids = $group_model->getFieldVal($opts, 'admin_group_id', true); // 旧组别
        $prev_group_ids = empty($prev_group_ids) ? [] : $prev_group_ids;

        $new_group_ids = array_diff($group_ids, $prev_group_ids); // 新组别
        $del_group_ids = array_diff($prev_group_ids, $group_ids); // 删除组别
        if (!empty($new_group_ids)) {
            $insert_data = [];
            foreach ($new_group_ids as $group_id) {
                $insert_data[] = [
                    'admin_id' => $admin_id,
                    'admin_group_id' => $group_id,
                ];
            }
            $group_model->insertAll($insert_data);
        }
        if (!empty($del_group_ids)) {
            $where = [
                'admin_id' => $admin_id,
                'admin_group_id' => ['in', $del_group_ids],
            ];
            $group_model->remove($where);
        }
        if (!empty($new_group_ids) || !empty($del_group_ids)) {
            AdminCacheModel::removeAdminGroupRelation($admin_id);
        }

        $update_admin_config = [
            'is_auto_receive' => $is_auto_receive,
        ];
//        $receive_model = BaseModel::getInstance('admin_config_receive');
        $receive_config = $receive_model->getOneOrFail($admin_id);
        $prev_receive_type = $receive_config['type'];
        if (AdminConfigReceiveService::IS_AUTO_RECEIVE_YES == $is_auto_receive) {
            //设置接单

            $receive_type = isset($param['receive_type']) ? $param['receive_type'] : BaseModel::getInstance('admin_config_receive')->getFieldVal($admin_id, 'type');
            $update_admin_config['type'] = $receive_type;

            if (!in_array($receive_type, AdminConfigReceiveService::TYPE_VALID_ARRAY)) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '接单模式错误');
            }
            //检查客服所属角色是否工单客服和财务客服重叠
            if (
                (AdminConfigReceiveService::TYPE_TAKE_TURN == $receive_type &&
                    $total_type > AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR) ||
                (AdminConfigReceiveService::TYPE_TAKE_TURN != $receive_type &&
                    $total_type >= AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR)
            ) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '客服不能同时属于工单客服和财务客服');
            }

            $custom_workdays = $param['workdays'];

            $auditor_type = AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR;
            $worker_order_type = AdminRoleService::AUTO_RECEIVE_TYPE_CHECKER | AdminRoleService::AUTO_RECEIVE_TYPE_DISTRIBUTOR | AdminRoleService::AUTO_RECEIVE_TYPE_RETURNEE;

            if (AdminConfigReceiveService::TYPE_TAKE_TURN == $receive_type) {
                //财务客服
                if (0 == ($total_type & $auditor_type)) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '客服所属角色不能接工单');
                }
                $update_admin_config['max_receive_times'] = 0;
            } else {
                //工单客服
                if (0 == ($total_type & $worker_order_type)) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '客服所属角色不能接工单');
                }

                $max_receive_times = $param['max_receive_times'];
                if (0 > $max_receive_times) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '最大接单量错误');
                }
                $update_admin_config['max_receive_times'] = $max_receive_times;

                //检查地区
                if (isset($param['area_ids'])) {
                    $area_ids = empty($param['area_ids']) ? [] : array_unique($param['area_ids']);
                } else {
                    $area_ids = BaseModel::getInstance('admin_config_receive_area')->getFieldVal(['admin_id' => $admin_id], 'area_id', true);
                }

                if (empty($area_ids)) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '地区id为空');
                }
                $area_ids = BaseModel::getInstance('area')
                    ->getFieldVal(['id' => ['in', $area_ids]], 'id', true);
                if (empty($area_ids)) {
                    $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '地区不存在');
                }

                $area_model = BaseModel::getInstance('admin_config_receive_area');

                $prev_area_ids = $area_model->getFieldVal(['admin_id' => $admin_id], 'area_id', true);
                $prev_area_ids = empty($prev_area_ids) ? [] : $prev_area_ids;

                $new_area_ids = array_diff($area_ids, $prev_area_ids);
                $del_area_ids = array_diff($prev_area_ids, $area_ids);

                if (!empty($del_area_ids)) {
                    $area_model->remove([
                        'admin_id' => $admin_id,
                        'area_id' => ['in', $del_area_ids],
                    ]);
                }

                if (!empty($new_area_ids)) {
                    $insert_data = [];
                    foreach ($area_ids as $area_id) {
                        $insert_data[] = [
                            'area_id' => $area_id,
                            'admin_id' => $admin_id,
                            'parent_id' => 0,
                        ];
                    }
                    $area_model->insertAll($insert_data);
                }
                //对接人
                $partner_ids = $param['partner_ids'];
                $partner_ids = empty($partner_ids) ? [] : array_unique($partner_ids);

                $rel_table_name = 'rel_admin_roles';
                foreach ($partner_ids as $partner_id) {
                    $partner_role_ids = AdminCacheModel::getRelation($partner_id, $rel_table_name, 'admin_id', 'admin_roles_id');
                    $intersect = array_intersect($role_ids, $partner_role_ids);
                    if (empty($intersect)) {
                        $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '对接人客服角色不对应');
                    }
                }

                $partner_model = BaseModel::getInstance('admin_config_receive_partner');
                $prev_partner_admin_ids = $partner_model->getFieldVal(['admin_id' => $admin_id], 'partner_admin_id', true);
                $prev_partner_admin_ids = empty($prev_partner_admin_ids)? []: $prev_partner_admin_ids;
                $new_partner_ids = array_diff($partner_ids, $prev_partner_admin_ids);
                $del_partner_ids = array_diff($prev_partner_admin_ids, $partner_ids);
                if (!empty($new_partner_ids)) {
                    $insert_data = [];
                    foreach ($new_partner_ids as $new_partner_id) {
                        $insert_data[] = [
                            'partner_admin_id' => $new_partner_id,
                            'admin_id'         => $admin_id,
                        ];
                    }
                    $partner_model->insertAll($insert_data);
                }
                if (!empty($del_partner_ids)) {
                    $partner_model->remove([
                        'admin_id'         => $admin_id,
                        'partner_admin_id' => ['in', $del_partner_ids],
                    ]);
                }

                if (AdminConfigReceiveService::TYPE_FACTORY == $receive_type) {
                    //对接厂家
                    //检查厂家
                    $factory_ids = $param['factory_ids'];
                    if (empty($factory_ids)) {
                        $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '厂家id为空');
                    }
                    $factory_ids = BaseModel::getInstance('factory')
                        ->getFieldVal(['factory_id' => ['in', $factory_ids]], 'factory_id', true);
                    if (empty($factory_ids)) {
                        $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '厂家不存在');
                    }

                    $factory_model = BaseModel::getInstance('admin_config_receive_factory');

                    $prev_factory_ids = $factory_model->getFieldVal(['admin_id' => $admin_id], 'factory_id', true);
                    $prev_factory_ids = empty($prev_factory_ids) ? [] : $prev_factory_ids;

                    $new_factory_ids = array_diff($factory_ids, $prev_factory_ids);
                    $del_factory_ids = array_diff($prev_factory_ids, $factory_ids);

                    if (!empty($del_factory_ids)) {
                        $factory_model->remove([
                            'admin_id' => $admin_id,
                            'factory_id' => ['in', $del_factory_ids],
                        ]);
                    }

                    if (!empty($new_factory_ids)) {
                        $insert_data = [];
                        foreach ($new_factory_ids as $factory_id) {
                            $insert_data[] = [
                                'factory_id' => $factory_id,
                                'admin_id' => $admin_id,
                            ];
                        }
                        $factory_model->insertAll($insert_data);
                    }
                } elseif (AdminConfigReceiveService::TYPE_CATEGORY == $receive_type) {
                    //对接品类
                    //检查品类
                    $category_ids = isset($param['category_ids']) ? $param['category_ids'] : BaseModel::getInstance('admin_config_receive_category')->getFieldVal(['admin_id' => $admin_id], 'category_id', true);
                    if (empty($category_ids)) {
                        $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '品类id为空');
                    }
                    $category_ids = BaseModel::getInstance('cm_list_item')
                        ->getFieldVal([
                            'list_item_id' => ['in', $category_ids],
                        ], 'list_item_id', true);
                    if (empty($category_ids)) {
                        $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '品类不存在');
                    }

                    $category_model = BaseModel::getInstance('admin_config_receive_category');

                    $prev_category_ids = $category_model->getFieldVal(['admin_id' => $admin_id], 'category_id', true);
                    $prev_category_ids = empty($prev_category_ids) ? [] : $prev_category_ids;

                    $new_category_ids = array_diff($category_ids, $prev_category_ids);
                    $del_category_ids = array_diff($prev_category_ids, $category_ids);

                    if (!empty($del_category_ids)) {
                        $category_model->remove([
                            'admin_id' => $admin_id,
                            'category_id' => ['in', $del_category_ids],
                        ]);
                    }

                    if (!empty($new_category_ids)) {
                        $insert_data = [];
                        foreach ($new_category_ids as $category_id) {
                            $insert_data[] = [
                                'category_id' => $category_id,
                                'admin_id' => $admin_id,
                                'parent_id' => 0,
                            ];
                        }
                        $category_model->insertAll($insert_data);
                    }

                } elseif (AdminConfigReceiveService::TYPE_AREA == $receive_type) {

                } elseif (AdminConfigReceiveService::TYPE_FACTORY_GROUP == $receive_type) {

                    if (isset($param['factory_group_ids'])) {
                        $factory_group_ids = empty($param['factory_group_ids']) ? [] : array_unique($param['factory_group_ids']);
                    } else {
                        $factory_group_ids = BaseModel::getInstance('admin_config_receive_factory_group')->getFieldVal(['admin_id' => $admin_id], 'group_id', true);
                    }

                    if (empty($factory_group_ids)) {
                        $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '厂家组别为空');
                    }
                    $valid_factory_group_ids = empty(FactoryService::FACTORY_GROUP) ? [] : array_column(FactoryService::FACTORY_GROUP, 'id');
                    $diff = array_diff($factory_group_ids, $valid_factory_group_ids);
                    if (!empty($diff)) {
                        $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '部分厂家组别不存在');
                    }

                    $group_model = BaseModel::getInstance('admin_config_receive_factory_group');

                    $prev_factory_group_ids = $group_model->getFieldVal(['admin_id' => $admin_id], 'group_id', true);
                    $prev_factory_group_ids = empty($prev_factory_group_ids) ? [] : $prev_factory_group_ids;

                    $new_factory_group_ids = array_diff($factory_group_ids, $prev_factory_group_ids);
                    $del_factory_group_ids = array_diff($prev_factory_group_ids, $factory_group_ids);

                    if (!empty($del_factory_group_ids)) {
                        $group_model->remove([
                            'admin_id' => $admin_id,
                            'group_id' => ['in', $del_factory_group_ids],
                        ]);
                    }

                    if (!empty($new_factory_group_ids)) {
                        $insert_data = [];
                        foreach ($new_factory_group_ids as $factory_group_id) {
                            $insert_data[] = [
                                'group_id' => $factory_group_id,
                                'admin_id' => $admin_id,
                            ];
                        }
                        $group_model->insertAll($insert_data);
                    }
                }
            }

            //清空旧配置
            if ($prev_receive_type != $receive_type) {
                if (AdminConfigReceiveService::TYPE_TAKE_TURN == $prev_receive_type) {
                    //财务客服

                } elseif (AdminConfigReceiveService::TYPE_FACTORY == $prev_receive_type) {
                    //对接厂家
                    BaseModel::getInstance('admin_config_receive_factory')
                        ->remove([
                            'admin_id' => $admin_id,
                        ]);

                } elseif (AdminConfigReceiveService::TYPE_CATEGORY == $prev_receive_type) {
                    //对接品类
                    BaseModel::getInstance('admin_config_receive_category')
                        ->remove([
                            'admin_id' => $admin_id,
                        ]);

                } elseif (AdminConfigReceiveService::TYPE_AREA == $prev_receive_type) {
                    //对接地区
                    BaseModel::getInstance('admin_config_receive_area')
                        ->remove([
                            'admin_id' => $admin_id,
                        ]);
                } elseif (AdminConfigReceiveService::TYPE_FACTORY_GROUP == $receive_type) {
                    BaseModel::getInstance('admin_config_receive_factory_group')
                        ->remove([
                            'admin_id' => $admin_id,
                        ]);
                }
            }

            //当前提交的工作日
            $workday_all = range(0, 6);
            $custom_workdays = empty($custom_workdays) ? [] : $custom_workdays;
            $custom_workdays = array_intersect($workday_all, $custom_workdays);
            $rest_days = array_diff($workday_all, $custom_workdays);

            //未修改前工作日
            $workday_model = BaseModel::getInstance('admin_config_receive_workday');
            $prev_custom_workdays = $workday_model->getList(['admin_id' => $admin_id], 'is_on_duty,workday');
            $prev_workdays = [];
            $prev_rest_days = [];
            foreach ($prev_custom_workdays as $custom_workday) {
                $is_on_duty = $custom_workday['is_on_duty'];
                if (AdminConfigReceiveWorkdayService::IS_ON_DUTY_YES == $is_on_duty) {
                    $prev_workdays[] = $custom_workday['workday'];
                } else {
                    $prev_rest_days[] = $custom_workday['workday'];
                }
            }

            $diff_workdays = array_diff($custom_workdays, $prev_workdays);
            if (!empty($diff_workdays)) {
                $workday_model->update([
                    'admin_id' => $admin_id,
                    'workday'  => ['in', $diff_workdays],
                ], [
                    'is_on_duty' => AdminConfigReceiveWorkdayService::IS_ON_DUTY_YES,
                ]);
            }
            $diff_rest_days = array_diff($rest_days, $prev_rest_days);
            if (!empty($diff_rest_days)) {
                $workday_model->update([
                    'admin_id' => $admin_id,
                    'workday'  => ['in', $diff_rest_days],
                ], [
                    'is_on_duty' => AdminConfigReceiveWorkdayService::IS_ON_DUTY_NO,
                ]);
            }

        }

        $receive_model->update($admin_id, $update_admin_config);

        //更新缓存
        CacheModel::startTrans();

        AdminCacheModel::addCache($admin_id, $admin_info);
        AdminCacheModel::addAdminRoleRelation($admin_id);
        AdminCacheModel::addAdminGroupRelation($admin_id);

        $weekday = Carbon::now()->dayOfWeek;

//        $key = sprintf(C('AUTO_RECEIVE_WORKER_ORDER.ADMIN_LIST_FACTORY_KEY_TPL'), $weekday);
//        $register_key = sprintf(C('AUTO_RECEIVE_INIT.REGISTER'), $weekday);
//        RedisPool::getInstance()->sPop($register_key, $key);

        CacheModel::commit();

    }

    public function info($param)
    {
        $admin_id = $param['id'];

        $field = 'id,user_name,nickout,tell,tell_out,thumb,state,last_login_time,add_time,agent,is_limit_ip';
        $admin_info = AdminCacheModel::getOneOrFail($admin_id, $field);

        $rel_group_table = 'rel_admin_group';
        $group_ids = AdminCacheModel::getRelation($admin_id, $rel_group_table, 'admin_id', 'admin_group_id');
        $groups = empty($group_ids) ? null : $group_ids;
        $group_names = [];
        foreach ($group_ids as $group_id) {
            $info = AdminGroupCacheModel::getOneOrFail($group_id, 'name,is_disable');
            if (AdminGroupService::IS_DELETE_NO == $info['is_disable']) {
                $group_names[] = $info['name'];
            }
        }
        $admin_info['group_names'] = empty($group_names) ? null : $group_names;

        $admin_info['group_ids'] = $groups;
        $admin_info['thumb_url'] = Util::getServerFileUrl($admin_info['thumb']);

        $rel_role_table = 'rel_admin_roles';
        $role_ids = AdminCacheModel::getRelation($admin_id, $rel_role_table, 'admin_id', 'admin_roles_id');
        $role_ids = empty($role_ids) ? null : $role_ids;
        $admin_info['role_ids'] = $role_ids;
        $role_names = [];
        foreach ($role_ids as $role_id) {
            $info = AdminRoleCacheModel::getOneOrFail($role_id, 'name,is_disable');
            if (AdminRoleService::IS_DELETE_NO == $info['is_disable']) {
                $role_names[] = $info['name'];
            }
        }
        $admin_info['role_names'] = empty($role_names) ? null : $role_names;

        $factory_ids = null;
        $factory_names = [];
        $category_ids = null;
        $category_names = [];
        $area_ids = null;
        $area_names = [];
        $factory_group_ids = null;
        $factory_group_names = [];

        $admin_config = BaseModel::getInstance('admin_config_receive')
            ->getOneOrFail($admin_id);
        $type = $admin_config['type'];

        if (AdminConfigReceiveService::TYPE_TAKE_TURN == $type) {

        } else {
            $area_ids = BaseModel::getInstance('admin_config_receive_area')
                ->getFieldVal(['admin_id' => $admin_id], 'area_id', true);
            $area_ids = empty($area_ids)? []: array_values(array_unique($area_ids));
            foreach ($area_ids as $area_id) {
                $info = AreaCacheModel::getOneOrFail($area_id, 'name');
                $area_names[] = $info['name'];
            }

            $opts = [
                'where' => [
                    'admin_id' => $admin_id,
                ],
            ];
            $partner_ids = BaseModel::getInstance('admin_config_receive_partner')
                ->getFieldVal($opts, 'partner_admin_id', true);
            $partner_ids = empty($partner_ids)? []: array_values(array_unique($partner_ids));
            $partner_names = [];
            foreach ($partner_ids as $partner_id) {
                $partner = AdminCacheModel::getOneOrFail($partner_id, 'nickout');
                $partner_names[] = $partner['nickout'];
            }

            if (AdminConfigReceiveService::TYPE_FACTORY == $type) {
                $factory_ids = BaseModel::getInstance('admin_config_receive_factory')
                    ->getFieldVal(['admin_id' => $admin_id], 'factory_id', true);
                $factory_ids = empty($factory_ids) ? null : array_values(array_unique($factory_ids));
                foreach ($factory_ids as $factory_id) {
                    $info = FactoryCacheModel::getOneOrFail($factory_id, 'factory_full_name');
                    $factory_names[] = $info['factory_full_name'];
                }

            } elseif (AdminConfigReceiveService::TYPE_CATEGORY == $type) {

                $category_ids = BaseModel::getInstance('admin_config_receive_category')
                    ->getFieldVal(['admin_id' => $admin_id], 'category_id', true);
                $category_ids = empty($category_ids) ? null : array_values(array_unique($category_ids));
                foreach ($category_ids as $category_id) {
                    $info = CmListItemCacheModel::getOneOrFail($category_id, 'item_desc');
                    $category_names[] = $info['item_desc'];
                }

            } elseif (AdminConfigReceiveService::TYPE_AREA == $type) {

            } elseif (AdminConfigReceiveService::TYPE_FACTORY_GROUP == $type) {
                $opts = [
                    'where' => [
                        'admin_id' => $admin_id,
                    ],
                ];
                $factory_group_ids = BaseModel::getInstance('admin_config_receive_factory_group')
                    ->getFieldVal($opts, 'group_id', true);

                $factory_group_ids = empty($factory_group_ids)? []: array_values(array_unique($factory_group_ids));
                $factory_group_names = [];
                $groups = [];
                foreach (FactoryService::FACTORY_GROUP as $group) {
                    $groups[$group['id']] = $group['name'];
                }
                foreach ($factory_group_ids as $factory_group_id) {
                    $factory_group_names[] = $groups[$factory_group_id];
                }

            }
        }

        $opts = [
            'where' => [
                'admin_id'   => $admin_id,
                'is_on_duty' => AdminConfigReceiveWorkdayService::IS_ON_DUTY_YES,
            ],
            'order' => 'workday',
        ];
        $workdays = BaseModel::getInstance('admin_config_receive_workday')
            ->getFieldVal($opts, 'workday', true);

        $admin_info['is_auto_receive'] = $admin_config['is_auto_receive'];
        $admin_info['max_receive_times'] = $admin_config['max_receive_times'];
        $admin_info['receive_type'] = $admin_config['type'];
        $admin_info['factory_ids'] = $factory_ids;
        $admin_info['category_ids'] = $category_ids;
        $admin_info['area_ids'] = $area_ids;

        $admin_info['workdays'] = $workdays;
        $admin_info['factory_names'] = empty($factory_names) ? null : $factory_names;
        $admin_info['category_names'] = empty($category_names) ? null : $category_names;
        $admin_info['area_names'] = empty($area_names) ? null : $area_names;
        $admin_info['partner_ids'] = empty($partner_ids) ? null : $partner_ids;
        $admin_info['partner_names'] = empty($partner_names) ? null : $partner_names;
        $admin_info['factory_group_ids'] = empty($factory_group_ids)? null: $factory_group_ids;
        $admin_info['factory_group_names'] = empty($factory_group_names)? null: $factory_group_names;

        return $admin_info;
    }

    public function getAvailableList($param)
    {
        $name = $param['name'];

        $cnt = 0;
        $data = [];

        $where = [];
        if (!empty($name)) {
            $where['nickout'] = ['like', '%' . $name . '%'];
        }

        $total_type = AdminRoleService::AUTO_RECEIVE_TYPE_CHECKER | AdminRoleService::AUTO_RECEIVE_TYPE_DISTRIBUTOR | AdminRoleService::AUTO_RECEIVE_TYPE_RETURNEE | AdminRoleService::AUTO_RECEIVE_TYPE_AUDITOR;

        //查找角色
        $role_where = [
            'type'       => ['exp', "&{$total_type}>0"],
            'is_delete'  => AdminRoleService::IS_DELETE_NO,
            'is_disable' => AdminRoleService::IS_DISABLE_NO,
        ];
        $role_ids = BaseModel::getInstance('admin_roles')
            ->getFieldVal($role_where, 'id', true);

        if (empty($role_ids)) {
            return [
                'cnt'  => $cnt,
                'data' => $data,
            ];
        }

        //查找客服
        $rel_role_where = [
            'rel_admin_roles' => ['in', $role_ids],
        ];
        $admin_ids = BaseModel::getInstance('rel_admin_roles')
            ->getFieldVal($rel_role_where, 'admin_id', true);

        if (empty($admin_ids)) {
            return [
                'cnt'  => $cnt,
                'data' => $data,
            ];
        }

        $admin_ids = array_unique($admin_ids);
        $where['id'] = ['in', $admin_ids];
        $where['state'] = ['in', AdminService::STATE_ENABLED];
        $field = 'id,nickout as nickname';
        $model = BaseModel::getInstance('admin');
        $opts = [
            'field' => $field,
            'where' => $where,
            'order' => 'id',
            'limit' => $this->page(),
        ];
        $data = $model->getList($opts);

        $cnt = $model->getNum($where);

        return [
            'data' => $data,
            'cnt'  => $cnt,
        ];

    }


}