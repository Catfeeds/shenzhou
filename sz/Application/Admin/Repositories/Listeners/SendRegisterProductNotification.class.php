<?php
/**
 * File: SendRegisterProductNotification.class.php
 * User: xieguoqiu
 * Date: 2016/12/27 12:04
 */

namespace Admin\Repositories\Listeners;

use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Admin\Repositories\Events\RegisterProductEvent;
use Common\Common\Logic\Sms\SmsServerLogic;
use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;
use Library\Common\Util;
use Think\Log;
use Common\Common\Service\AuthService;

class SendRegisterProductNotification implements ListenerInterface
{

    /**
     * @param RegisterProductEvent $event
     */
    public function handle(EventAbstract $event)
    {
        try {
            $md5code = D('WorkerOrderDetail')->codeToMd5Code($event->code);
            $key = substr($md5code, 0, 1);
            $model = BaseModel::getInstance('factory_excel_datas_'.$key);

            $pro_info = $model->getOneOrFail(['code' => $event->code]);
            $product_qr = D('FactoryProductQrcode')->getInfoByCode($event->code);
            $product = D('Product')->getInfoById($product_qr['product_id']);
            if (!$product) {
                $this->throwException(ErrorCode::CODE_NOT_PRODUCT);
            }

            $pro_str = '';
            $pro_str .= $product['brand'] ? $product['brand'] : '';
//            $pro_str .= $product['product_xinghao'] ? $product['product_xinghao'] : '';
            $pro_str .= $product['category'] ? $product['category'] : '';
            $stime = date('Y-m-d', $event->data['time']);
            $etime = date('Y-m-d', get_limit_date($event->data['time'], $pro_info['zhibao_time']));
            $message = '您已成功登记了'.$pro_str.'的质保信息。质保时间为'.$stime.' 至 '.$etime.'。当您需要产品售后服务时请打开“家电帮帮”公众号，点击菜单栏“我要售后”提交需求，神州联保将为您提供专业家电安装和维修服务！';


            // 发送微信号信息前提已关注
            $wx_user = (AuthService::getAuthModel()->user_type == 1) ? BaseModel::getInstance('wx_user')->getOne(['telephone' => $event->data['phone']], 'id,user_type,openid') : AuthService::getAuthModel();

            if (D('WeChatUser', 'Logic')->isSubscribe($wx_user['openid'])) {
                D('WeChatNewsEvent', 'Logic')->wxSendNewsByOpenId($wx_user['openid'], $message, 'text');
                $news_list[] = [
                    'title' => '送您30元代金劵，无需注册，一键到账',
                    'description' => '恭喜您收到一个红包！！！',
                    'image' => Util::getServerFileUrl('/Public/shop_news_image.jpg'),
                    'url' => 'http://mp.weixin.qq.com/s?__biz=MzA3OTYwMTYwMA==&mid=2648558398&idx=1&sn=add3082865bfbed4caaac189df9c2daf&chksm=87986191b0efe887c6092fdb72bec128b6746d1350095a467e064f4c37aa4f9bcf827c167fed&mpshare=1&scene=1&srcid=0315ClvR55R516S4eppvHECy#rd'
                ];

                D('WeChatNewsEvent', 'Logic')->wxSendNewsByOpenId($wx_user['openid'], $news_list, 'news');
            } else {

                $message = "您已成功登记了{$pro_str}的质保信息，质保期为{$stime} 至 {$etime}。感谢使用我们的服务，送您30元代金券，豪华电陶炉用券后只需99元，关注“神州聚惠”微信验证您的手机号即可领取，数量有限先到先得！";

                $add_data = [
                    'table_id' => 0,
                    'phone'    => $event->data['phone'],
                    'content'  => $message,
                    'type'     => 2,
                ];
                $add_datas[] = $add_data;
                (new SmsServerLogic('queue_message', true))->addTemporary($add_datas);
            }
        } catch (\Exception $e) {

        }
    }
}
