<?php

namespace app\util;
class Des
{
    public $key;
    public $iv; //偏移量

    public function __construct($key, $iv = 0)
    {
        //key长度8
        $this->key = $key;
        if ($iv == 0) {
            $this->iv = $key;
        } else {
            $this->iv = $iv;
        }
    }


    public function encrypt($str)
    {
        $str  = $this->pkcs5Pad($str, 8);
        $sign = openssl_encrypt($str, 'DES-CBC', $this->key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $this->iv);
        return base64_encode($sign);
    }


    //des解密（cbc模式）
    public function decrypt($encrypted)
    {
        $encrypted = base64_decode($encrypted);
        $sign      = @openssl_decrypt($encrypted, 'DES-CBC', $this->key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $this->iv);
        $sign      = $this->pkcs5Unpad($sign);
        return rtrim($sign);

    }

    public function pkcs5Unpad($text)
    {
        $pad = ord($text[strlen($text) - 1]);
        if ($pad > strlen($text)) return false;
        if (strspn($text, chr($pad), strlen($text) - $pad) != $pad) return false;
        return substr($text, 0, -1 * $pad);
    }


    public function pkcs5Pad($text, $blocksize)
    {
        $pad = $blocksize - (strlen($text) % $blocksize);
        return $text . str_repeat(chr($pad), $pad);
    }
}
