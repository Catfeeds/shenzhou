<?php
/**
 * File: TokenService.class.php
 * User: xieguoqiu
 * Date: 2016/12/21 10:50
 */

namespace Common\Common\Service;

class TokenService
{

    const HEADER_TOKEN_FIELD = 'token';

    protected $headers = [];

    /**
     * @var static $instance
     */
    protected static $instance = null;

    private function __construct()
    {
        $this->headers = getallheaders();
    }

    /**
     * @return static
     */
    public static function getInstance()
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        
        return static::$instance;
    }

    public function __get($name)
    {
        return static::$instance->headers[$name];
    }

}
