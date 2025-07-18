<?php

namespace app\common;

// 常用状态码
class Code
{
    public const IS_YES = 1;
    public const IS_NO = 0;
    public const IS_DICT = [
        self::IS_YES => '是',
        self::IS_NO => '否',
    ];

    public const API_YES = 0;
    public const API_NO = 1;

    public const DATA_MASK = '***';
    public const OPERATOR_EQ = '=';
    public const OPERATOR_GT = '>=';
    public const OPERATOR_LT = '<=';
    public const OPERATOR_DICT = [
        self::OPERATOR_EQ => '等于',
        self::OPERATOR_GT => '大于',
        self::OPERATOR_LT => '小于',
    ];

    public const DEFAULT_ICON = 'fa-light fa-file';

    public const ICON_CODE     = 'fa-light fa-file-code';
    public const ICON_VIDEO    = 'fa-light fa-file-video';
    public const ICON_AUDIO    = 'fa-light fa-file-audio';
    public const ICON_RAR      = 'fa-light fa-file-zipper';
    public const ICON_IMAGE    = 'fa-light fa-image';
    public const ICON_PPT      = 'fa-light fa-file-ppt';
    public const ICON_EXCEL    = 'fa-light fa-file-xls';
    public const ICON_WORD     = 'fa-light fa-file-word';
    public const ICON_PDF      = 'fa-light fa-file-pdf';
    public const ICON_DATABASE = 'fa-light fa-database';
    public const ICON_TEXT     = 'fa-light fa-file-lines';
    public const EXT_ICON_DICT = [
        // AUDIO
        'aac'  => self::ICON_AUDIO,
        'adt'  => self::ICON_AUDIO,
        'adts' => self::ICON_AUDIO,
        'avi'  => self::ICON_AUDIO,
        'cda'  => self::ICON_AUDIO,
        'm4a'  => self::ICON_AUDIO,
        'mp3'  => self::ICON_AUDIO,
        'wav'  => self::ICON_AUDIO,
        'aif'  => self::ICON_AUDIO,
        'aiff' => self::ICON_AUDIO,
        'flac' => self::ICON_AUDIO,
        'ape'  => self::ICON_AUDIO,
        'mid'  => self::ICON_AUDIO,
        'wma'  => self::ICON_AUDIO,
        'ra'   => self::ICON_AUDIO,
        'vqf'  => self::ICON_AUDIO,
        // CODE
        'aspx' => self::ICON_CODE,
        'bat'  => self::ICON_CODE,
        'exe'  => self::ICON_CODE,
        'htm'  => self::ICON_CODE,
        'html' => self::ICON_CODE,
        'php'  => self::ICON_CODE,
        'c'    => self::ICON_CODE,
        'cpp'  => self::ICON_CODE,
        'py'   => self::ICON_CODE,
        'java' => self::ICON_CODE,
        'reg'  => self::ICON_CODE,
        // IMAGE
        'bmp'  => self::ICON_IMAGE,
        'gif'  => self::ICON_IMAGE,
        'jpg'  => self::ICON_IMAGE,
        'jpeg' => self::ICON_IMAGE,
        'png'  => self::ICON_IMAGE,
        'dib'  => self::ICON_IMAGE,
        'dif'  => self::ICON_IMAGE,
        'pcp'  => self::ICON_IMAGE,
        'eps'  => self::ICON_IMAGE,
        'iff'  => self::ICON_IMAGE,
        'mpt'  => self::ICON_IMAGE,
        'tif'  => self::ICON_IMAGE,
        'tiff' => self::ICON_IMAGE,
        'cdr'  => self::ICON_IMAGE,
        'wmf'  => self::ICON_IMAGE,
        'pcd'  => self::ICON_IMAGE,
        'psd'  => self::ICON_IMAGE,
        'pdd'  => self::ICON_IMAGE,
        'tga'  => self::ICON_IMAGE,
        'webp' => self::ICON_IMAGE,
        // DOC
        'doc'  => self::ICON_WORD,
        'docm' => self::ICON_WORD,
        'docx' => self::ICON_WORD,
        'dot'  => self::ICON_WORD,
        'dotx' => self::ICON_WORD,
        // VIDEO
        'flv'  => self::ICON_VIDEO,
        'mov'  => self::ICON_VIDEO,
        'mp4'  => self::ICON_VIDEO,
        'mpeg' => self::ICON_VIDEO,
        'wmv'  => self::ICON_VIDEO,
        'mpg'  => self::ICON_VIDEO,
        'rm'   => self::ICON_VIDEO,
        'swf'  => self::ICON_VIDEO,
        'ram'  => self::ICON_VIDEO,
        // RAR
        'iso'  => self::ICON_RAR,
        'msi'  => self::ICON_RAR,
        'rar'  => self::ICON_RAR,
        'zip'  => self::ICON_RAR,
        'gzip' => self::ICON_RAR,
        'bz2'  => self::ICON_RAR,
        'xz'   => self::ICON_RAR,
        '7z'   => self::ICON_RAR,
        'tar'  => self::ICON_RAR,
        'gz'   => self::ICON_RAR,
        // PDF
        'pdf'  => self::ICON_PDF,
        // PPT
        'pot'  => self::ICON_PPT,
        'potm' => self::ICON_PPT,
        'potx' => self::ICON_PPT,
        'ppam' => self::ICON_PPT,
        'pps'  => self::ICON_PPT,
        'ppsm' => self::ICON_PPT,
        'ppsx' => self::ICON_PPT,
        'ppt'  => self::ICON_PPT,
        'pptm' => self::ICON_PPT,
        'pptx' => self::ICON_PPT,
        'sldm' => self::ICON_PPT,
        'sldx' => self::ICON_PPT,
        // TEXT
        'txt'  => self::ICON_TEXT,
        // EXCEL
        'xla'  => self::ICON_EXCEL,
        'xlam' => self::ICON_EXCEL,
        'xll'  => self::ICON_EXCEL,
        'xlm'  => self::ICON_EXCEL,
        'xls'  => self::ICON_EXCEL,
        'xlsm' => self::ICON_EXCEL,
        'xlsx' => self::ICON_EXCEL,
        'xlt'  => self::ICON_EXCEL,
        'xltm' => self::ICON_EXCEL,
        'xltx' => self::ICON_EXCEL,
        'xps'  => self::ICON_EXCEL,
        // DATABASE
        'mdb'  => self::ICON_DATABASE,
        'mdf'  => self::ICON_DATABASE,
        'myd'  => self::ICON_DATABASE,
        'db '  => self::ICON_DATABASE,
        'dbf'  => self::ICON_DATABASE,
        'wdb'  => self::ICON_DATABASE,
        'sql'  => self::ICON_DATABASE,
    ];
}
