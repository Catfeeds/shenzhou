<?php
/**
 * File: UserLogic.class.php
 * User: xieguoqiu
 * Date: 2016/12/14 15:19
 */

namespace Api\Logic;

use Api\Common\ErrorCode;
use Api\Logic\BaseLogic;
use Api\Model\BaseModel;
use Common\Common\CacheModel\WxUserCacheModel;
use Common\Common\Repositories\Events\DealerUpdateDataEvent;
use Common\Common\ResourcePool\RedisPool;
use Common\Common\Service\AuthService;
use Library\Common\Util;
use Common\Common\Service\SMSService;

class UserLogic extends BaseLogic
{

    public function checkPhoneCodeOrFail($phone, $code, $type = 0)
    {
        $model = BaseModel::getInstance('phone_code');

        $where = [
            // 'verification_code' => $code,
            'phone_number' => $phone,
            'type' => $type,
            '_string' => ' create_time + is_expired >= '.NOW_TIME.' ',
        ];

        // if (1 != $type) {
        if (!in_array($type, SMSService::CHECK_PHONE_CODE_NOT_MUST_VERIFIED)) {
            $where['_string'] .= $where['_string'] ? ' AND ' : '';
            $where['_string'] .= ' (verified_time IS NULL OR verified_time = 0) ';
        }

        $opt = [
            'where' => $where,
            'order' => 'create_time DESC',
        ];
        $code_data = $model->getOne($opt);
        
        if (!$code_data['id'] || $code_data['verification_code'] != $code) {
            $this->throwException(ErrorCode::CODE_IS_WRONG_OR_ED);
        }

        BaseModel::getInstance('phone_code')->update($code_data['id'], ['verified_time' => NOW_TIME]);

    }

    /**
     * @User 微信用户绑定手机号码
     @ @param $type 1: 经销商 否则 普通用户
     */
    public function setWxPhone($phone = 0, $code = '', $type = 0)
    {
        $auth_user = AuthService::getAuthModel();
        $user_id = $auth_user['id'];

        if (!$phone) {
            $this->throwException(ErrorCode::PHONE_NOT_EMPTY);
        } elseif (!$code) {
            $this->throwException(ErrorCode::CODE_NOT_EMPTY);
        }

        $data = ['telephone' => $phone, 'bind_time' => NOW_TIME, 'user_type' => $type];
//        $user = BaseModel::getInstance('wx_user')->getOne($id, 'id,telephone,openid')
//        $model = D('User');
//        $user = $model->getOne($id);
        if (!$phone || !Util::isPhone($phone)) {
            $this->throwException(ErrorCode::PHONE_GS_IS_WRONG);
        }

        $wx_user_model = BaseModel::getInstance('wx_user');

        $wx_user_model->startTrans();
        // 检查是否已绑定手机号码
        if ($auth_user['telephone']) {
            $this->throwException(ErrorCode::YOU_HAD_SAME_PHONE);
        } else {
            $user = $wx_user_model->getOne(['telephone' => $phone], 'id,telephone,openid');
            if ($user['openid']) {
                $this->throwException(ErrorCode::HAD_SAME_PHONE);
            }
            if ($user['id']) {
                $user_id = $user['id'];
                $auth_user_data = $auth_user->data;
                $wxUserId = $auth_user_data['id'];
                unset($auth_user_data['id'], $auth_user['add_time']);
                // 更新账号信息，删除原账号
                WxUserCacheModel::update($user['id'], $auth_user_data);
//                $wx_user_model->update($user['id'], $auth_user_data);
                WxUserCacheModel::remove($auth_user['id']);
//                $wx_user_model->update($auth_user['id'], ['is_delete' => 1]);
                // 转移工单数据
                BaseModel::getInstance('worker_order_user_info')->update([
                    'wx_user_id' => $auth_user['id'],
                ], [
                    'wx_user_id' => $user['id']
                ]);
                // TODO 转移优惠券等数据
                $this->transUserData($user_id, $wxUserId);
            }
        }


//        $this->checkPhoneCodeOrFail($phone, $code, 1);
        if (S('C_REGISTER_VERIFY_' . $phone) != $code) {
            $this->throwException(ErrorCode::CODE_IS_WRONG_OR_ED);
        }

        $where = [
            'phone' => $phone,
            'is_delete' => 0,
        ];
        $log_model = BaseModel::getInstance('dealer_bind_products');
        $log = $log_model->getList($where);

        if ($log && $type == 1) {
            $this->throwException(ErrorCode::CAN_NOT_APPLY_DEALER_HAD_LOG);
        }

        $add_log = [];
        foreach ($log as $k => $v) {
            $add_log[] = [
                'wx_user_id' => $user_id,
                'wx_product_id' => $v['product_id'],
                'wx_factory_id' => $v['factory_id'],
                'code' => $v['code'] ?? '',
                'md5code' => $v['md5code'] ?? '',
                'bill' => $v['bill'] ?? '',
            ];
        }

        // if ($type == 1) {
        //     $data['user_type'] = 1;
        // }
        //     false === $model->update($id, $data)
        // &&  $this->throwException(ErrorCode::SYS_DB_ERROR);

        if ($type != 1) {
            $add_log && BaseModel::getInstance('wx_user_product')->insertAll($add_log);
            
            $log_model->update($where, ['is_delete' => 1]);
        }
        WxUserCacheModel::update($user_id, $data);
//        $wx_user_model->update($user_id, $data);
        $wx_user_model->commit();
        S('C_REGISTER_VERIFY_' . $phone, null);

        return $user_id;
    }

    public function consumer($id)
    {
        $info = D('User')->getOne(['id' => $id], 'id,user_type,nickname,telephone');
        
        if ($info['user_type'] != 0) {
            $this->throwException(ErrorCode::USER_NOT_COMMON_USER);
        }
        
        return $info;
    }

  

    public function agencyInfo()
    {
        $dealer_info = BaseModel::getInstance('dealer_info')
            ->getOne(['wx_user_id' => AuthService::getAuthModel()->id], 'id,store_name,dealer_product_ids,name,area_ids,area_desc,license_image,dealer_images');
        if (!$dealer_info) {
            return (Object)[];
//            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '暂无资料');
        }

        $dealer_products = BaseModel::getInstance('dealer_product')
            ->getFieldVal(['id' => ['IN', $dealer_info['dealer_product_ids']]], 'name', true);
        $dealer_products = implode(',', $dealer_products);
        $areas = BaseModel::getInstance('cm_list_item')
            ->getFieldVal(
                [
                    'where' => ['list_item_id' => ['IN', $dealer_info['area_ids']]],
                    'order' => 'list_item_id ASC'
                ],
                'item_desc',
                true
            );
        $areas = implode('', $areas);

        $info['dealer_products'] = $dealer_products;
        $info['areas'] = $areas;

        $info['license_image'] = Util::getServerFileUrl($dealer_info['license_image']);
        $info['license_image_name'] = $dealer_info['license_image'];
        $info['dealer_images'] = \GuzzleHttp\json_decode($dealer_info['dealer_images']);
        $images = [];
        foreach ($info['dealer_images'] as $dealer_image) {
            $images[] = [
                'name' => $dealer_image,
                'url' => Util::getServerFileUrl($dealer_image),
            ];
        }

        $info['dealer_images'] = $images;

        return array_merge($dealer_info, $info);
    }

    public function updateAgency($id, $params)
    {
        $this->addAgencyInfoRuleDataOrFail($params);
        if (isset($params['dealer_images'])) {
            if (count(htmlEntityDecodeAndJsonDecode($params['dealer_images'])) > 3) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '点名图片最多3张');
            }
            // $params['dealer_images'] = \GuzzleHttp\json_encode($params['dealer_images']);
            $params['dealer_images'] = htmlEntityDecode($params['dealer_images']);
        }

        $dealer_info = BaseModel::getInstance('dealer_info')
            ->getOne(['wx_user_id' => AuthService::getAuthModel()->id], 'id,name');

        $user_phone = AuthService::getAuthModel()->telephone;

        if (!$dealer_info) { 
            // 添加  zjz  考虑到旧数据 没有经销商信息
            $params['wx_user_id'] = AuthService::getAuthModel()->id;
            $params = $this->addAgencyInfoRuleDataOrFail($params);
            BaseModel::getInstance('dealer_info')->insert($params);
        } else {
             // 修改
            if (AuthService::getAuthModel()->id != $id) {
                $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '您无权限修改');
            }
            BaseModel::getInstance('dealer_info')->update($dealer_info['id'], $params);
            // event(new DealerUpdateDataEvent($user_phone, $params));
        }
        // 修改的时候才触发 更新经营执照 重置审核权限
        event(new DealerUpdateDataEvent($user_phone, $params));

    }

    // 验证 添加经销商资料时候的数据
    public function addAgencyInfoRuleDataOrFail($data = [])
    {
        $dealer_pro_ids = array_unique(array_filter(explode(',', $data['dealer_product_ids'])));  // find_in_set
        $area_ids = array_unique(array_filter(explode(',', $data['area_ids'])));
        $dealer_images = !is_array($data['dealer_images']) ? htmlEntityDecodeAndJsonDecode($data['dealer_images']) : $data['dealer_images'];
        $dealer_images = array_unique(array_filter($dealer_images));
        
        if (!$data['store_name']) {
            $this->throwException(ErrorCode::STROE_NAME_NOT_EMPTY);
        } elseif (!$data['name']) {
            $this->throwException(ErrorCode::NAME_NOT_EMPTY);
        } elseif (!$dealer_pro_ids) {
            $this->throwException(ErrorCode::DEALER_PRODUCT_NOT_EMPTY);
        } elseif (!$area_ids) {
            $this->throwException(ErrorCode::AREA_IDS_NOT_EMPTY);
        } elseif (!$data['area_desc']) {
            $this->throwException(ErrorCode::AREA_DESC_NOT_EMPTY);
        } elseif (!$data['license_image']) {
            $this->throwException(ErrorCode::LICENSE_IMG_NOT_EMPTY);
        } elseif (!$dealer_images) {
            $this->throwException(ErrorCode::DEALER_IMGS_NOT_EMPTY);
        } elseif (count($dealer_images) > 3) {
            $this->throwException(ErrorCode::IMAGES_NOT_DY_3);
        }

        $data['dealer_product_ids'] = implode(',', $dealer_pro_ids);
        $data['area_ids'] = implode(',', $area_ids);
        $data['dealer_images'] = json_encode($dealer_images);

        return $data;
    }

    public function addAgencyOlnyOne($data = [])
    {
        $add = $this->addAgencyInfoRuleDataOrFail($data);
        $add['wx_user_id'] = $user_id = AuthService::getAuthModel()->id;

//        $this->setWxPhone($data['phone'], $data['code'], 1);

//        D('User')->update($user_id, ['telephone' => $data['phone'], 'user_type' => 1]);

        $model = BaseModel::getInstance('dealer_info');
        $model->startTrans();
        $model->remove(['wx_user_id' => $user_id]);
        $model->insert($add);
        $model->commit();
    }

    public function applyAgencyByFid($fid = 0, $data = [])
    {
        // if (!AuthService::getAuthModel()->user_type || AuthService::getModel() != 'wxuser') {
        //     $this->throwException(ErrorCode::USER_NOT_AGENCY);
        // }

        // if (!AuthService::getAuthModel()->telephone) {
        //     $this->throwException(ErrorCode::YOU_NOT_PHONE);
        // }

        // $where = [
        //     'user_name' => AuthService::getAuthModel()->telephone,
        //     'factory_id' => $fid,
        // ];

        // $model = BaseModel::getInstance('factory_product_white_list');

        $model = D('FactoryProductWhiteList');

        $white_data = $model->checkThisFactoryAgencyByFid($fid);
        if ($white_data) {
            // $this->throwException(ErrorCode::YOU_HAD_FACTORY_ID_AGENCY);
            return $model->update([
                    'user_name' => AuthService::getAuthModel()->telephone,
                    'factory_id' => $fid,
                ], ['status' => 0]);
        }

        $add = [
            'factory_id' => $fid,
            'user_name' => AuthService::getAuthModel()->telephone,
            'name' => BaseModel::getInstance('dealer_info')->getFieldVal(['wx_user_id' => AuthService::getAuthModel()->id], 'name'),
            // 'is_use' => 1,
            'status' => 0,
        ];  

        if (!$add['name']) {
            $this->throwException(ErrorCode::PLEACE_SET_AGENCY_INFO);
        }
        if ($data['password']) {
            $add['password'] = md5($data['password']);
        }

        $model->insert($add);

    }


    /**
     * 微信端绑定手机号码
     * @param $unionid
     * @param $phone
     * @throws \Common\Common\ReminderException
     * TODO 废弃
     */
    function wxBindUser($unionid, $phone)
    {
        $model = D('User');
        M()->startTrans();
        if ($unionid && $phone) {
            //手机用户
            $user = $model->getOne(['telephone'=>$phone]);
            //微信用户
            $wxUser = $model->getOne(['unionid'=>$unionid], 'id, telephone');
            $wxUser['telephone'] && $this->throwException(ErrorCode::HAD_SAME_PHONE, '手机号码已被绑定，请重新输入');
            if ($user) {
                if ($user['unionid']) {
                    $this->throwException(ErrorCode::HAD_SAME_PHONE, '该手机号码已被其他微信号绑定，请重新输入');
                } else {
                    //微信绑定手机号
                    $flag1 = $model->update(['id'=>$user['id']], ['unionid'=>$unionid]);
                    $flag2 = $this->transUserData($user['id'], $wxUser['id']);
                    $flag3 = $model->update(['id'=>$wxUser['id']], ['is_delete'=>time()]); //删除微信账户
                    if ($flag1!==false && $flag2!==false && $flag3!==false) {
                        M()->commit();
                    }

                    M()->rollback();
                }
            } else {
                //手机号绑定微信
                if (false!==$model->update(['id'=>$wxUser['id']], ['telephone'=>$phone]))
                    M()->commit();
                else
                    M()->rollback();
            }
        } else {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '手机号码和Openid不能为空');
        }
    }

    /**
     *  转移抽奖、中奖、优惠券
     */
    public function transUserData($phoneUserId, $wxUserId)
    {
        $flag1 = D('DrawRecord')->update(['wx_user_id'=>$wxUserId], ['wx_user_id'=>$phoneUserId]);
        $flag2 = D('CouponReceiveRecord')->update(['wx_user_id'=>$wxUserId], ['wx_user_id'=>$phoneUserId]);

        if ($flag1!==false && $flag2==false)
            return true;
        else
            return false;
    }

}
