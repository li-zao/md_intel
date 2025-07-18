<?php

namespace app\util;

class Render
{

    /**
     * @return string
     */
    public static function emptyOrFailTip($type = '')
    {
        return '<blockquote class="layui-elem-quote alert-warning">无内容</blockquote>';
    }


    /**
     * @param $headers
     * @return array|mixed
     */
    public static function formatHeaders($headers = [])
    {
        foreach ($headers as &$item) {
            $lang = isset(UtilLang::get('table_headers')[strtolower($item)]) ? UtilLang::get('table_headers')[strtolower($item)] : '';
            if (!empty($lang)) {
                $item = $lang;
            }
        }
        return $headers;
    }

    /**
     * @param $list
     * @param $class
     * @param $collapse
     * @return string
     */
    public static function list2Table($list = [], $class = '', $collapse = [])
    {
        if (empty($list)) {
            return self::emptyOrFailTip();
        }
        $tableHtml = '';
        $headers   = array_keys($list[0]);
        $headers   = self::formatHeaders($headers);
        $tableHtml .= '<div class="table-content"><table class="layui-table ' . $class . '" lay-skin="auto">';
        $tableHtml .= '<thead>';
        $tableHtml .= '<tr>';
        $tableHtml .= '<th>';
        $tableHtml .= implode('</th><th>', $headers);
        $tableHtml .= '</th>';
        $tableHtml .= '</tr>';
        $tableHtml .= '</thead>';
        $tableHtml .= '<tbody>';
        $span      = count($headers);
        $trStyle   = '';
        foreach ($list as $pos => $items) {
            if (!empty($collapse[$pos])) {
                $tableHtml .= sprintf(
                    '<tr><td colspan="%s" class="%s">%s</td></tr>',
                    $span,
                    'collapsible',
                    lang('common.collapsible')
                );
                $trStyle   = ' display:none;';
            }
            $tableHtml .= sprintf('<tr style="%s">', $trStyle);
            foreach ($items as $item) {
                if (is_array($item)) {
                    $item = json_encode($item);
                }
                $tableHtml .= '<td>' . $item . '</td>';
            }
            $tableHtml .= '</tr>';
        }
        $tableHtml .= '</tbody>';
        $tableHtml .= '</table></div>';
        return $tableHtml;
    }
}