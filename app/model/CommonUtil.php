<?php

namespace app\model;

use app\common\Code;
use app\library\Domain;
use app\library\Ip2Region;
use Exception;
use think\facade\Cache;
use think\facade\Log;

class CommonUtil
{
    public const SEARCH_FIELD_TYPE_EQUAL = 'equal';
    public const SEARCH_FIELD_TYPE_ZERO = 'zero';
    public const SEARCH_FIELD_TYPE_LIKE = 'like';
    public const SEARCH_FIELD_TYPE_TIME = 'time';
    public const SEARCH_FIELD_TYPE_FIND = 'find';
    public const SEARCH_FIELD_TYPE_IN = 'in';
    public const MULTI_SEPARATOR = '|';
    public const DEL_FIELD = 'is_del';
    const TYPE_NAME_DICT = [
        'url_normal'     => '正常URL',
        'url_malicious'  => '恶意URL',
        'file_normal'    => '正常文件',
        'file_malicious' => '恶意文件',
    ];
    const TYPE_WHERE_DICT = [
        'url_normal'     => ' type = ' . Url::TYPE_NORMAL,
        'url_malicious'  => ' type = ' . Url::TYPE_MALICIOUS,
        'file_normal'    => ' type = ' . Url::TYPE_NORMAL,
        'file_malicious' => ' type = ' . Url::TYPE_MALICIOUS,
    ];


    /**
     * @param $url
     * @param $data
     * @param $method
     * @param $headers
     * @return mixed
     */
    public static function curlRequest($url, $data = [], $method = 'GET', $headers = [])
    {
        $method = strtoupper($method);
        if ($method == 'GET') {
            $url  = $url . '?' . http_build_query($data);
            $data = [];
        }
        $res = self::curl($url, $data, $method, $headers);
        if (empty($res['header']['http_code']) || $res['header']['http_code'] != 200) {
            return '';
        }
        $json = json_decode($res['body'], true);
        if (is_string($res['body']) && !empty($json)) {
            return $json;
        }
        return $res['body'];
    }

    /**
     * @param $url
     * @param $data
     * @param $method
     * @param $headers
     * @return array
     */
    private static function curl($url, $data, $method = 'GET', $headers = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        if (strtoupper($method) == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $return = curl_exec($ch);
        $header = curl_getinfo($ch);
        return ['header' => $header, 'body' => $return];
    }

    /**
     * 接口返回json格式数据封装
     * @param array $data
     * @param int $code
     * @param ...$arg
     * @return \think\response\Json
     */
    public static function jsonRes($data = [], $code = 0, ...$arg)
    {
        $res = [
            'code' => $code,
            'data' => $data,
        ];
        foreach ($arg as $values) {
            if (is_array($values)) {
                foreach ($values as $kk => $vv) {
                    $res[$kk] = $vv;
                }
            } else {
                $res['msg'] = (string)$values;
            }
        }
        return json($res, 200, [], ['json_encode_param' => JSON_INVALID_UTF8_IGNORE]);
    }

    /**
     * 解析字段搜索条件，支持单字段多次搜索
     * @param $field
     * @param $info
     * @return array
     */
    public static function getSearchData($field = null, $info = [])
    {
        $w = $info['with'] ?? '=';
        $v = $info['value'] ?? strval($info);
        $f = $field;
        // 支持单字段多次搜索
        if (strpos($field, self::MULTI_SEPARATOR) !== false) {
            [$ff, $ww] = explode(self::MULTI_SEPARATOR, $field);
            if (!empty($ww)) {
                $w = $ww;
            }
            if (!empty($ff)) {
                $f = $ff;
            }
        }
        return [$f, $w, $v];
    }

    /**
     * @param $ip
     * @return string|null
     * @throws Exception
     */
    public static function getIpInfo($ip)
    {
        $util = new Ip2Region();
        return $util->search($ip);
    }

    /**
     * @param $ipGeo
     * @param $separator
     * @return string
     */
    public static function filterIpGeo($ipGeo = '', $separator = '|')
    {
        if (empty($ipGeo)) {
            return '';
        }
        $ipGeo = explode('|', $ipGeo);
        $geo   = [];
        foreach ($ipGeo as $value) {
            if (empty($value)) {
                continue;
            }
            $geo[$value] = $value;
        }
        return implode($separator, $geo);
    }

    /**
     * @param $type
     * @param $fields
     * @param $searchParam
     * @param $params
     * @return array|mixed
     */
    public static function getFieldsSearch($type = '', $fields = [], $searchParam = [], $params = [])
    {
        if (empty($type)) {
            return $searchParam;
        }
        foreach ($fields as $pField => $dbField) {
            switch ($type) {
                case self::SEARCH_FIELD_TYPE_EQUAL:
                    if (!empty($params[$pField])) {
                        $searchParam[$dbField] = trim($params[$pField]);
                    }
                    break;
                case self::SEARCH_FIELD_TYPE_ZERO:
                    if (isset($params[$pField]) && !self::emptyNonZero($params[$pField])) {
                        $searchParam[$dbField] = $params[$pField];
                    }
                    break;
                case self::SEARCH_FIELD_TYPE_LIKE:
                    list($ops, $dbField) = $dbField;
                    list($before, $after) = explode(CommonUtil::MULTI_SEPARATOR, $ops);
                    if (empty($params[$pField])) {
                        break;
                    }
                    $param                 = trim($params[$pField]);
                    $searchParam[$dbField] = [
                        'with'  => 'like',
                        'value' => $before . $param . $after
                    ];
                    break;
                case self::SEARCH_FIELD_TYPE_TIME:
                    $v = $params[$pField] ?? '';
                    if (!empty($v)) {
                        [$f, $o] = $dbField;
                        $op = self::getFieldOperator($pField, $params);
                        if (empty($op)) {
                            $op = $o;
                        }
                        $searchParam[$f . self::MULTI_SEPARATOR . $op] = ['with' => $op, 'value' => $v];
                        // if ($op == Code::OPERATOR_LT) {
                        //     $searchParam[$f . self::MULTI_SEPARATOR . Code::OPERATOR_GT] = [
                        //         'with' => Code::OPERATOR_GT,
                        //         'value' => 1
                        //     ];
                        // }
                    }
                    break;
                case self::SEARCH_FIELD_TYPE_FIND:
                    $v = $params[$pField] ?? '';
                    if (!empty($v)) {
                        $searchParam[$dbField] = [
                            'with'  => 'exp',
                            'value' => sprintf("find_in_set('%s', %s)", $v, $dbField)
                        ];
                    }
                    break;
                case self::SEARCH_FIELD_TYPE_IN:
                    $inVal   = $dbField;
                    $dbField = $pField;
                    if (empty($inVal)) {
                        break;
                    }
                    $searchParam[$dbField] = [
                        'with'  => 'in',
                        'value' => $inVal
                    ];
                    break;
            }
        }
        return $searchParam;
    }

    /**
     * 判空
     * 为0时返回false
     * @param $value
     * @return bool
     */
    public static function emptyNonZero($value): bool
    {
        $value = str_replace(' ', '', $value);
        if (strlen($value) == 0) {
            return true;
        }
        if ($value === '0') {
            return false;
        }
        return empty($value);
    }

    /**
     * 获取前端选择 时间对比操作符
     * @param $pField
     * @param $params
     * @return mixed|string
     */
    public static function getFieldOperator($pField, $params)
    {
        $op = $params[$pField . '_op'] ?? '';
        if (!empty($op) && isset(Code::OPERATOR_DICT[$op])) {
            return $op;
        }
        return '';
    }

    /**
     * @param array $params
     * @return array|int[]
     */
    public static function pagination($params = [])
    {
        $page  = intval($params['page'] ?? 1);
        $limit = intval($params['limit'] ?? 10);
        return [$page, $limit];
    }

    /**
     * @param Exception $e
     * @return void
     */
    public static function logError(Exception $e)
    {
        Log::error($e->getMessage());
        Log::info(json_encode($e->getTrace()));
    }


    /**
     * @param $item
     * @return mixed
     */
    public static function dataMasking($item)
    {
        // 手机号脱敏
        if (!empty($item['phone'])) {
            $item['phone'] = substr_replace($item['phone'], '****', 3, 4);
        }
        // 邮箱脱敏
        if (!empty($item['mail'])) {
            list($addr, $domain) = explode('@', $item['mail']);
            $addr         = substr_replace($addr, '****', 1, 4);
            $item['mail'] = implode('@', [$addr, $domain]);
        }
        return $item;
    }

    /**
     * @param $from
     * @return mixed|string
     */
    public static function getMailDomain($from = '')
    {
        $domain = '';
        if (strpos($from, '@') !== false) {
            list(, $domain) = explode('@', $from);
        }
        $sep = [' ', '('];
        foreach ($sep as $s) {
            if (strpos($domain, $s) !== false) {
                list($domain,) = explode($s, $domain);
            }
        }
        return $domain;
    }

    /**
     * @param $url
     * @return string
     */
    public static function getHost($url)
    {
        $res = parse_url($url, PHP_URL_HOST);
        if ($res) {
            return $res;
        }
        $prefix = 'http://';
        $res    = parse_url($prefix . $url, PHP_URL_HOST);
        if ($res) {
            return $res;
        }
        return $url;
    }

    /**
     * @param $string
     * @return mixed|string
     */
    public static function get1stDomain($string)
    {
        if (empty($string)) {
            return '';
        }
        if (substr_count($string, '.') < 1) {
            return $string;
        }
        $string = CommonUtil::getHost($string);
        if (filter_var($string, FILTER_VALIDATE_IP)) {
            return $string;
        }
        try {
            $domain = new Domain($string);
            return $domain->getRegisterable();
        } catch (Exception $e) {
            $str    = explode('.', $string);
            $length = count($str);
            return $str[$length - 2] . '.' . $str[$length - 1];
        }
    }

    /**
     * @param $num
     * @return array
     */
    public static function formatBytes($num)
    {
        $num  = round($num, 4);
        $unit = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $i    = 0;
        while ($num >= 1024) {
            $num /= 1024;
            $i++;
        }
        return [round($num, 4), $unit[$i]];
    }

    /**
     * Ini file array to string
     * @param $data
     * @param $section
     * @return string
     */
    public static function getIniString($data, $section = true)
    {
        $res = [];
        foreach ($data as $key => $val) {
            if ($section) {
                $res[] = sprintf('[%s]', $key);
                foreach ($val as $gKey => $gValue) {
                    $res[] = sprintf('%s=%s', trim($gKey), trim($gValue));
                }
                $res[] = '';
            } else {
                $res[] = sprintf('%s=%s', trim($key), trim($val));
            }
        }
        $res[] = '';
        return implode(PHP_EOL, $res);
    }

    /**
     * @param $format
     * @param $time
     * @return false|string
     */
    public static function getDate($format = 'Y-m-d H:i:s', $time = null)
    {
        if (is_null($time)) {
            $time = time();
        }
        return date($format, $time);
    }


    /**
     * @return mixed|string
     */
    public static function getRandomString()
    {
        return microtime(true);
    }

    /**
     * @param $name
     * @return mixed|string
     */
    public static function getRealFileName($name)
    {
        if (strpos($name, '_') !== false) {
            list(, $name) = explode('_', $name, 2);
        }
        return $name;
    }

    /**
     * @param $xAxis
     * @param $refresh
     * @return array
     */
    public static function getIndexStatistics($xAxis = [], $refresh = 0)
    {
        if (empty($xAxis)) {
            return [];
        }
        $urlModel  = Url::getCommonModel();
        $fileModel = File::getCommonModel();
        $typeList  = [
            'url_normal'     => $urlModel,
            'url_malicious'  => $urlModel,
            'file_normal'    => $fileModel,
            'file_malicious' => $fileModel,
        ];
        foreach ($typeList as $type => $model) {
            $name      = self::TYPE_NAME_DICT[$type];
            $oneSeries = [
                'name'      => $name,
                'type'      => 'line',
                'stack'     => 'Total',
                'label'     => [
                    'show' => false,
                ],
                'areaStyle' => [],
                'emphasis'  => [
                    'focus' => 'series',
                ],
                'data'      => [],
            ];
            foreach ($xAxis as $date) {
                $cacheKey = sprintf('%s_%s', $type, $date);
                if (!empty(Cache::get($cacheKey)) && $refresh == 0) {
                    $oneSeries['data'][] = Cache::get($cacheKey);
                    continue;
                }
                $data = self::getCountByDate($model, $type, $date);
                $ttl  = null;
                if ($date == date('Y-m-d')) {
                    $ttl = env('index.cache_ttl', 60);
                }
                Cache::set($cacheKey, $data, $ttl);
                $oneSeries['data'][] = $data;
            }
            $series[] = $oneSeries;
        }
        return $series;
    }

    /**
     * @param $model
     * @param $type
     * @param $date
     * @return mixed
     */
    private static function getCountByDate($model, $type, $date)
    {
        $info  = clone $model;
        $start = $date . ' 00:00:00';
        $end   = $date . ' 23:59:59';
        return $info->whereRaw(self::TYPE_WHERE_DICT[$type])
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end)
            ->where(self::DEL_FIELD, Code::IS_NO)
            ->count();
    }


    /**
     * @param mixed $string
     * @param string $separator
     * @return array
     */
    public static function explodeStr($string = null, $separator = ','): array
    {
        if (empty($string)) {
            return [];
        }
        if (is_array($string)) {
            return $string;
        }
        $string = trim($string);
        return array_filter(explode($separator, $string));
    }

    /**
     * @param $url
     * @return string
     */
    public static function getUrlHash($url)
    {
        return md5($url);
    }

    /**
     * @param $url
     * @param $redirect
     * @param $options
     * @return array
     */
    public static function getHttpHeader($url = '', $redirect = 0, $options = [])
    {
        if (empty($url)) {
            return [];
        }
        $redirectLimit = 10;
        if ($redirect >= $redirectLimit) {
            return [];
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        if (stripos($url, 'https://') === 0) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        foreach ($options as $key => $value) {
            curl_setopt($ch, $key, $value);
        }
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            return [curl_error($ch)];
        }
        curl_close($ch);
        $format   = function ($string) {
            $list  = explode(PHP_EOL, $string);
            $res   = [];
            $alias = [
                'content-type'   => ['content_type', 'strval'],
                'content-length' => ['content_length', 'intval'],
                'location'       => ['location', 'strval'],
            ];
            foreach ($list as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                if (stripos($line, 'HTTP/') === 0) {
                    list(, $code,) = explode(' ', $line);
                    $res['http_code'] = $code;
                    continue;
                }
                if (stripos($line, ':') !== false) {
                    list($key, $value) = explode(':', $line, 2);
                    $key       = strtolower(trim($key));
                    $value     = trim($value);
                    $res[$key] = $value;
                    if (isset($alias[$key])) {
                        list($newKey, $exec) = $alias[$key];
                        if (is_callable($exec)) {
                            $value = call_user_func($exec, $value);
                        }
                        $res[$newKey] = $value;
                    }
                }
            }
            return $res;
        };
        $response = $format($response);
        if (
            ($response['http_code'] == 301 || $response['http_code'] == 302)
            && !empty($response['location'])
            && $redirect < $redirectLimit
        ) {
            $location = $response['location'];
            return self::getHttpHeader($location, $redirect + 1);
        }
        return $response;
    }

    /**
     * @param $url
     * @param $data
     * @param $method
     * @param $header
     * @param $options
     * @return array|false
     */
    public static function httpRequest($url, $data = [], $method = 'GET', $header = [], $options = [])
    {
        if (empty($url)) {
            return false;
        }
        $ch = curl_init();
        switch ($method) {
            case 'GET':
                if (!empty($data)) {
                    $url = $url . '?' . http_build_query($data);
                }
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                break;
        }
        $agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        // curl_setopt($ch, CURLOPT_COOKIESESSION, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $agent);
        foreach ($options as $key => $value) {
            curl_setopt($ch, $key, $value);
        }
        if (!empty($header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }
        if (stripos($url, 'https://') === 0) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        $result     = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body       = mb_substr($result, $headerSize);
        $rawHeaders = mb_substr($result, 0, $headerSize);
        $headers    = [];
        if (!empty($rawHeaders)) {
            $rawHeaders = explode("\r\n", $rawHeaders);
            foreach ($rawHeaders as $key => $value) {
                if (strpos($value, ':') !== false) {
                    list($k, $v) = explode(':', $value, 2);
                    $headers[$k] = $v;
                } elseif (!empty($value)) {
                    $headers[] = $value;
                }
            }
        }
        $headers = array_merge(curl_getinfo($ch), $headers);
        $charset = 'utf-8';
        if (stripos($headers['content_type'], 'charset=') !== false) {
            list(, $charset) = explode('charset=', strtolower($headers['content_type']));
        }
        if (strpos($charset, 'utf') === false) {
            @$body = iconv($charset, 'UTF-8//IGNORE', $body);
        }
        return [
            'code'   => curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'error'  => curl_error($ch),
            'body'   => $body,
            'header' => $headers,
        ];
    }

    /**
     * @param $url
     * @param $timeout
     * @return array|false
     */
    public static function getTLSInfo($url, $timeout = 5)
    {
        if (stripos($url, 'https://') === false) {
            $url = 'https://' . $url;
        }
        $parseInfo = parse_url($url, PHP_URL_HOST);
        $get       = stream_context_create(array("ssl" => array("capture_peer_cert" => true)));
        @$read = stream_socket_client("ssl://" . $parseInfo . ":443", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $get);
        @$cert = stream_context_get_params($read);
        return empty($cert['options']['ssl']['peer_certificate']) ? [] : openssl_x509_parse($cert['options']['ssl']['peer_certificate']);
    }


    /**
     * 随机字符串检测
     * @param $word
     * @return bool
     */
    public static function isRandomWord($word)
    {
        if (defined('PROJECT_ROOT')) {
            $dictFile = PROJECT_ROOT . '/data/randomDict.json';
        } else {
            $dictFile = __DIR__ . '/../data/randomDict.json';
        }
        $randomDict                 = json_decode(file_get_contents($dictFile), true);
        $commonBigramsThreshold     = 0.1;
        $uncommonBigramsThreshold   = 0.3;
        $duplicatedBigramsThreshold = 0.33;
        $length                     = strlen($word);
        if ($length < 4 || !preg_match("/^[a-zA-Z0-9]*/", $word)) {
            return false;
        }
        $word    = strtolower($word);
        $bigrams = [];
        for ($i = 0; $i < $length - 1; $i++) {
            $bigrams[] = substr($word, $i, 2);
        }
        $commonBigramsNum = 0;
        foreach ($bigrams as $item) {
            if (is_numeric($item)) {
                $commonBigramsNum += 1;
                continue;
            }
            $threshold = $randomDict[$item] ?? 0;
            if ($threshold > $commonBigramsThreshold) {
                $commonBigramsNum += 1;
            }
        }
        $wordsLength          = count($bigrams);
        $uncommonBigramsNum   = $wordsLength - $commonBigramsNum;
        $duplicatedBigramsNum = $wordsLength - count(array_unique($bigrams));
        if ($uncommonBigramsNum / $wordsLength > $uncommonBigramsThreshold) {
            return true;
        }
        if ($duplicatedBigramsNum / $wordsLength > $duplicatedBigramsThreshold) {
            return true;
        }
        return false;
    }


    /**
     * @param string $string
     * @return false|string
     */
    public static function execShell(string $string)
    {
        return exec($string);
    }

    /**
     * @param $filePath
     * @param $limit
     * @return false|string
     */
    public static function getFileContent($filePath, $limit = 0)
    {
        // 读取文件的前$limit长度的内容
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return 'exists || readable';
        }
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return 'fopen';
        }
        try {
            $content = $limit > 0 ? fread($handle, $limit) : fread($handle, filesize($filePath));
        } finally {
            fclose($handle);
        }
        return $content ?: 'empty content';
    }

    /**
     * @param $filePath
     * @param $lineLimit
     * @return false|string
     */
    public static function getFileLines($filePath, $lineLimit = 0)
    {
        try {
            $fd = fopen($filePath, 'r');
            if (!$fd) {
                return false;
            }
            $counter = 0;
            $lines   = [];
            while (!feof($fd)) {
                $line = fgets($fd);
                if ($lineLimit > 0 && $counter >= $lineLimit) {
                    break;
                }
                $counter++;
                $lines[] = $line;
            }
            fclose($fd);
            return implode('', $lines);
        } catch (Exception $e) {
            CommonUtil::logError($e);
        }
        return false;
    }

    /**
     * @param $url
     * @param $options
     * @return string
     */
    public static function formatUrl($url = '', $options = [])
    {
        if (strpos($url, 'http') === false) {
            $url = 'http://' . ltrim($url, "://");
        }
        $url    = html_entity_decode($url);
        $cut    = $options['cut'] ?? true;
        $clean  = $options['clean'] ?? false;
        $decode = $options['decode'] ?? false;
        if ($decode) {
            $url = urldecode($url);
        }
        $url = mb_convert_encoding($url, 'UTF-8', 'UTF-8');
        if ($clean) {
            $parsed = parse_url($url);
            $query  = $parsed['query'] ?? '';
            if (isset($parsed['scheme'])) {
                $url = sprintf(
                    '%s://%s%s%s',
                    $parsed['scheme'],
                    $parsed['host'] ?? '',
                    $parsed['path'] ?? '',
                    !empty($query) ? '?' . $query : ''
                );
            }
        }
        if ($cut) {
            return mb_strcut($url, 0, 255);
        }
        return $url;
    }

    /**
     * 组装富文本编辑图片上传返回数据
     * @param string $src
     * @return array
     */
    public static function getEditorImageData(string $src = '')
    {
        $fileNames = explode('/', $src);
        $fileName  = array_pop($fileNames);
        $fileName  = $fileName ?: basename($src);
        $fileID    = str_replace(['/', '\\', '.'], '_', $fileName);
        $ext       = strtolower(pathinfo($src, PATHINFO_EXTENSION));
        $icon      = Code::EXT_ICON_DICT[$ext] ?? Code::DEFAULT_ICON;
        return [
            'src'  => $src,
            'id'   => $fileID,
            'base' => $fileName,
            'ext'  => $ext,
            'icon' => $icon,
        ];
    }

    /**
     * 文件URL转路径
     * @param string $url
     * @return string
     */
    public static function url2Path($url = '')
    {
        $info = parse_url($url);
        $path = public_path($info['path'] ?? '');
        return self::formatPath($path);
    }

    /**
     * 格式化路径
     * @param string $path
     * @return string
     */
    public static function formatPath($path = '')
    {
        $path = str_replace('\\', '/', $path);
        return rtrim($path, '/');
    }

    /**
     * 截图
     * @param $url
     * @param $path
     * @param $resize
     * @return array
     */
    public static function makeScreenshot($url, $path, $resize = false)
    {
        $cmd = "/usr/local/bin/phantomjs /opt/bin/screenshot.js '" . $url . "' '" . $path . "'";
        $res[] = exec($cmd);
        if ($resize !== false) {
            $exec_str = sprintf("convert %s", $resize);
            $res[] = exec($exec_str);
        }
        return $res;
    }
}
