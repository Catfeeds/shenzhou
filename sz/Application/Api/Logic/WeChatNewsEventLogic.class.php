<?php
/**
* @User zjz
* @Date 2016/12/5
* @mess 公众平台信息及事件处理
*/
namespace Api\Logic;

use Common\Common\Repositories\Events\WechatSubscribeEvent;
use Think\Log;
use Api\Common\ErrorCode;
use Api\Logic\BaseLogic;
use Library\Common\Util;
use EasyWeChat\Foundation\Application;
use EasyWeChat\Message\Image;
use EasyWeChat\Message\News;
use EasyWeChat\Message\Text;

class WeChatNewsEventLogic extends BaseLogic
{

	const REPLY_TEXT = 1;
    const REPLY_IMAGE = 2;
    const REPLY_NEWS = 3;

    const TYPE_KEY = 1;
    const TYPE_SUBSCRIPT = 2;
    const TYPE_NOMATCH = 3;


    public function arrTotNewsMessages($datas = [])
    {
        $messages = [];
        return $messages;
    }

    /**
     * @User zjz
     * 主动推送消息
     */
    public function wxSendNewsByOpenId($open_id, $message, $type = 'text')
    {
        $config = C('easyWeChat');
        $app = new Application($config);
        switch (strtolower($type)) {
            case 'text':
                $msg = new Text(['content' => $message]);
                break;

            case 'news':
                $msg = [];
                foreach ($message as $k => $v) {
                    $msg[] = new News($v);
                }
                break;

            case 'image':
                $msg = $message;
                break;
        }
        // var_dump($message);die;
        try {
            \Think\Log::record('media_id:' . $msg . '__,open_id:' . $open_id);
            $return = $app->staff->message($msg)->to($open_id)->send();
        } catch (\Exception $e) {
            
        }
        return $return;
    }

	/**
     * 微信后台配置URL的检测与响应用户行为
     * @param $name
     */
    public function run()
    {
        // $origin_id = $this->decodeName($name);
        $config = C('easyWeChat');

        $app = new Application($config);
        Log::record(file_get_contents('php://input'));

        $server = $app->server;

        $server->setMessageHandler(function($message) use ($config) {
            // var_dump($message);die;
            // 注意，这里的 $message 不仅仅是用户发来的消息，也可能是事件
            if ($message->MsgType == 'event') {
                Log::record(\GuzzleHttp\json_encode($message, JSON_UNESCAPED_UNICODE));
                switch (strtolower($message->Event)) {
                    case 'subscribe':
//                    	$info = [
//                            'reply_type' => self::REPLY_TEXT,
//                            'reply' => '欢迎关注“家电帮帮”，家电售后更轻松！消费者扫码激活的产品自动保存在“我的家电库”里，随时可查询产品说明和质保期限，需要售后服务时可以一键申请！',
//                        ];

                        // TODO 前端处理好某些一面不需要登录后注释注册代码
                        D('Register', 'Logic')->subscribeByOpenId($message->FromUserName);
                        event(new WechatSubscribeEvent($message->FromUserName));
//                        return $this->reply($info, $message);

                    // case 'unsubscribe':
                         break;

                    case 'click':
                        $key = $message->EventKey;
                        if (!method_exists($this, $key)) {
                            return $this->reply([
                                'reply_type' => self::REPLY_TEXT,
                                'reply'  => '喵？喵？喵？',
                            ]);
                        }
                        return $this->$key();

                    default:
                        # code...
                        break;
                }
            } else {
                switch (strtolower($message->MsgType)) {
                    case 'text':
                        if (trim($message->Content) == '抽奖') {
                            $info = [
                                'reply_type' => self::REPLY_TEXT,
                                'reply' => '<a href="http://d.szlb.cc/lottery-page_?draw_id=164">点击抽奖</a>',
                            ];
                            Log::record(json_encode($info));
                            return $this->reply($info, $message);
                        }
                    default:
//                        return $this->reply([
//                        		'reply_type' => self::REPLY_TEXT,
//                        		'reply'	 => '喵？喵？喵？',
//                        	]);
//                        break;
                }
            }
        });


        $response = $server->serve();

        $response->send();
    }

    protected function reply($info)
    {
        if ($info['reply_type'] == self::REPLY_TEXT) {
            return new Text(['content' => $info['reply']]);
        } elseif ($info['reply_type'] == self::REPLY_IMAGE) {
            return new Image(['media_id' => $info['media_id']]);
        } elseif ($info['reply_type'] == self::REPLY_NEWS) {
            $news_list[] = [
            	'title' => 'title1',
                'description' => 'description1',
                'image' => 'https://ss1.baidu.com/6ONXsjip0QIZ8tyhnq/it/u=283392913,102919755&fm=80&w=179&h=119&img.JPEG',
                'url' => 'https://www.baidu.com/',
            ];
            $news_list[] = [
            	'title' => 'title2',
                'description' => 'description2',
                'image' => 'https://ss1.baidu.com/6ONXsjip0QIZ8tyhnq/it/u=283392913,102919755&fm=80&w=179&h=119&img.JPEG',
                'url' => 'https://git.3ncto.com/user/login',
            ];
            $news = [];
            foreach ($news_list as $key => $news) {
                $news = new News([
                    'title' => $news['title'],
                    'description' => $news['digest'],
                    'image' => $news['image'],
                    'url' => $news['url'],
                ]);
            }
            return $news;
        } else {
            Log::record('未知回复类型:' . json_encode($info, JSON_UNESCAPED_UNICODE));
            $this->throwException(ErrorCode::SYS_SYSTEM_ERROR);
        }
    }

    protected function contactUs()
    {
        return $this->reply([
            'reply_type' => self::REPLY_TEXT,
            'reply'  => '尊敬的用户，如果您有疑问或者家里有电器需要维修安装，可拨打我们的免费服务热线400-830-9995咨询哦',
        ]);
    }

}
