<?php
/**
 * File: AreaController.class.php
 * User: xieguoqiu
 * Date: 2017/4/10 16:40
 */

namespace Admin\Controller;

use Common\Common\Service\ShortUrlService;

class LinkController extends BaseController
{

    public function __construct()
    {
        parent::__construct();
        $code = I('get.url');
        if ($code) {
            import('Common.Common.Service.ShortUrl');
            $short = new ShortUrlService();
            $ids = $short->decodeShortLink($code); //mEPyq4
            if (empty($ids)){
                header('HTTP/1.0 404 Not Found');
                echo 'Unknown link.';
                exit();
            }
            $url = D('ShortUrl')->getOne(['id' => $ids[0]]);
            if ($url) {
                header('Location: ' . $url['link']);
            } else {
                header('HTTP/1.0 404 Not Found');
                echo 'Unknown link.';
            }
        } else {
            header('HTTP/1.0 404 Not Found');
            echo 'Unknown link.';
        }
//        print_r($id);exit;
//        print_r($code);exit;
    }
}

