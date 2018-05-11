<?php
/**
 * File: SendRegisterProductNotification.class.php
 * User: xieguoqiu
 * Date: 2016/12/27 12:04
 */

namespace Common\Common\Repositories\Listeners\WxUserRegisterProduct;

use Api\Logic\WeChatUserLogic;
use Api\Model\FactoryProductQrcodeModel;
use Api\Model\ProductModel;
use Api\Model\WorkerOrderDetailModel;
use Common\Common\ErrorCode;
use Common\Common\Model\BaseModel;
use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Events\WxUserRegisterProductEvent;
use Common\Common\Repositories\Listeners\ListenerInterface;
use Common\Common\Service\SMSService;
use Library\Common\Util;
use Common\Common\Service\AuthService;

class SendRegisterProductNotification implements ListenerInterface
{

    /**
     * @param WxUserRegisterProductEvent $event
     */
    public function handle(EventAbstract $event)
    {
        try {
            $model = BaseModel::getInstance(factoryIdToModelName(getFidByCode($event->code)));
//            $md5code = (new WorkerOrderDetailModel())->codeToMd5Code($event->code);
//            $key = substr($md5code, 0, 1);
//            $model = BaseModel::getInstance('factory_excel_datas_'.$key);

            $pro_info = $model->getOneOrFail(['code' => $event->code]);
            $product_qr = (new FactoryProductQrcodeModel())->getInfoByCode($event->code);
            $product = (new ProductModel())->getInfoById($product_qr['product_id']);
            if (!$product) {
                throw new \Exception('产品不存在', ErrorCode::SYS_REQUEST_PARAMS_ERROR);
            }

            $pro_str = '';
            $pro_str .= $product['brand'] ? $product['brand'] : '';
//            $pro_str .= $product['product_xinghao'] ? $product['product_xinghao'] : '';
            $pro_str .= $product['category'] ? $product['category'] : '';
            $stime = date('Y-m-d', $event->data['time']);
            $etime = date('Y-m-d', get_limit_date($event->data['time'], $pro_info['zhibao_time']));
            $message = '您已成功登记了' . $pro_str . '的质保信息。质保时间为' . $stime . ' 至 ' . $etime . '。当您需要产品售后服务时请打开“家电帮帮”公众号，点击菜单栏“我要售后”提交需求，神州联保将为您提供专业家电安装和维修服务！';


            // 发送微信号信息前提已关注
            $wx_user = (AuthService::getAuthModel()->user_type == 1) ? BaseModel::getInstance('wx_user')->getOne(['telephone' => $event->data['phone']], 'id,user_type,openid') : AuthService::getAuthModel();

            $wx_user_logic = new WeChatUserLogic();
            $is_subscribe = $wx_user['openid'] ? $wx_user_logic->isSubscribe($wx_user['openid']) : false;
            if ($wx_user && $is_subscribe) {
                sendWechatNotification($wx_user['openid'], $message, 'text');
                $news_list[] = [
                    'title' => '送您30元代金劵，无需注册，一键到账',
                    'description' => '恭喜您收到一个红包！！！',
                    'image' => Util::getServerFileUrl('/Public/images/shop_news_image.jpg'),
                    'url' => 'http://mp.weixin.qq.com/s?__biz=MzA3OTYwMTYwMA==&mid=2648558398&idx=1&sn=add3082865bfbed4caaac189df9c2daf&chksm=87986191b0efe887c6092fdb72bec128b6746d1350095a467e064f4c37aa4f9bcf827c167fed&mpshare=1&scene=1&srcid=0315ClvR55R516S4eppvHECy#rd'
                ];
                sendWechatNotification($wx_user['openid'], $news_list, 'news');
            } else {

                //判断厂家是否可见广告
                $model = new \Api\Model\YimaModel();
                $pro_info = $model->getYimaInfoByCode($event->code, true);
                $is_show_yima_ad = BaseModel::getInstance('factory')->getFieldVal($pro_info['factory_id'], 'is_show_yima_ad');

                //判断是否有正在进行的活动
                $time = time();
                $where['status'] = 1;
                $where['start_time'] = ['ELT', $time];
                $where['end_time'] = ['EGT', $time];

                $drawParams = [
                    'field' => 'id',
                    'where' => $where,
                    'order' => 'id desc'
                ];
                $drawing = D('DrawRule')->getOne($drawParams);

                $templetId = SMSService::TMP_WX_USER_REGISTER_PRODUCT;
                $params = [
                    'pro_str' => $pro_str,
                    'stime' => $stime,
                    'etime' => $etime,
                ];

                if (!empty($drawing) && $is_show_yima_ad == 1) {

                    $data = $event ->data;
                    $url = C('C_HOST_URL') . C('C_DRAW_PAGE');

                    $templetId =  SMSService::TMP_WX_USER_REGISTER_PRODUCT_WITH_DRAW_URL;

                    $params = [
                        'nickname' =>$data['phone'],
                        'pro_str' => $pro_str,
                        'stime' => $stime,
                        'etime' => $etime,
                        'url' => $url
                    ];
                }

                sendSms($event->data['phone'],$templetId, $params);
            }
        } catch (\Exception $e) {

        }
    }
}
