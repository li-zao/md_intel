<?php

namespace app\util;
class MdApi
{
    public $apiKey = null;
    public $desKey = null;
    public $config = null;

    public $API_HOST = 'http://8.130.24.205/';
    const API_HTTP_REQUEST = '/api/httpRequest';

    public function __construct()
    {
        $this->init();
    }

    public function __destruct()
    {
    }


    /**
     * @param $force
     * @return void
     */
    private function init($force = false)
    {
        $this->desKey = 'da55151c';
        $this->apiKey = 'd1efad72dc5b17dc66a46767c32fff40';
    }

    /**
     * @param $url
     * @param $params
     * @param $method
     * @param $header
     * @return bool|mixed|string
     */
    private function query($url, $params = [], $method = 'GET', $header = [])
    {
        // if (empty($this->apiKey)) {
        //     return [];
        // }
        $url    = $this->API_HOST . $url;
        $ch     = curl_init();
        $method = strtoupper($method);
        switch ($method) {
            case 'GET':
                if (!empty($params)) {
                    $url = $url . '?' . http_build_query($params);
                }
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                if (is_string($params)) {
                    $header[] = 'Content-Type:application/json';
                }
                break;
        }
        $agent     = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $genHeader = function ($data) use ($header) {
            ksort($data);
            $des      = new Des($this->desKey);
            $header[] = sprintf(
                'Authorization:%s',
                $des->encrypt($this->apiKey . '.' . md5($this->apiKey . http_build_query($data))));
            return $header;
        };
        $header    = $genHeader($params);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_COOKIESESSION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $agent);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        if (strpos(strtolower($url), 'https://') === 0) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        if (!empty($this->config->proxy)) {
            curl_setopt($ch, CURLOPT_PROXY, $this->config->proxy);
        }
        $result = curl_exec($ch);
        $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code != 200) {
            $return_data['http_code'] = $code;
            return $return_data;
        }
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body       = mb_substr($result, $headerSize);
        if (json_decode($body) !== null) {
            return json_decode($body, true);
        }
        return $body;
    }

    /**
     * @param $url
     * @return mixed|string
     */
    private function formatUrl($url = '')
    {
        if (strpos($url, 'http') === false) {
            $url = 'http://' . ltrim($url, '://');
        }
        return $url;
    }


    /**
     * @param $url
     * @param $data
     * @param $method
     * @param $header
     * @param $options
     * @return array|bool|mixed|string
     */
    public function httpRequest($url, $data = [], $method = 'GET', $header = [], $options = [])
    {
        $queryData = [
            'url'     => $url,
            'data'    => json_encode($data),
            'method'  => $method,
            'header'  => json_encode($header),
            'options' => json_encode($options),
        ];
        return $this->query(self::API_HTTP_REQUEST, $queryData, 'POST');
    }
}