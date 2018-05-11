<?php
/**
* 
*/
namespace Admin\Controller;

use Admin\Controller\BaseController;
use Admin\Common\ErrorCode;
use Admin\Model\BaseModel;
use Admin\Repositories\Events\UploadFileEvent;
use Library\Common\Util;

class FileController extends BaseController
{
    public function uploads()
    {
        try {
            $result = D('Files', 'Logic')->uploadOrFail();
            // $new = [];
            // foreach ($result as $k => $v) {
            //     $new[$v['key']][$v['name']] = $v;
            // }
            // var_dump($_FILES);die;

            // $save = [];
            // foreach ($_FILES as $k => $v) {
            //     $save
            // }


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
        try {
            $files = $save = [];
            foreach ($images as $v) {
                preg_match('/^(data:\s*image\/(\w+);base64,)/', $v, $result);
                $save[] = [
                    'str' => str_replace('data:image/'.$result[2].';base64', '', $v),
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
            event(new UploadFileEvent($send_forb));
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

}
