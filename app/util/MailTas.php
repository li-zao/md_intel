<?php

namespace app\util;

use app\model\CommonUtil;
use Exception;
use think\facade\Log;

class MailTas
{
    public $desKey = null;
    public $apiKey = null;
    public $config = null;
    public $errInfo = '';
    public $API_HOST = 'http://192.168.10.67:8088';
    const API_URL_SCAN = '/internal/intelscan';
    const API_URL_GET_SCAN = '/internal/intelgetscan';

    public function __construct()
    {
        $this->init();
    }

    /**
     * @param $name
     * @param $value
     * @return void
     * @throws Exception
     */
    public function set($name, $value)
    {
        if (!property_exists($this, $name)) {
            throw new Exception("property not exists: " . $name);
        }
        $this->$name = $value;
    }

    /**
     * @param $msg
     * @return bool
     */
    private function log($msg)
    {
        Log::info($msg);
        return true;
    }

    /**
     * @param $force
     * @return void
     * @throws Exception
     */
    private function init($force = false)
    {
        $this->desKey = 'da55151c';
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
                    $header[] = 'Content-Type: application/json';
                }
                break;
        }
        $agent     = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $genHeader = function ($data) use ($header) {
            if (!is_array($data)) {
                $data = json_decode($data, true);
            }
            krsort($data);
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
        if (!empty($this->config['proxy'])) {
            curl_setopt($ch, CURLOPT_PROXY, $this->config['proxy']);
        }
        $result = curl_exec($ch);
        $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code != 200) {
            $msg           = curl_error($ch) . $result;
            $this->errInfo = $msg;
            $this->log($msg);
            return false;
        }
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body       = mb_substr($result, $headerSize);
        // $this->log(json_encode([$url, $params, $code, $body]));
        if (json_decode($body) !== null) {
            return json_decode($body, true);
        }
        return $body;
    }

    /**
     * @param $url
     * @return int
     */
    public function urlScan($url = '')
    {
        try {
            $this->errInfo = '';
            $url           = CommonUtil::formatUrl($url);
            if (empty($url)) {
                return 0;
            }
            $params = [
                'url' => $url,
            ];
            $res    = $this->query(self::API_URL_SCAN, $params, 'POST');
            if ($res === false) {
                return 0;
            }
            return $res;
        } catch (Exception $e) {
            $this->log($e->getMessage());
            $this->errInfo = $e->getMessage();
            return 0;
        }
    }

    /**
     * @param $id
     * @return array|bool|mixed|string
     */
    public function urlGetScan($id = '')
    {
        try {
            $this->errInfo = '';
            $params = [
                'id' => $id,
            ];
            $res    = $this->query(self::API_URL_GET_SCAN, $params, 'POST');
            if ($res === false) {
                return [];
            }
            return $res;
        } catch (Exception $e) {
            $this->log($e->getMessage());
            $this->errInfo = $e->getMessage();
            return [];
        }
    }
}
