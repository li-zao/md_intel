<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * @mixin \think\Model
 */
class UrlScreen extends Model
{
    /**
     * @param $list
     * @return array
     */
    public static function formatList($list)
    {
        $res = [];
        foreach ($list as $src) {
            $res[] = CommonUtil::getEditorImageData($src);
        }
        return $res;
    }
}
