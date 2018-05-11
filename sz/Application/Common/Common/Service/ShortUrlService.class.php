<?php
/**
 * File: WorkerService.class.php
 * Function:
 * User: sakura
 * Date: 2017/11/22
 */

namespace Common\Common\Service;

use Hashids\Hashids;

class ShortUrlService
{

    protected $hashids;
    public function __construct()
    {
        $minLen = C('MIN_HASH_LENGTH');
        $this->hashids = new Hashids('szlbv', $minLen);
    }

    function encodeShortLink($longUrl)
    {
        $longUrl = trim($longUrl);
        if (empty($longUrl))
            return;
        $data = D('ShortUrl')->getOne(['link'=>$longUrl]);

        if ($data) {
            $code = $this->hashids->encode($data['id']);
        } else {
            $id = D('ShortUrl')->insert(['link'=>$longUrl]);
            $code = $this->hashids->encode($id);
            D('ShortUrl')->update(['id'=>$id], ['code'=>$code]);
        }
        return $code;
    }

    function decodeShortLink($code)
    {
        $ids = $this->hashids->decode($code);
        return $ids;
    }

}