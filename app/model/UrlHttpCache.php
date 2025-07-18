<?php
declare (strict_types=1);

namespace app\model;

use think\Model;

/**
 * @mixin \think\Model
 */
class UrlHttpCache extends Model
{

    /**
     * @param $url
     * @param $options
     * @return array
     */
    public function getByUrl($url, $options = [])
    {
        $field = $options['field'] ?? '*';
        $record = self::field($field)->where('url_hash', CommonUtil::getUrlHash($url))->findOrEmpty();
        if (!$record->isEmpty()) {
            return $record->toArray();
        }
        return [];
    }

    /**
     * @param $hash
     * @param $options
     * @return array
     */
    public function getByHash($hash, $options = [])
    {
        $field = $options['field'] ?? '*';
        $record = self::field($field)->where('url_hash', $hash)->findOrEmpty();
        if (!$record->isEmpty()) {
            return $record->toArray();
        }
        return [];
    }

    /**
     * @param $data
     * @param $id
     * @return UrlHttpCache|int|string
     */
    public function updateCache($data, $id = 0)
    {
        if (!empty($id)) {
            return $this->update($data, ['id' => $id]);
        }
        return $this->insert($data);
    }

    /**
     * @param $str
     * @return array
     */
    public static function reformatJson($str)
    {
        $data = $str;
        $body = '';
        $reformatJson = $data[0];
        $stop         = false;
        $checkStart   = function ($s) use ($data) {
            if (isset($data[$s]) && $data[$s] == '"' && $data[$s - 1] !== '\\') {
                return false;
            }
            return true;
        };
        $checkStop    = function ($s) use ($data) {
            $res = '';
            for ($j = 0; $j < 8; $j++) {
                if (isset($data[$s])) {
                    $res .= $data[$s];
                    $s--;
                }
            }
            return strrev($res);
        };
        for ($i = 1; $i < strlen($data); $i++) {
            if ($checkStop($i) === '"body":"') {
                $stop = true;
            }
            if (!$stop) {
                $reformatJson .= $data[$i];
            } else {
                $body .= $data[$i];
                $stop = $checkStart($i + 1);
                if (!$stop) {
                    $reformatJson .= '"';
                }
            }
        }
        $body = str_replace(['\n', '\t', '\"', '\/', '\r'], ["\n", "\t", '"', '/', "\r"], $body);
        $body = htmlspecialchars_decode($body);
        $body = html_entity_decode($body);
        return [$reformatJson, $body];
    }
}
