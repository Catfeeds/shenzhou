<?php
/**
 * File: ProductLogic.class.php
 * User: xieguoqiu
 * Date: 2016/12/14 17:43
 */

namespace Api\Logic;

use Api\Common\ErrorCode;
use Api\Model\BaseModel;
use Common\Common\Service\AuthService;
use Library\Common\Util;
use Common\Common\Logic\Sms\SmsServerLogic;
use Api\Model\FactoryModel;

class ProductLogic extends BaseLogic
{

    public function getDetailByCode($code)
    {
        $md5_code = D('WorkerOrderDetail')->codeToMd5Code($code);

        return $this->detail($md5_code);
    }

    public function detail($code, $type = 1)
    {
        // 根据md5码获取code信息
        // $code_info = D('FactoryExcel')->getExcelDataByMyPidOrFail($code);
        $model = new \Api\Model\YimaModel();
        switch ($type) {
            case 1:
                $code = $model->getYimaCodeByEnCode($code);            
                break;

        }
        
        $code_info = $model->getYimaInfoByCode($code);
        if (!$code_info['factory_product_qrcode_id'] && $code_info['factory_product_qrcode_id'] != 0) {
            $fdata = BaseModel::getInstance('factory')->getOne(getFidByCode($code));
            $product_info = [
                'product_id' => '',
                'factory_id' => $fdata['factory_id'],
                'factory' => $fdata['factory_full_name'],
                'factory_phone' => $fdata['linkphone'],
                'qrcode_person' => $fdata['qrcode_person'],
                'qrcode_tell' => $fdata['qrcode_tell'],
                'product_category' => '',
                'product_xinghao' => '',
                'item_desc' => '',
                'category' => '',
                'images' => [],
            ];
        } else {
            $product_info = D('Product')->getInfoById($code_info['product_id']);
        }

        // 根据code获取qrcode信息
        // $qrcode_info = D('FactoryProductQrcode')->getInfoByCode($code_info['code']);
        // $product_info = D('Product')->getInfoById($qrcode_info['product_id']);
        // $product_info = D('Product')->getInfoById($code_info['product_id']);

        $product_info['product_title'] = $product_info['standard_name'] . $product_info['brand'] .
            $product_info['category'] . $product_info['product_xinghao'];

        $product_info['product_attrs'] = json_decode($product_info['product_attrs']);
        // $product_info['product_attrs'] = \GuzzleHttp\json_decode($product_info['product_attrs'] ? $product_info['product_attrs'] : '[]');
        $product_info['product_code'] = $code_info['code'];
        $product_info['product_md5_code'] = encryptYimaCode($code_info['code']);
        $product_info['is_active'] = $code_info['active_time'] > 0 ? 1 : 0;
        $product_info['active_time'] = $code_info['active_time'];
        // $product_info['active_end_time'] = $code_info['active_time'] && $code_info['zhibao_time'] ? 
        // 质保策略延保时间
        $product_info['active_credence_end_time'] = '0';
        if ($code_info['chuchuang_time'] && $code_info['active_json']['active_credence_day']) {
            $chuchuang_day = date('Y-m-d', $code_info['chuchuang_time']) .' + '.$code_info['active_json']['active_credence_day'].' day';
            $product_info['active_credence_end_time'] = (string)strtotime($chuchuang_day);
        }

        $product_info['cat_active_end_time'] = '0';
        if ($code_info['chuchuang_time'] && $code_info['active_json']['cant_active_credence_day']) {
            $chuchuang_day = date('Y-m-d', $code_info['chuchuang_time']) .' + '.$code_info['active_json']['cant_active_credence_day'].' day';
            $product_info['cat_active_end_time'] = (string)strtotime($chuchuang_day);
        }
        $product_info['active_end_time'] = (string)get_limit_date($code_info['active_time'], $code_info['zhibao_time'] + $code_info['active_json']['active_reward_moth']);
        if ($product_info['active_end_time'] >=  NOW_TIME || !$code_info['zhibao_time'] || !$code_info['code']) {
            $product_info['is_in'] = '1';
            $product_info['is_out'] = '0';
        } else {
            $product_info['is_in'] = '0';
            $product_info['is_out'] = '1';
        }
        
        $product_info['register_time'] = $code_info['register_time'];
        $product_info['scan_times'] = $code_info['saomiao'];
        $product_info['zhibao_time'] = $code_info['zhibao_time'];
        $product_info['chuchuang_time'] = $code_info['chuchang_time'];
        $product_info['is_disable'] = $code_info['is_disable'];
        $product_info['is_active_type'] = explode(',', $code_info['active_json']['is_active_type']);
        $product_info['is_order_type'] = explode(',', $code_info['active_json']['is_order_type']);
        $product_info['active_reward_moth'] = $code_info['active_json']['active_reward_moth'];
        $product_info['factory_product_qrcode_id'] = $code_info['factory_product_qrcode_id'];

        if ($product_info['product_thumb']) {
            $product_info['product_thumb'] = Util::getServerFileUrl($product_info['product_thumb']);
        } else {
            $product_thumb = BaseModel::getInstance('cm_list_item')
                ->getFieldVal($product_info['product_category'], 'item_thumb');
            $product_info['product_thumb'] = $product_thumb ? Util::getServerFileUrl($product_thumb) : '';
        }

        $product_info['product_content'] = Util::buildImgTagSource(htmlspecialchars_decode($product_info['product_content']));
        $product_info['product_normal_faults'] = Util::buildImgTagSource(htmlspecialchars_decode($product_info['product_normal_faults']));

        return $product_info;
    }

    public function registerMyProductByCode($code = '', $data = [])
    {   
        $area_ids = array_unique(array_filter(explode(',', $data['area_ids'])));

        if ($area_ids) {
            $area_str_arr = BaseModel::getInstance('cm_list_item')->getList(['list_item_id' => ['in', $area_ids]], 'item_desc');
            $area_str_arr = [
                'ids' => implode(',', $area_ids),
                'names' => arrFieldForStr($area_str_arr, 'item_desc', ''),
                'address' => $data['area_desc'],
            ];
        }

        // $data['time'] = strtotime($data['time']);
        // die(json_encode($data));
        if (!$data['time']) {
            $this->throwException(ErrorCode::SHOP_TIME_NOT_EMPTY);
        } elseif ($data['time'] > NOW_TIME) {
            $this->throwException(ErrorCode::SHOP_TIME_DY_NOW_TIME);
        } elseif (!$data['name']) {
            $this->throwException(ErrorCode::SHOP_NAME_NOT_EMPTY);
        } elseif (AuthService::getAuthModel()->user_type == 1 && !Util::isPhone($data['phone'])) {
            $this->throwException(ErrorCode::PHONE_GS_IS_WRONG);
        } elseif (!AuthService::getAuthModel()->user_type && !AuthService::getAuthModel()->telephone) {
            $this->throwException(ErrorCode::YOU_NOT_SAME_PHONE);
        } elseif (!$area_str_arr) {
            $this->throwException(ErrorCode::AREA_IDS_NOT_EMPTY);
        } elseif (!$data['area_desc']) {
            $this->throwException(ErrorCode::AREA_DESC_NOT_EMPTY);
        }

        // 获取 二维码信息
        // $md5code = D('WorkerOrderDetail')->codeToMd5Code($code);        
        // $key = substr($md5code, 0, 1);
        // $model = BaseModel::getInstance('factory_excel_datas_'.$key);
        // $pro_info = $model->getOneOrFail(['code' => $code]);
        // 新获取方式
        $model = new \Api\Model\YimaModel();
        $pro_info = $model->getYimaInfoByCode($code, true);
        $active_json = $pro_info['active_json'];
        $pro_info['chuchuang_time'] = $pro_info['chuchang_time'];

        if (!$pro_info['product_id']) {
            $this->throwException(ErrorCode::DATA_WRONG, '未绑定产品');
        } elseif ($pro_info['chuchuang_time'] && date('Ymd', $data['time']) < date('Ymd', $pro_info['chuchuang_time'])) {
            $this->throwException(ErrorCode::ACTIVE_TIME_NOT_XY_CHUCHANG_TIME);
        } elseif ($pro_info['chuchuang_time'] && $active_json['active_credence_day'] && date('Ymd', $data['time']) > date('Ymd', strtotime(date('Y-m-d', $pro_info['chuchuang_time']).' + '.$active_json['active_credence_day'].' day')) && !$data['bill']) {
            $this->throwException(ErrorCode::SHOP_TIME_DY_90_NOT_BILL, '购买时间大于产品的出厂时间'.$active_json['active_credence_day'].'天，请上传购买小票');
        } elseif ($pro_info['active_time']) {
            $this->throwException(ErrorCode::ACTIVE_TIME_HAD_SAME);
        } elseif (AuthService::getModel() !=  'wxuser' || !in_array(AuthService::getAuthModel()->user_type + 1, explode(',', $active_json['is_active_type']))) {
            // $this->throwException(ErrorCode::REGISTER_PRODUCT_NOT_POWER, '用户类型不在质保策略允许激活的范围内');
            $this->throwException(ErrorCode::REGISTER_PRODUCT_NOT_POWER, '您的身份暂不能激活该产品，如有疑问，请联系厂家，联系电话：'.(new FactoryModel())->getWorkerNeedPhone($pro_info['factory_id']));
        } elseif ($pro_info['chuchuang_time'] && $active_json['cant_active_credence_day'] && $data['time'] > strtotime(date('Y-m-d', $pro_info['chuchuang_time']).' + '.$active_json['cant_active_credence_day'].' day') + 24*3600-1 ) {
            $this->throwException(ErrorCode::REGISTER_PRODUCT_NOT_POWER, '已超过质保策略限制激活产品的时间');
        }
        
        // 码段数据
        // if (!$pro_info['factory_product_qrcode_id']) {
        //     $product_qr = D('FactoryProductQrcode')->getInfoByCode($code);
        // } else {
        //     $product_qr = D('FactoryProductQrcode')->getOne($pro_info['factory_product_qrcode_id']);
        // }
        // 
        // $product = D('Product')->getInfoById($pro_info['product_id']);
        // if (!$product) {
        //     $this->throwException(ErrorCode::CODE_NOT_PRODUCT);
        // }

        $update = [
            'member_id' => AuthService::getAuthModel()->id,
            'active_time' => $data['time'],
            'user_name' => $data['name'],
            'user_tel' =>  (AuthService::getAuthModel()->user_type == 1) ? $data['phone'] : AuthService::getAuthModel()->telephone,
            'user_address' => json_encode($area_str_arr, JSON_UNESCAPED_UNICODE),
            'register_time' => NOW_TIME,
        ];

        $is_power_where = [
            'factory_id' => $pro_info['factory_id'],
            'user_name' => AuthService::getAuthModel()->telephone,
            'status' => 1,
        ];
        if (AuthService::getAuthModel()->user_type == 1 && !BaseModel::getInstance('factory_product_white_list')->getOne($is_power_where)) {
            $this->throwException(ErrorCode::REGISTER_PRODUCT_NOT_POWER);
        }

        if (AuthService::getAuthModel()->telephone == $data['phone'] || !AuthService::getAuthModel()->user_type) {
            $wx_user = AuthService::getAuthModel();
        } else {
            $wx_user = BaseModel::getInstance('wx_user')->getOne(['telephone' => $data['phone']], 'id,user_type');
        }

        if ($wx_user['user_type']) {
            $this->throwException(ErrorCode::PHONE_IS_DEALER);
        } elseif ($wx_user['id'] && BaseModel::getInstance('wx_user_product')->getNum(['wx_user_id' => $wx_user['id']]) >= 1000) {
            $this->throwException(ErrorCode::REGISTER_PRODUCT_DY_1000);
        }

        $model = BaseModel::getInstance(factoryIdToModelName($pro_info['factory_id']));
        $model->startTrans();

        $add_code_data = $update + $pro_info;
        $add_code_data['active_json'] = json_encode($add_code_data['active_json'], JSON_UNESCAPED_UNICODE);
        
        if ($pro_info['not_true']) {
            unset($pro_info['not_true']);
            $model->insert($add_code_data);
        } else {
            $model->update(['code' => $code], $update);
        }

        if (AuthService::getAuthModel()->user_type && !$wx_user['id']) {

            $dbp_mode = BaseModel::getInstance('dealer_bind_products');
            $dbp_phone_where = [
                'is_delete' => 0,
                'phone' => $data['phone'],
            ];
            if ($wx_user['id'] && $dbp_mode->getNum($dbp_phone_where) >= 1000) {
                $this->throwException(ErrorCode::REGISTER_PRODUCT_DY_1000);
            }

            $add = [
                'dealer_id' => AuthService::getAuthModel()->id,
                'product_id' => $pro_info['product_id'],
                'factory_id' => $pro_info['factory_id'],
                'phone' => $data['phone'],
                'md5code' => '',
                'code' => $pro_info['code'],
                'bill' => (string)$data['bill'],
                'is_delete' => 0,
            ];

            $dbp_mode->insert($add);
        } else if (!$wx_user['id']) {
            $this->throwException(ErrorCode::NOT_REGISTER_WECHAT);
        } else {
            if (AuthService::getAuthModel()->user_type == 1) {
                $add = [
                    'dealer_id' => AuthService::getAuthModel()->id,
                    'product_id' => $pro_info['product_id'],
                    'factory_id' => $pro_info['factory_id'],
                    'phone' => $data['phone'],
                    'md5code' => '',
                    'code' => $pro_info['code'],
                    'bill' => (string)$data['bill'],
                    'is_delete' => 1,
                ];
                BaseModel::getInstance('dealer_bind_products')->insert($add);
            }

            $add = [
                'wx_user_id' => $wx_user['id'],
                'wx_product_id' => $pro_info['product_id'],
                'wx_factory_id' => $pro_info['factory_id'],
                'md5code'       => '',
                'code'          => $pro_info['code'],
                'bill'          => (string)$data['bill'],
            ];
            BaseModel::getInstance('wx_user_product')->insert($add);
        }

        $model->commit();
        $factory = BaseModel::getInstance('factory')->getOne($pro_info['factory_id'], 'is_show_yima_ad');
        $add_code_data['is_show_yima_ad'] = $factory['is_show_yima_ad'];
        return $add_code_data;
    }

    public function registerMyProductByCodeWeChatMessage($code = '', $data = [])
    {
        // $md5code = D('WorkerOrderDetail')->codeToMd5Code($code);
        // $key = substr($md5code, 0, 1);
        // $model = BaseModel::getInstance('factory_excel_datas_'.$key);
        // $pro_info = $model->getOneOrFail(['code' => $code]);

        $pro_info = (new \Api\Model\YimaModel())->getYimaInfoByCode($code);
        $product_qr = D('FactoryProductQrcode')->getInfoByCode($code);
        $product = D('Product')->getInfoById($product_qr['product_id']);
        if (!$product) {
            $this->throwException(ErrorCode::CODE_NOT_PRODUCT);
        }

        $pro_str = '';
        $pro_str .= $product['brand'] ? '['.$product['brand'].']' : '';
        $pro_str .= $product['product_xinghao'] ? '['.$product['product_xinghao'].']' : '';
        $pro_str .= $product['category'] ? '['.$product['category'].']' : '';
        $stime = date('Y-m-d', $data['time']);
        $etime = date('Y-m-d', get_limit_date($data['time'], $pro_info['zhibao_time'] + $pro_info['active_json']['active_reward_moth']));
        $message = '尊敬的用户您好，你的'.$pro_str.'已成功登记质保。质保时间为['.$stime.']－['.$etime.']。您可以通过“我的产品库“随时可查询产品说明和质保期限。';

        // 发送微信号信息前提已关注
        $wx_user = BaseModel::getInstance('wx_user')->getOne(['telephone' => $data['phone']], 'id,user_type,openid');
        if ($wx_user['openid']) {
            D('WeChatNewsEvent', 'Logic')->wxSendNewsByOpenId($wx_user['openid'], $message, 'text');
        } else {
            $add_data = [
                'table_id' => 0,
                'phone'    => $data['phone'],
                'content'  => $message, // '【神州联保】'.$message,
                'type'     => 2,
            ];
            $add_datas[] = $add_data;
            (new SmsServerLogic('queue_message', true))->addTemporary($add_datas);
        }
    }

}
