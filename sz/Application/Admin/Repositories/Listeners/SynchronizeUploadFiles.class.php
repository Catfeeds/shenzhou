<?php
/**
 * File: SynchronizeUploadFiles.class.php
 * User: xieguoqiu
 * Date: 2016/12/23 10:26
 */

namespace Admin\Repositories\Listeners;

use Admin\Repositories\Events\UploadFileEvent;
use Common\Common\Repositories\Events\EventAbstract;
use Common\Common\Repositories\Listeners\ListenerInterface;
use GuzzleHttp\Client;

class SynchronizeUploadFiles implements ListenerInterface
{

    /**
     * @param UploadFileEvent $event
     */
    public function handle(EventAbstract $event)
    {
        try {
            $client = new Client();
            foreach ($event->files as $file) {
                $multipart = [];

                $handler = fopen(realpath($file['url']), 'r');
                
                $multipart[] = [
                    'name' => 'file',
                    'contents' => $handler,
                ];

                $params = [
                    'dir' => dirname($file['url']),
                ];
                $url = C('BACKEND_B_SITE') . "/Public/serverUpload?" . http_build_query($params);
                $client->request('POST', $url, ['multipart' => $multipart]);
                fclose($handler);
            }
        } catch (\Exception $e) {

        }
    }


}
