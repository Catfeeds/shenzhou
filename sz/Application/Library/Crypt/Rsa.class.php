<?php
namespace Library\Crypt;

class Rsa {
 
    /**     
     * 获取私钥     
     * @return bool|resource     
     */    
    private static function getPrivateKey($privKey)
    {
        return openssl_pkey_get_private($privKey);    
    }    

    /**     
     * 获取公钥     
     * @return bool|resource     
     */    
    private static function getPublicKey($publicKey)
    {
        return openssl_pkey_get_public($publicKey);    
    }    

    /**     
     * 私钥加密     
     * @param string $data
     * @return null|string     
     */    
    public static function privEncrypt($data = '',$privKey)
    {        
        if (!is_string($data)) {            
            return null;       
        }        
        return openssl_private_encrypt($data,$encrypted,self::getPrivateKey($privKey)) ? base64_encode($encrypted) : null;
    }    

    /**     
     * 公钥加密     
     * @param string $data     
     * @return null|string     
     */    
    public static function publicEncrypt($data = '',$publicKey)
    {        
        if (!is_string($data)) {            
            return null;        
        }        
        return openssl_public_encrypt($data,$encrypted,self::getPublicKey($publicKey)) ? base64_encode($encrypted) : null;
    }    

    /**     
     * 私钥解密     
     * @param string $encrypted     
     * @return null     
     */    
    public static function privDecrypt($encrypted = '',$privKey)
    {        
        if (!is_string($encrypted)) {            
            return null;        
        }        
        return (openssl_private_decrypt(base64_decode($encrypted), $decrypted, self::getPrivateKey($privKey))) ? $decrypted : null;
    }    

    /**     
     * 公钥解密     
     * @param string $encrypted     
     * @return null     
     */    
    public static function publicDecrypt($encrypted = '',$publicKey)
    {        
        if (!is_string($encrypted)) {            
            return null;        
        }        
    return (openssl_public_decrypt(base64_decode($encrypted), $decrypted, self::getPublicKey($publicKey))) ? $decrypted : null;
    }
}

//openssl genrsa -out rsa_private_key.pem 1024
//生成原始 RSA私钥文件
//openssl pkcs8 -topk8 -inform PEM -in rsa_private_key.pem -outform PEM -nocrypt -out private_key.pem
//将原始 RSA私钥转换为 pkcs8格式
//openssl rsa -in rsa_private_key.pem -pubout -out rsa_public_key.pem
//生成RSA公钥
//我们将私钥rsa_private_key.pem用在服务器端，公钥发放给android跟ios等前端。
