<?php
/**
* 
*/
namespace Api\Controller;

use Api\Controller\BaseController;
use Api\Common\ErrorCode;
use Api\Logic\WeChatFileLogic;
use Api\Model\BaseModel;
use Api\Repositories\Events\UploadFileEvent;
use Library\Common\Util;

class FileController extends BaseController
{
    public function uploads()
    {
        try {
            $result = D('Files', 'Logic')->uploadOrFail();


            $save = [];
            foreach ($result as $k => $v) {
                if (!$v['url']) { //  || !file_exists($v['url'])
                    $this->fail(ErrorCode::FILE_UPLOAD_WORNG);
                }
                $save[] = [
                    'input_name' => $v['key'],
                    'url' => trim($v['url'], '.'),
                    'name' => $v['name'],
                ];
            }
            $this->response($save);   
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    public function uploadsBase64()
    {
        $images = htmlEntityDecodeAndJsonDecode(I('images'));
        // $images[] = "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";
        // var_dump(json_encode($images));die;
        try {
            $files = $save = [];
            foreach ($images as $v) {
                preg_match('/^(data:\s*image\/(\w+);base64,)/', $v, $result);
                $save[] = [
                    'str' => str_replace('data:image/png;base64', '', $v),
                    'gs'  => $result[2],
                ];
            }
            $save = array_filter($save);
            if (count($save) > 3) {
                $this->throwException(ErrorCode::IMAGES_NOT_DY_3);
            }

            $send_forb = [];
            foreach ($save as $value) {
                $file = $this->issetFileName($value['gs']);
                file_put_contents($file['url'], base64_decode($value['str']));
                $send_forb[] = [
                    'url' => $file['url'],
                ];
                $files[] = [
                    'name' => $file['name'],
                    'url' => trim($file['url'], '.'),
                    'url_full' => Util::getServerFileUrl($file['url']),
                ];
            }
//            event(new UploadFileEvent($send_forb));
            $this->response($files);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

    protected function issetFileName($type = '')
    {
        $type = $type ? $type : 'png';
        $name = md5(time().sprintf("%011d", rand(0, 99999999999))).'.'.$type;
        $url = './Uploads/'.date('Ymd').'/';
        mkdir($url, 0777, true);
        $file = $url.$name;
        if (file_exists(Util::getServerFileUrl($file))) {
            $this->issetFileName();
        }
        $return = [
            'url' => $file,
            'name' => $name,
        ];
        return $return;
    }

    public function uploadWechat()
    {
        try {
            $result = D('Files', 'Logic')->uploadOrFail();
            $res = (new WeChatFileLogic())->uploadFile($result['image']['url']);
            $this->response($res);
        } catch (\Exception $e) {
            $this->getExceptionError($e);
        }
    }

}
