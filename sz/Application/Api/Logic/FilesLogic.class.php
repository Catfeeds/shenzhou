<?php
/**
* 
*/
namespace Api\Logic;

use Api\Logic\BaseLogic;
use Api\Model\BaseModel;
use Api\Common\ErrorCode;
use Api\Repositories\Events\UploadFileEvent;
use Library\Common\Util;

class FilesLogic extends BaseLogic
{
	protected $_savr_url = [
        '2'    => './Uploads/',
        '3'    => './Uploads/',
        '4'    => './Uploads/',
        '5'    => './Uploads/',
    ];
    protected $_file_type = [
        'jpeg', 'jpg', 'png', 'gif', 'bmp', '3g2', '3gp', 'asf', 'avi', 'f4v', 'flv', 'mov', 'mkv', 'mov', 'mp4', 'mpeg', 'mpg', 'rm', 'rmvb', 'swf', 'vob', 'wmv', 'mp3', 'wma', 'wav', 'amr'
    ];
    protected $_file_size = [
        '4' => 10485760, // 10M => 10*1024Kb => 10*1024*1024 B
        '5' => 10485760,
    ];

    // UPLOAD_FILE_TYPE_WORNG  不支持上传文件类型
    public function upload()
    {
        try {
        	return $this->uploadOrFail();
        } catch (\Exception $e) {        	
        	return $e;
        }
    }

    public function uploadOrFail()
    {
        $upload = new \Think\Upload();        // 实例化上传类
        $upload->maxSize   =     10485760 ;    // 设置附件上传大小
        $upload->exts      =     $this->_file_type;// 设置附件上传类型
        $upload->rootPath  =     './Uploads/'; // 设置附件上传根目录
        // $upload->savePath  =     date('Y-m-d'); // 设置附件上传（子）目录
        // 上传文件 
        $info   =   $upload->upload();
        if (!$info) {// 上传错误提示错误信息
            $this->throwException(ErrorCode::FILE_UPLOAD_WORNG, $upload->getError());
        }

        $files = [];
        foreach ($info as $k => $v) {
            // $v['url'] = Util::getServerFileUrl($upload->rootPath.$v['savepath'].$v['savename']);
            $v['url'] = $upload->rootPath.$v['savepath'].$v['savename'];
            $files[$k] = $v;
        }

//        event(new UploadFileEvent($files));

        return $files;
    }
    
}
