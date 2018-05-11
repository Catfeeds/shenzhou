<?php
/**
 * File: UserController.class.php
 * User: xieguoqiu
 * Date: 2016/12/14 15:11
 */

namespace Api\Controller;

use Api\Common\ErrorCode;
use Api\Model\BaseModel;
use Common\Common\Service\AuthService;
use Library\Crypt\AuthCode;
use Library\Common\Util;
use Think\Log;

class UserController extends BaseController
{

    public function isSubscribe()
    {
        try {
            $data = BaseModel::getInstance('wx_user')->getOneOrFail(I('get.id', 0));
            $is_subscribe = false;
                $data['openid'] 
            &&  ($is_subscribe = D('WeChatUser', 'Logic')->isSubscribe($data['openid']));
            $this->response([
                'is_subscribe' => $is_subscribe,
            ]);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function setMyPhone()
    {
        $id = $this->requireAuth();
        $type = AuthService::getModel();
        $phone = I('put.phone', 0);
        $check_type = I('put.type', 0);
        $code = I('put.code', 0);

        $logic = D('User', 'Logic');
        try {
            switch (strtolower($type)) {
                case 'wxuser':
                    $user_id = $logic->setWxPhone($phone, $code, $check_type);
                    break;
                
                default:
                    $this->fail(ErrorCode::DATA_IS_WRONG);
                    break;
            }
            $s = 24*3600;
            $token_data = [
                'user_id' => $user_id,
                'type' => 'wxuser',
            ];
            $token = AuthCode::encrypt(json_encode($token_data), C('TOKEN_CRYPT_CODE'), $s);
            $this->response(['token' => $token, 'user_id' => $user_id]);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function consumer()
    {
        try {
            $id = $this->requireAuth();
            $info = D('User', 'Logic')->consumer($id);
            $this->response($info);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function agencyInfo()
    {
        try {
            $this->requireAuth();
            $info = D('User', 'Logic')->agencyInfo();
            $this->response($info);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function updateAgency()
    {
        try {
            $id = $this->requireAuth();
            $params = [];
            I('store_name') && $params['store_name'] = I('store_name');
            I('name') && $params['name'] = I('name');
            I('dealer_product_ids') && $params['dealer_product_ids'] = I('dealer_product_ids');
            I('area_ids') && $params['area_ids'] = I('area_ids');
            I('area_desc') && $params['area_desc'] = I('area_desc');
            // I('license_image') && $params['license_image'] = I('license_image');
            $params['license_image'] = I('license_image');
            // I('dealer_images') && $params['dealer_images'] = I('dealer_images');
            $params['dealer_images'] = I('dealer_images');
            if (!$params) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_MISSING);
            }
            if (AuthService::getAuthModel() != 1) {
                $this->fail(ErrorCode::SYS_REQUEST_PARAMS_ERROR, '你不是经销商');
            }
            D('User', 'Logic')->updateAgency($id, $params);
            $this->response();
        } catch (\Exception $e) {
            Log::record($e);
            $this->getExceptionError($e);
        }
    }

    public function addAgency()
    {
        $post = I('post.');
        try {
            $id = $this->requireAuth();
            D('User', 'Logic')->addAgencyOlnyOne($post);
            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function dealerProducts()
    {
        $model = BaseModel::getInstance('dealer_product');

        $count = $model->getNum();
        $list = $model->getList([
                'limit' => getPage(),
            ]);
        $this->paginate($list, $count);
    }

    public function applyAgency()
    {
        $this->requireAuth();
        $factory_id = I('get.id', 0);
        try {
            BaseModel::getInstance('factory')->getOneOrFail($factory_id);
            D('User', 'Logic')->applyAgencyByFid($factory_id, I('post.'));
            $this->okNull();
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function getFactoryAgency()
    {
        $this->requireAuth();
        try {
            $factory_id = I('get.id', 0);
            $data = D('FactoryProductWhiteList')->checkThisFactoryAgencyByFid($factory_id);
            // $data['status'] 状态：0待授权，1启用，2停用
            $type = 2;
            if ($data['id']) {
                switch ($data['status']) {
                    case 0:
                        $type = 3;
                        break;

                    case 1:
                        $type = 1;
                        break;

                    case 2:
                        $type = 4;
                        break;

                    default:
                        $type = 2;
                        break;
                }
            }

            $this->response(['result' => (string)$type]);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function contactHistories()
    {

        $id = I('get.id', 0, 'intval');
        $type = I('get.type', '');

        try {
            $model = new \Api\Model\UserModel();
            $data = [
                'name' => null,
                'phone' => null,
                'area_ids' => null,
                'area_ids_desc' => null,
                'area_desc' => null,
            ];
            // $data = array_merge($data, $model->getMyOrderContactHistoriesById($id, $type));
            $data = $model->getOrderAndRegisterLastAreasById($id) + $data;
            // switch ($type) {
            //     case '1':
            //         $data = array_merge($data, $model->getMyProductContactHistoriesById($id));
            //         break;

            //     case '2':
            //         $data = array_merge($data, $model->getMyOrderContactHistoriesById($id));
            //         break;
            // }

            if ($data['area_ids']) {
                $list = D('Product')->getCmListItemByIds($data['area_ids']);
                $data['area_ids_desc'] = arrFieldForStr($list, 'item_desc');
            }

            $this->response($data);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }

    }

}
