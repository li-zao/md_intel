<?php

namespace app\util;

use app\model\CommonUtil;
use app\model\Url;
use app\model\UrlHttpCache;
use app\model\UrlVector;
use Exception;
use think\facade\Config;
use think\facade\Log;

class UrlNN
{
    public $desKey = null;
    public $apiKey = null;
    public $config = null;
    public $abnormalRatio = 0;
    public $normalRatio = 0;
    public $registerDays = 365;
    public $queryDomains = [];
    public $vectors = [];
    public $errInfo = '';
    public $API_HOST = 'http://127.0.0.1:9002';
    const API_URL_PREDICT = '/predict';
    const URL_PREDICT_NULL = -1;
    const URL_PREDICT_SKIP = -2;
    const URL_PREDICT_UNKNOWN = -3;
    const URL_PREDICT_OK = 0;
    const URL_PREDICT_MAL = 1;
    const URL_PREDICT_DICT = [
        self::URL_PREDICT_NULL    => 'NULL',
        self::URL_PREDICT_SKIP    => '跳过',
        self::URL_PREDICT_UNKNOWN => '未知',
        self::URL_PREDICT_OK      => '正常',
        self::URL_PREDICT_MAL     => '恶意',
    ];
    public $vectorModel;

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
        $this->config = Config::get('nn');
        $this->desKey = 'da55151c';
        $this->apiKey = $this->config['nn_key'];
        if (!empty($this->config['nn_host'])) {
            $this->API_HOST = $this->config['nn_host'];
        }
        if (!empty($this->config['nn_desKey'])) {
            $this->desKey = $this->config['nn_desKey'];
        }
        if (!empty($this->config['nn_registerDays'])) {
            $this->registerDays = $this->config['nn_registerDays'];
        }
        if (!empty($this->config['nn_queryDomains'])) {
            $this->queryDomains = explode(',', $this->config['nn_queryDomains']);
        }
        $this->vectorModel = new UrlVector();
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
    public function urlPredict($url = '')
    {
        try {
            $this->errInfo = '';
            // $this->vectors       = [];
            // $this->abnormalRatio = $this->normalRatio = 0;
            $url = CommonUtil::formatUrl($url);
            if (empty($url)) {
                $this->errInfo = 'url is empty';
                return self::URL_PREDICT_NULL;
            }
            $this->vectors = $vectors = $this->vectorModel->getCache($url);
            $vectors       = json_decode($vectors['url_vector'], true);
            if (empty($vectors)) {
                $this->errInfo = 'url vector is empty';
                return self::URL_PREDICT_UNKNOWN;
            }
            $vectors = $this->formatVector($vectors);
            $format  = function ($res) {
                $_res = floatval($res[0]) ?: 0;
                $_res = intval($_res * 100);
                if ($_res >= 5000) {
                    return self::URL_PREDICT_MAL;
                }
                return self::URL_PREDICT_OK;
                // list($this->abnormalRatio, $this->normalRatio) = json_decode($res[0], true);
                // $this->normalRatio   = sprintf("%.10f", $this->normalRatio);
                // $this->abnormalRatio = sprintf("%.10f", $this->abnormalRatio);
                // if ($this->normalRatio < $this->abnormalRatio) {
                //     return self::URL_PREDICT_MAL;
                // }
                // return self::URL_PREDICT_OK;
            };
            $format  = function ($str) {
                $data = json_decode(str_replace("'", '"', $str));
                $res  = [];
                foreach ($data as $item) {
                    [$k, $v] = explode(',', $item);
                    [, $k] = explode(':', $k);
                    [, $v] = explode(':', $v);
                    $k       = trim($k);
                    $v       = trim($v);
                    $res[$k] = number_format($v, 10);
                }
                $_res          = $res['phishing'] ?? 0;
                $this->errInfo = $str;
                if ($_res >= 0.5) {
                    return self::URL_PREDICT_MAL;
                }
                return self::URL_PREDICT_OK;
            };
            // $format  = function ($str) {
            //     $str     = str_replace(["'", "%", '"'], '', $str);
            //     $data    = json_decode($str, true);
            //     [$s1, $s2] = $data;
            //     $s1 = $s1 * 100;
            //     $s2 = $s2 * 100;
            //     $s1 = intval($s1);
            //     $s2 = intval($s2);
            //     $this->errInfo = $s1 . ' | ' . $s2;
            //     // if ($s1 >= 50 && $s2 >= 50) {
            //     if ($s1 >= 50) {
            //         return self::URL_PREDICT_MAL;
            //     }
            //     return self::URL_PREDICT_OK;
            // };
            $format  = function ($res) {
                $res = str_replace(["'", '%'], '', $res);
                list($abnormalRatio, $normalRatio) = json_decode($res, true);
                $normalRatio   = sprintf("%.10f", $normalRatio);
                $abnormalRatio = sprintf("%.10f", $abnormalRatio);
                if ($normalRatio < $abnormalRatio) {
                    return self::URL_PREDICT_MAL;
                }
                return self::URL_PREDICT_OK;
            };
            $params  = json_encode([
                'url'    => $url,
                'vector' => implode(',', $vectors),
            ]);
            $res     = $this->query(self::API_URL_PREDICT, $params, 'POST');
            $this->errInfo = json_encode($res);
            // {"time":"2025-04-28T13:52:10+08:00","type":"info","msg":"[\"[0.0, 1.0]\",201]"}
            // $this->log(json_encode($res));
            if ($res === false) {
                return self::URL_PREDICT_UNKNOWN;
            }
            return $format($res[0]);
        } catch (Exception $e) {
            $this->log($e->getMessage());
            $this->errInfo = $e->getMessage();
            return self::URL_PREDICT_NULL;
        }
    }

    /**
     * @param $vectors
     * @return array
     */
    public function formatVector($vectors)
    {
        $model = new UrlVector();
        $res   = [];
        foreach ($model->vectorMap as $key => $v) {
            $res[$key] = $vectors[$key] ?? 0;
        }
        return $res;
    }
}
