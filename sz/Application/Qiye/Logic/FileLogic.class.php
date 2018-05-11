<?php
/**
 * File: FileLogic.class.php
 * User: sakura
 * Date: 2017/11/1
 */

namespace Qiye\Logic;

use Library\Common\Util;
use Qiye\Common\ErrorCode;
use Qiye\Logic\BaseLogic;
use Qiye\Model\BaseModel;
use Library\Crypt\AuthCode;
use Think\Upload;

class FileLogic extends BaseLogic
{

    protected $_file_type
        = [
            'jpeg', 'jpg', 'png', 'gif', 'bmp', '3g2', '3gp', 'asf', 'avi', 'f4v', 'flv', 'mov', 'mkv', 'mov', 'mp4', 'mpeg', 'mpg', 'rm', 'rmvb', 'swf', 'vob', 'wmv', 'mp3', 'wma', 'wav', 'amr',
        ];

    public function upload()
    {
        $upload = new Upload();        // 实例化上传类
        $upload->maxSize = 10485760;    // 设置附件上传大小
        $upload->exts = ['jpeg', 'jpg', 'png', 'gif', 'bmp'];// 设置附件上传类型
        $upload->rootPath = 'Uploads/'; // 设置附件上传根目录
        // $upload->savePath  =     date('Y-m-d'); // 设置附件上传（子）目录

        if (empty($_FILES['image'])) {
            $this->throwException(ErrorCode::SYS_REQUEST_PARAMS_MISSING, '请上传图片');
        }

        // 上传文件
        $info = $upload->uploadOne($_FILES['image']);
        if (!$info) {// 上传错误提示错误信息
            $this->throwException(ErrorCode::WORKER_FILE_UPLOAD_FAIL, $upload->getError());
        }

        $path = '/'.$upload->rootPath . $info['savepath'] . $info['savename'];

        return [
            'image_url'   => Util::getServerFileUrl($path),
            'submit_path' => $path,
        ];
    }
}