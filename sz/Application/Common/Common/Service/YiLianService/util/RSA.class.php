<?php
/**
 * @author fengmuhai
 * @date 2016-12-28
 */
namespace Common\Common\Service\YiLianService\util;

class RSA {
    public static function encrypt($data, $pubKey){
        $sign = '';
        if($pubKey){
            // $pubKey是java传过来的经过base64编码的公钥(一般是M字头的)"; 
            // $pem = chunk_split($pubKey,64,"\n");//转换为pem格式的公钥
            // $pem = "-----BEGIN PUBLIC KEY-----\n".$pem."-----END PUBLIC KEY-----\n";
            // $pubKey = openssl_pkey_get_public($pem);//获取公钥内容
            $res = openssl_get_publickey($pubKey);
            openssl_public_encrypt($data, $sign, $res);
            openssl_free_key($res);
            //base64编码
            $sign = base64_encode($sign);
        }
        return $sign;
    }
    
}
//$GDYILIAN_CERT_PUB_64 =
//'-----BEGIN PUBLIC KEY-----
//MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDJ1fKGMV/yOUnY1ysFCk0yPP4b
//fOolC/nTAyHmoser+1yzeLtyYsfitYonFIsXBKoAYwSAhNE+ZSdXZs4A5zt4EKoU
//+T3IoByCoKgvpCuOx8rgIAqC3O/95pGb9n6rKHR2sz5EPT0aBUUDAB2FJYjA9Sy+
//kURxa52EOtRKolSmEwIDAQAB
//-----END PUBLIC KEY-----';
//$keyEncrypt = RSA::encrypt("123456", $GDYILIAN_CERT_PUB_64);
//echo $keyEncrypt;