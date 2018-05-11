<?php
/**
* @User zjz
*/
namespace Api\Logic;

use Api\Common\ErrorCode;
use Api\Model\BaseModel;
use EasyWeChat\Foundation\Application;
use Library\Common\Util;
use Think\Log;
use Api\Logic\BaseLogic;
use Api\Repositories\Events\UploadFileEvent;

class WeChatFileLogic extends BaseLogic
{
	
	protected $material;

	function __construct()
	{
        $config = C('easyWeChat');
		$app = new Application($config);
//        Log::record(file_get_contents('php://input'));
        // die($app->access_token->getToken());
        $this->material['long'] = $app->material;
        $this->material['sort'] = $app->material_temporary;
        return $this->material;
	}
	
	public function downloadFiles($ids)
	{
		$id_arr = array_filter(explode(',', $ids));
        $return = $post_server = [];
        $url = './Uploads/worker_product_'.date('Ymd', NOW_TIME).'/';
        if (!is_dir($url)){  
            //第三个参数是“true”表示能创建多级目录，iconv防止中文目录乱码
            mkdir($url, 0777, true); 
        }
        foreach ($id_arr as $k => $v) {
        	$name = md5(date('Ymd', NOW_TIME).$v);
        	// $save = trim($url.$name, '.');

        	$name = $this->material['sort']->download($v, $url, $name);
            
            $save = $url.$name;

        	$post_server [] = [
                'url' => '.'.trim($save, '.'),
            ];
        	$return[] = [
                'url' => trim($save, '.'),
                'url_full' => Util::getServerFileUrl(trim($save, '.')),
                'name' => $name,
            ];
        }
//        event(new UploadFileEvent($post_server));
        return $return;
	}

    public function uploadFile($file)
    {
        $real_path = realpath($file);

        $result = $this->material['long']->uploadImage($real_path);

        return $result;
	}

}