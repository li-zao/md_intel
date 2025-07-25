<?php
declare (strict_types=1);

namespace app\model;

use app\common\Code;
use app\library\Domain;
use app\util\ForceUTF8Encode;
use app\util\MdApi;
use app\util\UrlNN;
use app\util\WhoisUtil;
use DOMDocument;
use DOMXPath;
use Exception;
use think\facade\Log;
use think\Model;

/**
 * @mixin \think\Model
 */
class UrlVector extends Model
{

    const SKIP_DOMAIN_KEYWORDS = [
        'google.com',
        'googleapis.com',
        'cloudflare.com',
        'googletagmanager',
        's3.amazonaws',
        'cdn.',
        '.cdn',
    ];
    const SKIP_OUT_LINK_KEYWORDS = [
        '.min.js',
        'jquery.',
        'jquery-',
    ];
    const SKIP_SRC_KEYWORDS = [
        'assets'  => 0,
        'asset'   => 0,
        'static'  => 0,
        'statics' => 0,
    ];
    // 跳过建站托管等平台上的网站
    const SKIP_SITE_HOST = [
        '.dragonparking.com',
        'Suspected Phishing',
        'This website has been reported for',
        'Account Suspended!',
        'www.com.top',
    ];
    const SPECIAL_CHARACTER = '=%&?';
    const SENSITIVE_KEYWORD = ['login', 'secure', 'account'];
    const PATTERN_IP = '(25[0-5]|2[0-4]\d|[0-1]\d{2}|[1-9]?\d)\.(25[0-5]|2[0-4]\d|[0-1]\d{2}|[1-9]?\d)\.(25[0-5]|2[0-4]\d|[0-1]\d{2}|[1-9]?\d)\.(25[0-5]|2[0-4]\d|[0-1]\d{2}|[1-9]?\d)';
    const CC_TLD_LIST = ['ac', 'ad', 'ae', 'af', 'ag', 'ai', 'al', 'am', 'ao', 'aq', 'ar', 'as', 'at', 'au', 'aw', 'ax', 'az', 'ba', 'bb', 'bd', 'be', 'bf', 'bg', 'bh', 'bi', 'bj', 'bm', 'bn', 'bo', 'bq', 'br', 'bs', 'bt', 'bw', 'by', 'bz', 'ca', 'cc', 'cd', 'cf', 'cg', 'ch', 'ci', 'ck', 'cl', 'cm', 'cn', 'co', 'cr', 'cu', 'cv', 'cw', 'cx', 'cy', 'cz', 'de', 'dj', 'dk', 'dm', 'do', 'dz', 'ec', 'ee', 'eg', 'eh', 'er', 'es', 'et', 'eu', 'fi', 'fj', 'fk', 'fm', 'fo', 'fr', 'ga', 'gd', 'ge', 'gf', 'gg', 'gh', 'gi', 'gl', 'gm', 'gn', 'gp', 'gq', 'gr', 'gs', 'gt', 'gu', 'gw', 'gy', 'hk', 'hm', 'hn', 'hr', 'ht', 'hu', 'id', 'ie', 'il', 'im', 'in', 'io', 'iq', 'ir', 'is', 'it', 'je', 'jm', 'jo', 'jp', 'ke', 'kg', 'kh', 'ki', 'km', 'kn', 'kp', 'kr', 'kw', 'ky', 'kz', 'la', 'lb', 'lc', 'li', 'lk', 'lr', 'ls', 'lt', 'lu', 'lv', 'ly', 'ma', 'mc', 'md', 'me', 'mg', 'mh', 'mk', 'ml', 'mm', 'mn', 'mo', 'mp', 'mq', 'mr', 'ms', 'mt', 'mu', 'mv', 'mw', 'mx', 'my', 'mz', 'na', 'nc', 'ne', 'nf', 'ng', 'ni', 'nl', 'no', 'np', 'nr', 'nu', 'nz', 'om', 'pa', 'pe', 'pf', 'pg', 'ph', 'pk', 'pl', 'pm', 'pn', 'pr', 'ps', 'pt', 'pw', 'py', 'qa', 're', 'ro', 'rs', 'ru', 'rw', 'sa', 'sb', 'sc', 'sd', 'se', 'sg', 'sh', 'si', 'sk', 'sl', 'sm', 'sn', 'so', 'sr', 'ss', 'st', 'su', 'sv', 'sx', 'sy', 'sz', 'tc', 'td', 'tf', 'tg', 'th', 'tj', 'tk', 'tl', 'tm', 'tn', 'to', 'tr', 'tt', 'tv', 'tw', 'tz', 'ua', 'ug', 'uk', 'us', 'uy', 'uz', 'va', 'vc', 've', 'vg', 'vi', 'vn', 'vu', 'wf', 'ws', 'ye', 'yt', 'za', 'zm', 'zw'];
    const SLD_LIST = ['net', 'org', 'gov', 'edu', 'mil', 'int'];
    const ABUSED_TLD_LIST = [
        // 'eu', // 欧盟
        'name',
        'co',
        'io', //  British Indian Ocean Territory
        'life',
        'moe',
        'dev',
        'app', // Domain Name System of the Internet. It was launched by Google in 2015 and is intended for use by app developers and related audiences
        'xyz',
        'site',
        // 'ch', // Switzerland
        // 'it', // Italy
        'club',
        'info',
        // 'de', // Germany
        'racing',
        'live',
        // 'ru', // Russia
        // 'cc', // Cocos (Keeling) Islands, a remote territory of Australia in the Indian Ocean.
        'mobi',
        // 'me', // Montenegro, a southeast European country.
        // 'au', // Australia
        // 'pw', // Republic of Palau, an island nation in the Pacific Ocean
        // 'in', // India's top-level domain prefix on the internet, similar to .com or .net in the United States
        // 'fr', // France
        // 'be', // Belgium
        'pro', 'top', 'xin', 'finance', 'bond', 'support', 'help', 'world', 'buzz', 'today', 'icu', 'mom', 'win', 'cyou', 'cfd', 'vip', 'fyi', 'zone', 'click', 'lat', 'services',
        'network', 'sbs', 'lol', 'bar', 'monster', 'shop', 'pics', 'online', 'ink', 'link', 'best', 'work', 'com', 'skin', 'rest', 'fun', 'space', 'one', 'quest', 'digital', 'run', 'cloud', 'store',
    ];
    // @todo 脚本类检测：setTimeout
    const LIST_CONTENT_KEYWORDS = [
        'mailto:',
        'mail()',
        // 'location.href',
        // 'window.location.href',
        'http-equiv="refresh"',
    ];
    const PATTERN_CONTENT_KEYWORD = '法轮|大法|迫害|真.{0,1}善.{0,1}忍|津贴|补贴|福利|个税|退税|税务|高温|薪资|年终|通知|调整|税收|抽查|邮箱管理|管理中心|警惕韩军|月终奖|季度奖|绩效|奖金|补助|五险一金|十三薪|申办|申报|申领|代缴|稅务|稅務|年度纳税|本月纳税|个人薪水|补貼|中秋福利|防暑|高温|降温|升温|奖励|新春礼品|公户年检|救灾资金|人力资源|保障部|财务部|财政部|纳税人|在职人员|全体员工|企业员工|企业全员|企业职工|逾期未提交|点击申报|点击查看|点我查看|通知您|您的密码|立即激活|不要乱调试|系统懒人版|登录超时|重新登录|pc端下载|页面仅支持|pc浏览器|票单|是手机端|显示提示|管理总局|行政管理局|联合下发|登记|逾期视为|弃权领取|多出一笔|不纳入|工资|这是windows|这是mac|手机访问|申请|身份证页|内部文件|禁止外传|监听提交|密码必须|正确的电子|帐户不存在|查看文档|url账号|不匹配|邮箱管理中心|引领企业邮件|系统革新|获取完整的|不只是邮箱|高效办公|迁移系统|升级系统|请输入邮箱账号|请输入密码|简单轻松|轻松高效|获取子域名|生成二级域名|前页面的url|获取数据库|对应的url2|浏览器中打开|进行跳转|诺诺发票|欢迎贵司|贵司下载|下载查询|贵公司发票|已经开具|开具成功|pdf下载|此文件仅支持|电脑查阅|自助出票|查看打印|云票|脑版查询|查询下载|开票成功|整个浏览器|浏览器窗口|扫码二维码|扫码下载|点击下载|财政app|点击激活|点击更新|邮箱验证|验证中心|文档过大|登录qq|登录微信|邮箱功能|超级拦截|邮件的干扰|将会被加密|确保安全|澳彩|新葡京|冊送|开沪送|电子游戏|赚钱|视讯|ag視訊|百家樂|驻冊|駐冊立送|冊秒送|送58礼金|性感女|冊金礼|株式会社|超高返水|以小博大|美女陪|真人直播|直播游|ag真人|炸金花|注册秒送|首存返现|棋牌|专业彩票|带赚团队|资深大神|高手|中奖率|彩票计划|大神导师|带赚计划|包赚|逆天改命|月入百万|仿站小工具|性感|趁屄b|女优|假阳具|美女|少妇|床上等你|缩阴|如少女|真实啪啪|体内膨胀|粗细可调|自动伸缩|超越真人|啪啪啪|夫妻调情|排解寂寞|单身必备|肛兽|巨乳|痴汉|爆乳|嫩穴|av视频|露逼|香穴|美穴|鸡巴|肉棒|骚屄|淫穴|跳蛋|内射|骚货|骚妇|骚女|骚妻|骚穴|大奶|淫乱|小穴|插逼|操逼|日韩成人|无码av|酥胸|美乳|成人视频|成人网站|全国兼职|兼职小姐|楼凤|熟女|爆菊|萝莉|风骚|勾引|自摸|深喉|裸女|思春|嫩屄|大屌|慢慢脱|情趣|炮友|麻豆|纵欲|偷拍|嫩妹|长腿|黄色电影|成人电影|壮阳|手淫|性高潮|淫荡|淫贱|淫水|淫欲|樱唇|兽交|够骚|一夜情|选妃|成人色站|青楼|porn|erotischen fotos|sex eller vennskap|sexuellen fantasien|ha sex|seksuelle behov|sexpartner|sex befriedigt|einen seelenverwandten|smutsigt sex|knulle meg hardt|en dejtingsajt|dating-site|din man hjälper inte|sexuella äventyr|ständig nach sex|seks hebben|je pik wil voelen|je me wilt neuken|mijn man gaat binnenkort op zakenreis|looking for a girl|dating site|adult time|hot single males|a blind date with me|(search for|find|met).{0,2}a man.{0,60}resume old ties.{0,50}new photos|dating app|bunch of single women|do you want to chat for a bit|watch me get kinky|start sex chat|untamed fun|bomb connection|relationship site|hidden yearnings|sultry chat|love connection site|darkest yearnings|orgasming|bigger sex drive|great sex|something sexually|fuckbuddy|big tits and ass|cock in my pussy|sex dating|sex.{0,5}buddy dating|start dating up.{0,20}for sex|Suspected phishing site|Suspected Phishing|potential phishing|do not remove the following links|have you heard of any offer|s2.loli.net.*png|picurl.cn.*jpg|picurl.cn.*jpeg|picurl.cn.*png|picb.cc.*jpg|picb.cc.*jpeg|picb.cc.*png|pic.sl.al.*jpg';
    // @todo 添加域名正则匹配
    const PATTERN_URL_KEYWORD = '';
    // @todo 添加header正则匹配
    const PATTERN_HEADER_KEYWORD = '';
    const PATTERN_HOST_KEYWORD = 'edm|news';
    const PATTERN_FORM = 'http|https|\/\/.*';
    const PATTERN_SUSPECT_LINK = '.*\.php?.*|.*aspx$|.*php$|.*asp$';
    const PATTERN_OUT_LINK = 'http|https.*';
    const PATTERN_SHORT_URL = 'goo.gl|ff.im|4url.cc|litturl.com|xs.md|url.0daymeme.com|tr.im|visibli|post.ly|zapd.co|bre.ad|arseh.at|bit.ly|is.gd|kl.am|links.sharedby.co|ow.ly|surl.ws|tny.im|snipurl.com|tinyurl.com|ur1.ca|vbly.us|xym.kr|twitter-unrolled-urls-spritzer-stream|clck.ru|snip.ly|short.im|qrco.de|cutt.ly|shorte.st|go2l.ink|x.co|t.co|tinyurl|cli.gs|yfrog.com|migre.me|tiny.cc|url4.eu|twit.ac|su.pr|twurl.nl|short.to|BudURL.com|ping.fm|Just.as|bkite.com|snipr.com|fic.kr|loopt.us|doiop.com|short.ie|wp.me|rubyurl.com|om.ly|to.ly|bit.do|lnkd.in|db.tt|qr.ae|adf.ly|bitly.com|cur.lv|ity.im|q.gs|po.st|bc.vc|twitthis.com|u.to|j.mp|buzurl.com|cutt.us|u.bb|yourls.org|prettylinkpro.com|scrnch.me|filoops.info|vzturl.com|qr.net|1url.com|tweez.me|v.gd|link.zip.net|rplg.co';
    const PATTERN_HTTPS = 'DigiCert|Let\'s Encrypt|Comodo|Symantec|GlobalSign|GoDaddy|Entrust|Verisign';
    const PATTERN_SPLIT_URL_2_WORDS = '/[=?\/\.-]/';
    const CONTENT_TYPE_HIGH_POINT = 1;
    const CONTENT_TYPE_LOW_POINT = 0.5;
    // @todo content-type
    const CONTENT_TYPE_HIGH = [
        'application/octet-stream',
        'application/zip',
        'application/x-msdownload',
    ];
    const CONTENT_TYPE_LOW = [
        // 'application/msword',
        // 'application/vnd.ms-excel',
        // 'application/vnd.ms-powerpoint',
        // 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        // 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        // 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        // 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
        // 'application/vnd.openxmlformats-officedocument.presentationml.template',
        // 'application/vnd.openxmlformats-officedocument.spreadsheet',
    ];
    const FLAG_HTTP_TYPE = 'httpTypeFlag';
    const FLAG_IPGEO = 'ipGeoFlag';
    const FLAG_REDIRECT = 'redirectFlag';
    const FLAG_RANDOM_DOMAIN = 'randomDomainFlag';
    const FLAG_URL_CONTAIN_TO = 'urlContainToFlag';
    const FLAG_DOMAIN_REG = 'domainRegFlag';
    const FLAG_CONTENT_KEYWORD = 'contentKeywordFlag';
    const FLAG_ABUSED_TLD = 'tldFlag';
    const FLAG_HOST_KEYWORD = 'hostKeywordFlag';
    const FLAG_URL_KEYWORD = 'urlKeywordFlag';
    const FLAG_HEADER_KEYWORD = 'headerKeywordFlag';
    const FLAG_URL_SOURCE = 'urlSourceFlag';
    const FLAG_HTTP_LENGTH = 'httpLengthFlag';
    const FLAG_FORM = 'formFlag';
    const FLAG_NULL_HYPERLINK = 'nullHyperlinkFlag';
    const FLAG_SUSPECT_LINK = 'suspectLinkFlag';
    const FLAG_OUT_LINK = 'outLinkFlag';
    const FLAG_OUT_TAG = 'outTagFlag';
    const FLAG_IP_HOST = 'ipHostFlag';
    const FLAG_URL_LENGTH = 'urlLengthFlag';
    const FLAG_SHORT_URL = 'shortUrlFlag';
    const FLAG_DOUBLE_SLASH = 'doubleSlashFlag';
    const FLAG_PREFIX_SUFFIX = 'prefixSuffixFlag';
    const FLAG_HAVE_SUB_DOMAIN = 'haveSubDomainFlag';
    const FLAG_HTTPS = 'httpsFlag';
    const FLAG_FAVICON = 'faviconFlag';
    const FLAG_HTTPS_TOKEN = 'httpsTokenFlag';
    const FLAG_MEDIA_LINK = 'mediaLinkFlag';
    const FLAG_DOMAIN_LENGTH = 'domainLengthFlag';
    const FLAG_PATH_LENGTH = 'pathLengthFlag';
    const FLAG_SUB_DOMAIN_COUNT = 'subDomainCountFlag';
    const FLAG_IS_HTTPS = 'isHttpsFlag';
    const FLAG_SPECIAL_CHARS = 'specialCharsFlag';
    const FLAG_SENSITIVE_WORD = 'sensitiveWordFlag';
    const FLAG_NUMBER_RATIO = 'numberRatioFlag';
    const FLAG_MIX_WORD = 'mixWordFlag';
    const FLAG_IFRAME = 'iframeFlag';
    const FLAG_CONTAIN_IP = 'containIpFlag';
    const COMMON_TERMS = [
        'www' => 2,
        'com' => 2,
    ];
    public $errorFlag;
    public $vectorLogs;
    public $vectorMap = [
        self::FLAG_HTTP_TYPE        => '',
        self::FLAG_IPGEO            => '',
        self::FLAG_RANDOM_DOMAIN    => '',
        self::FLAG_URL_CONTAIN_TO   => '',
        self::FLAG_DOMAIN_REG       => '',
        self::FLAG_CONTENT_KEYWORD  => '',
        self::FLAG_ABUSED_TLD       => '',
        self::FLAG_HTTP_LENGTH      => '',
        self::FLAG_OUT_LINK         => '',
        // self::FLAG_OUT_TAG          => '',
        self::FLAG_IP_HOST          => '',
        self::FLAG_SHORT_URL        => '',
        self::FLAG_DOUBLE_SLASH     => '',
        self::FLAG_PREFIX_SUFFIX    => '',
        self::FLAG_HAVE_SUB_DOMAIN  => '',
        self::FLAG_HTTPS            => '',
        // self::FLAG_FAVICON          => '',
        self::FLAG_HTTPS_TOKEN      => '',
        // self::FLAG_MEDIA_LINK       => '',
        self::FLAG_DOMAIN_LENGTH    => '',
        self::FLAG_SUB_DOMAIN_COUNT => '',
        self::FLAG_IS_HTTPS         => '',
        self::FLAG_SENSITIVE_WORD   => '',
        // self::FLAG_NUMBER_RATIO     => '',
        // self::FLAG_MIX_WORD         => '',
        self::FLAG_IFRAME           => '',
        self::FLAG_CONTAIN_IP       => '',
        // self::FLAG_FORM             => '',
        // self::FLAG_URL_LENGTH       => '',
        // self::FLAG_NULL_HYPERLINK   => '',
        // self::FLAG_SUSPECT_LINK     => '',
        // self::FLAG_PATH_LENGTH      => '',
        // self::FLAG_SPECIAL_CHARS    => '',
        // self::FLAG_HOST_KEYWORD    => '',
        // self::FLAG_REDIRECT        => '',
        // self::FLAG_URL_KEYWORD     => '',
        // self::FLAG_HEADER_KEYWORD  => '',
        // self::FLAG_URL_SOURCE      => '',
    ];

    /**
     * 获取生成向量所需的信息
     * @param $url
     * @param bool $skipCache
     * @param bool $forceUpdate
     * @return array
     * @throws Exception
     */
    public function get2VectorInfo($url, $skipCache = false, $forceUpdate = false)
    {
        $httpCacheModel = new UrlHttpCache();
        $vectorModel    = new self();
        $httpCache      = $httpCacheModel->getByUrl($url);
        if (!empty($httpCache) && $httpCache['url_code'] == 200 && !$skipCache) {
            $httpCache['url_http']  = json_decode($httpCache['url_http'], true);
            $httpCache['url_whois'] = json_decode($httpCache['url_whois'], true);
            $httpCache['url_tls']   = json_decode($httpCache['url_tls'], true);
            return [$httpCache['url_http'], $httpCache['url_whois'], $httpCache['url_tls']];
        }
        $httpInfo = $vectorModel->getHttpContent($url);
        $realUrl = $httpInfo['http']['real_url'] ?? $url;
        list($whoisInfo, $tls) = $vectorModel->getWhoisContent($realUrl);
        if (json_encode($httpInfo, JSON_INVALID_UTF8_IGNORE) === false) {
            unset($httpInfo['body']);
        }
        $id   = $httpCache['id'] ?? 0;
        $data = [
            'url'       => $url,
            'url_hash'  => CommonUtil::getUrlHash($url),
            'url_code'  => $httpInfo['code'] ?? 0,
            'url_http'  => json_encode($httpInfo, JSON_INVALID_UTF8_IGNORE),
            'url_whois' => json_encode($whoisInfo, JSON_INVALID_UTF8_IGNORE),
            'url_tls'   => json_encode($tls, JSON_INVALID_UTF8_IGNORE),
            'url_date'  => date('Y-m-d'),
        ];
        if ($data['url_code'] == 200 || $forceUpdate) {
            $httpCacheModel->updateCache($data, $id);
        }
        return [$httpInfo, $whoisInfo, $tls];
    }

    /**
     * @param $rawUrl
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getHttpContent($rawUrl, $options = [])
    {
        $util     = new MdApi();
        $escCode  = [0, 2, 4, 5];
        $counter  = 0;
        $redirect = [];
        $url      = $rawUrl;
        $realUrl  = $url;
        $requestOps  = [
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_PROXY          => env('queue.proxy', '202.175.80.30:3888'),
        ];
        $headerOptions = [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => true,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_PROXY          => env('queue.proxy', '202.175.80.30:3888'),
        ];
        if (!empty($options['proxy'])) {
            $headerOptions[CURLOPT_PROXY] = $requestOps[CURLOPT_PROXY] = $options['proxy'];
        } else {
            unset($headerOptions[CURLOPT_PROXY]);
            unset($requestOps[CURLOPT_PROXY]);
        }
        while (true) {
            if (stripos($rawUrl, 'https://') === 0) {
                $headerOptions[CURLOPT_SSL_VERIFYPEER] = false;
                $headerOptions[CURLOPT_SSL_VERIFYHOST] = false;
            }
            // $header = $util->httpRequest($url, [], 'HEAD', [], $headerOptions);
            // $header = $header['header'] ?? [];
            $header = CommonUtil::getHttpHeader($url, 0, $headerOptions);
            if (!empty($header['content_type']) && stripos($header['content_type'], 'text') !== false) {
                $requestOps[CURLOPT_NOBODY] = false;
            } else {
                $requestOps[CURLOPT_NOBODY] = true;
            }
            $data = CommonUtil::httpRequest($url, [], 'GET', [], $requestOps);
            // $data = $util->httpRequest($url, [], 'GET', [], $options);
            foreach ($header as $key => $value) {
                if (!isset($data['header'][$key])) {
                    $data['header'][$key] = $value;
                }
            }
            $counter++;
            if ($counter >= 3) {
                break;
            }
            $_code = substr(strval($data['code'] ?? 0), 0, 1);
            $location = $data['header']['location'] ?? '';
            if ($_code == 3 || !empty($location)) {
                $url        = $data['header']['redirect_url'];
                if (empty($url)) {
                    $url = $location;
                }
                $redirect[] = $data;
                $realUrl    = $url;
            }
            if (in_array($_code, $escCode)) {
                break;
            }
        }
        $data['http'] = [
            'redirect'  => $redirect,
            'links'     => [],
            'ip'        => $data['header']['primary_ip'] ?? '',
            'query_url' => $rawUrl,
            'real_url'  => $realUrl,
            'host'      => CommonUtil::getHost($realUrl),
        ];
        $sizeList     = ['size_download', 'download_content_length'];
        if (empty($data['header']['content_length']) || $data['header']['content_length'] < 0) {
            foreach ($sizeList as $key) {
                if (!empty($data['header'][$key]) && $data['header'][$key] > 0) {
                    $data['header']['content_length'] = $data['header'][$key];
                    break;
                }
            }
        }
        if (!empty($data['header']['primary_ip'])) {
            $data['http']['ip_loc'] = CommonUtil::getIpInfo($data['header']['primary_ip']);
        }
        if (!empty($data['body'])) {
            // $data['body'] = ForceUTF8Encode::fixUTF8($data['body']);
        }
        if (!empty($requestOps[CURLOPT_PROXY]) && $data['code'] != 200) {
            return $this->getHttpContent($rawUrl, ['proxy' => false]);
        }
        return $data;
    }

    /**
     * @param $url
     * @return array
     */
    public function getWhoisContent($url)
    {
        $whoisUtil                  = new WhoisUtil();
        $whoisUtil->whois->STIMEOUT = 5;
        $host                       = CommonUtil::getHost($url);
        $domain                     = $whoisUtil->get1stDomain($url);
        $tls                        = CommonUtil::getTLSInfo($domain);
        if (!empty($tls)) {
            if (!is_string($tls['issuer'])) {
                $tls['issuer'] = json_encode($tls['issuer']);
            }
            if (empty($tls['from']) && !empty($tls['validFrom_time_t'])) {
                $tls['from'] = date('Y-m-d H:i:s', $tls['validFrom_time_t']);
            }
            if (empty($tls['to']) && !empty($tls['validTo_time_t'])) {
                $tls['to'] = date('Y-m-d H:i:s', $tls['validTo_time_t']);
            }
            $formatter  = function ($data) {
                return str_replace(["\\", "\r", "\n", "\r\n"], ["\\\\", "\\r", "\\n", "\\r\\n"], $data);
            };
            $encodeList = ['extensions'];
            foreach ($encodeList as $field) {
                if (empty($tls[$field])) {
                    continue;
                }
                if (is_array($tls[$field])) {
                    foreach ($tls[$field] as $key => &$value) {
                        $value = $formatter($value);
                    }
                } else {
                    $tls[$field] = $formatter($tls[$field]);
                }
            }
        }
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $whois = $whoisUtil->ip_whois($host);
        } else {
            $whois = $whoisUtil->get($domain, false);
        }
        if (is_array($whois)) {
            if (!empty($whois["regrinfo"]["disclaimer"])) {
                unset($whois["regrinfo"]["disclaimer"]);
            }
            $rawData = $whois['rawdata'];
            if (empty($rawData)) {
                return [$whois, $tls];
            }
            $newData   = [];
            $formatter = function ($data) {
                return str_replace(["\\", "\r", "\n", "\r\n"], ["\\\\", "", "\\n", "\\r\\n"], $data);
            };
            foreach ($rawData as $item) {
                if (strpos($item, '>>> Last update of') !== false) {
                    break;
                }
                $item      = $formatter($item);
                $newData[] = trim($item);
            }
            $whois['rawdata'] = $newData;
            return [$whois, $tls];
        } else {
            $raw     = explode(PHP_EOL, $whois);
            $whois   = [];
            $newData = [];
            foreach ($raw as $line) {
                $line = trim($line);
                if (empty($line) || $line[0] == '%') {
                    continue;
                }
                if (strpos($line, ':') !== false) {
                    list($key, $value) = explode(':', $line, 2);
                    $key   = trim($key);
                    $value = trim($value);
                    if (!isset($newData[$key])) {
                        $newData[$key] = $line;
                    }
                }
            }
            $whois['rawdata'] = $newData;
        }
        return [$whois, $tls];
    }

    /**
     * @param $url
     * @return array
     */
    public static function getCache($url)
    {
        $url    = CommonUtil::formatUrl($url);
        $record = self::where('url_hash', CommonUtil::getUrlHash($url))->findOrEmpty();
        if ($record->isEmpty()) {
            return ['url_vector' => '', 'log' => ''];
        }
        return $record->toArray();
    }

    /**
     * 获取URL预测模型向量
     * @param string $url
     * @param array $httpInfo
     * @param array $whoisInfo
     * @param array $tlsInfo
     * @param array $cache
     * @param bool $predict 是否预测，预测时不返回false
     * @return mixed
     * @throws Exception
     */
    public function getVectors($url = '', $httpInfo = [], $whoisInfo = [], $tlsInfo = [], $cache = [], $predict = false)
    {
        $row              = [];
        $this->vectorLogs = [];
        foreach ($this->vectorMap as $flag => $value) {
            $this->errorFlag = '';
            if (isset($cache[$flag])) {
                $row[$flag] = $cache[$flag];
                continue;
            }
            $vector = $this->getVector($flag, $url, $httpInfo, $whoisInfo, $tlsInfo, $predict);
            if ($vector === false && !$predict) {
                // 某个向量检测出当前URL样本不符合要求，跳过该样本
                $this->errorFlag = $flag;
                return false;
            }
            $vector     = round($vector, 4);
            $row[$flag] = $vector;
        }
        return $row;
    }

    /**
     * @param string $flag
     * @param $url
     * @param $httpInfo
     * @param $whoisInfo
     * @param $tlsInfo
     * @param $predict bool 是否预测，预测时不返回false
     * @return false|float|int|mixed
     * @throws Exception
     */
    public function getVector(string $flag, $url, $httpInfo, $whoisInfo, $tlsInfo, $predict = false)
    {
        $formatRegex     = function ($reg) {
            if ($reg === '') {
                return '';
            }
            return '/' . $reg . '/';
        };
        $containsChinese = function ($str) {
            $pattern = '/[\x{4e00}-\x{9fa5}]/u';
            preg_match_all($pattern, $str, $matches);
            if (count($matches[0]) >= 20) {
                return true;
            }
            return false;
        };
        $host            = CommonUtil::getHost($url);
        $domain          = new Domain($host);
        $body            = $httpInfo['body'] ?? '';
        $body            = is_bool($body) ? '' : $body;
        switch ($flag) {
            case self::FLAG_HTTP_TYPE:
                $contentType = $httpInfo['content_type'] ?? '';
                if (empty($contentType)) {
                    $this->vectorLogs[$flag] = sprintf('empty content_type, result: %s', UrlNN::URL_PREDICT_OK);
                    break;
                }
                foreach (self::CONTENT_TYPE_HIGH as $item) {
                    if (stripos($contentType, $item) !== false) {
                        $this->vectorLogs[$flag] = sprintf('match: %s, result: %s', $item, self::CONTENT_TYPE_HIGH_POINT);
                        return self::CONTENT_TYPE_HIGH_POINT;
                    }
                }
                foreach (self::CONTENT_TYPE_LOW as $item) {
                    if (stripos($contentType, $item) !== false) {
                        $this->vectorLogs[$flag] = sprintf('match: %s, result: %s', $item, self::CONTENT_TYPE_LOW_POINT);
                        return self::CONTENT_TYPE_LOW_POINT;
                    }
                }
                break;
            case self::FLAG_IPGEO:
                // 中国地区IP + 页面包含中文 为正常
                // 非中国地区IP + 页面包含中文 为异常
                $ipInfo = $httpInfo['http']['ip_loc'] ?? '';
                if (empty($ipInfo)) {
                    $this->vectorLogs[$flag] = sprintf('ip_loc: empty, result: %s', UrlNN::URL_PREDICT_OK);
                    return UrlNN::URL_PREDICT_OK;
                }
                list($country, $region, $province, $city, $isp) = explode('|', $ipInfo);
                if ($country == '中国') {
                    if ($containsChinese($body)) {
                        $this->vectorLogs[$flag] = sprintf('country: %s, body contains chinese, result: %s', $country, UrlNN::URL_PREDICT_OK);
                        return UrlNN::URL_PREDICT_OK;
                    }
                } elseif ($containsChinese($body)) {
                    $this->vectorLogs[$flag] = sprintf('country not china, body contains chinese, result: %s', UrlNN::URL_PREDICT_MAL);
                    return UrlNN::URL_PREDICT_MAL;
                } else {
                    $this->vectorLogs[$flag] = sprintf('country not china, body not contains chinese, result: %s', UrlNN::URL_PREDICT_OK);
                }
                break;
            case self::FLAG_REDIRECT:
                $redirectUrl    = $httpInfo['header']['url'] ?? '';
                $redirectHost   = CommonUtil::getHost($redirectUrl);
                $redirectDomain = new Domain($redirectHost);
                if ($domain->getRegisterable() != $redirectDomain->getRegisterable()) {
                    $this->vectorLogs[$flag] = sprintf('redirect from %s to %s, result: %s', $domain->getRegisterable(), $redirectDomain->getRegisterable(), UrlNN::URL_PREDICT_MAL);
                    return UrlNN::URL_PREDICT_MAL;
                } else {
                    $this->vectorLogs[$flag] = sprintf('redirect from %s to %s, result: %s', $domain->getRegisterable(), $redirectDomain->getRegisterable(), UrlNN::URL_PREDICT_OK);
                }
                break;
            case self::FLAG_RANDOM_DOMAIN:
                return intval(CommonUtil::isRandomWord($domain->getName()));
            case self::FLAG_URL_CONTAIN_TO:
                $pattern = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}/';
                $url     = CommonUtil::formatUrl($url);
                if (preg_match($pattern, $url) || strpos($url, '@') !== false) {
                    $this->vectorLogs[$flag] = sprintf('url contain @: %s, result: %s', $url, UrlNN::URL_PREDICT_MAL);
                    return UrlNN::URL_PREDICT_MAL;
                } else {
                    $this->vectorLogs[$flag] = sprintf('url not contain @: %s, result: %s', $url, UrlNN::URL_PREDICT_OK);
                }
                break;
            case self::FLAG_DOMAIN_REG:
                if (empty($whoisInfo['regrinfo']['domain']['created'])) {
                    $this->vectorLogs[$flag] = sprintf('domain created time: empty, result: %s', UrlNN::URL_PREDICT_OK);
                    break;
                }
                $time = $whoisInfo['regrinfo']['domain']['created'];
                if (strtotime($time) === false) {
                    $this->vectorLogs[$flag] = sprintf('domain created time: %s, result: %s', $time, UrlNN::URL_PREDICT_OK);
                    break;
                }
                if (strtotime($time) >= strtotime('-1 year')) {
                    $this->vectorLogs[$flag] = sprintf('domain created time: %s, result: %s', $time, UrlNN::URL_PREDICT_MAL);
                    return UrlNN::URL_PREDICT_MAL;
                }
                break;
            case self::FLAG_CONTENT_KEYWORD:
                // if (mb_strlen($httpInfo['body'], 'UTF-8') <= 100) {
                //     $this->vectorLogs[$flag] = sprintf('content length: %s, result: %s', mb_strlen($httpInfo['body']), UrlNN::URL_PREDICT_OK);
                //     return UrlNN::URL_PREDICT_OK;
                // }
                foreach (self::LIST_CONTENT_KEYWORDS as $item) {
                    if (stripos($body, $item) !== false) {
                        $this->vectorLogs[$flag] = sprintf('match: %s, result: %s', $item, UrlNN::URL_PREDICT_MAL);
                        return UrlNN::URL_PREDICT_MAL;
                    }
                }
                $pattern = $formatRegex(self::PATTERN_CONTENT_KEYWORD);
                if (empty($httpInfo['body']) || empty($pattern)) {
                    $this->vectorLogs[$flag] = sprintf('body: empty, result: %s', UrlNN::URL_PREDICT_OK);
                    break;
                }
                foreach (self::SKIP_SITE_HOST as $item) {
                    if (stripos($httpInfo['body'], $item) !== false && !$predict) {
                        $this->vectorLogs[$flag] = sprintf('body contains skip site: %s, result: %s', $item, UrlNN::URL_PREDICT_OK);
                        return false;
                    }
                }
                if (preg_match($pattern, $httpInfo['body'])) {
                    preg_match_all($pattern, $httpInfo['body'], $matches);
                    $this->vectorLogs[$flag] = sprintf('match: %s, result: %s', json_encode($matches[0]), UrlNN::URL_PREDICT_MAL);
                    unset($matches);
                    return UrlNN::URL_PREDICT_MAL;
                } else {
                    $this->vectorLogs[$flag] = sprintf('body not match, result: %s', UrlNN::URL_PREDICT_OK);
                }
                break;
            case self::FLAG_ABUSED_TLD:
                $tld = $domain->getSuffix();
                if (empty($tld)) {
                    break;
                }
                if (in_array($tld, self::ABUSED_TLD_LIST)) {
                    $this->vectorLogs[$flag] = sprintf('abused tld: %s, result: %s', $tld, UrlNN::URL_PREDICT_MAL);
                    return UrlNN::URL_PREDICT_MAL;
                }
                $sub = $domain->getSub();
                if (stripos($sub, $tld) !== false) {
                    $this->vectorLogs[$flag] = sprintf('sub has tld: %s, result: %s', $tld, UrlNN::URL_PREDICT_MAL);
                    return UrlNN::URL_PREDICT_MAL;
                }
                $path = parse_url($url, PHP_URL_PATH);
                if (!empty($path) && stripos($path, $tld) !== false) {
                    $this->vectorLogs[$flag] = sprintf('path has tld: %s, result: %s', $tld, UrlNN::URL_PREDICT_MAL);
                    return UrlNN::URL_PREDICT_MAL;
                }
                break;
            case self::FLAG_HOST_KEYWORD:
                $pattern = $formatRegex(self::PATTERN_HOST_KEYWORD);
                if (empty($pattern) || empty($host)) {
                    $this->vectorLogs[$flag] = sprintf('host or pattern: empty, result: %s', UrlNN::URL_PREDICT_OK);
                    break;
                }
                if (preg_match($pattern, $host)) {
                    preg_match_all($pattern, $host, $matches);
                    $this->vectorLogs[$flag] = sprintf('match: %s, result: %s', json_encode($matches[0]), UrlNN::URL_PREDICT_MAL);
                    unset($matches);
                    return 0.5;
                }
                break;
            case self::FLAG_URL_KEYWORD:
                $pattern = $formatRegex(self::PATTERN_URL_KEYWORD);
                if (empty($pattern)) {
                    $this->vectorLogs[$flag] = sprintf('pattern: empty, result: %s', UrlNN::URL_PREDICT_OK);
                    break;
                }
                if (preg_match($pattern, $url)) {
                    preg_match_all($pattern, $url, $matches);
                    $this->vectorLogs[$flag] = sprintf('match: %s, result: %s', json_encode($matches[0]), UrlNN::URL_PREDICT_MAL);
                    unset($matches);
                    return UrlNN::URL_PREDICT_MAL;
                }
                break;
            case self::FLAG_HEADER_KEYWORD:
                if (empty($httpInfo['header'])) {
                    $this->vectorLogs[$flag] = sprintf('header: empty, result: %s', UrlNN::URL_PREDICT_OK);
                    break;
                }
                $header  = json_encode($httpInfo['header']);
                $pattern = $formatRegex(self::PATTERN_HEADER_KEYWORD);
                if (empty($pattern) || empty($header)) {
                    $this->vectorLogs[$flag] = sprintf('header or pattern: empty, result: %s', UrlNN::URL_PREDICT_OK);
                    break;
                }
                if (preg_match($pattern, $header)) {
                    preg_match_all($pattern, $header, $matches);
                    $this->vectorLogs[$flag] = sprintf('match: %s, result: %s', json_encode($matches[0]), UrlNN::URL_PREDICT_MAL);
                    unset($matches);
                    return UrlNN::URL_PREDICT_MAL;
                }
                break;
            case self::FLAG_URL_SOURCE:
                break;
            case self::FLAG_HTTP_LENGTH:
                $length = $httpInfo['header']['content_length'] ?? 0;
                if ($length > 100) {
                    $this->vectorLogs[$flag] = sprintf('length < 100: %s, result: %s', $length, UrlNN::URL_PREDICT_MAL);
                    return UrlNN::URL_PREDICT_MAL;
                }
                break;
            case self::FLAG_FORM:
                $body = $httpInfo['body'] ?? '';
                if (empty($body)) {
                    $this->vectorLogs[$flag] = sprintf('body is empty, result: %s', UrlNN::URL_PREDICT_OK);
                    return UrlNN::URL_PREDICT_OK;
                }
                // 查找表单
                $dom = new DOMDocument();
                @$dom->loadHTML($body);
                $xpath = new DOMXPath($dom);
                $nodes = $xpath->query('//form');
                // 获取表单action
                foreach ($nodes as $node) {
                    $action = $node->getAttribute('action');
                    if (empty($action) || stripos($action, 'about:blank') !== false) {
                        $this->vectorLogs[$flag] = sprintf('form action is empty, result: %s', UrlNN::URL_PREDICT_MAL);
                        return UrlNN::URL_PREDICT_MAL;
                    }
                    if (stripos($action, '#') === false && stripos($action, '/') === false) {
                        $this->vectorLogs[$flag] = sprintf('form action is not full url, result: %s', UrlNN::URL_PREDICT_MAL);
                        return UrlNN::URL_PREDICT_MAL;
                    }
                    $pattern = $formatRegex(self::PATTERN_FORM);
                    if (preg_match($pattern, $action)) {
                        $actionHost   = CommonUtil::getHost($action);
                        $actionDomain = new Domain($actionHost);
                        if ($actionDomain->getRegisterable() != $domain->getRegisterable()) {
                            $this->vectorLogs[$flag] = sprintf('form action is not same domain, action: %s, domain: %s, result: %s', $actionDomain->getRegisterable(), $domain->getRegisterable(), 0.5);
                            return 0.5;
                        }
                    }
                }
                break;
            case self::FLAG_NULL_HYPERLINK:
                if (empty($body)) {
                    $this->vectorLogs[$flag] = sprintf('body is empty, result: %s', UrlNN::URL_PREDICT_OK);
                    return UrlNN::URL_PREDICT_OK;
                }
                $dom = new DOMDocument();
                @$dom->loadHTML($body);
                $xpath = new DOMXPath($dom);
                // 获取所有a标签
                $nodes   = $xpath->query('//a');
                $total   = $nodes->length;
                $counter = 0;
                foreach ($nodes as $node) {
                    $href = $node->getAttribute('href');
                    if (empty($href) || stripos($href, '#') === 0 || stripos($href, 'javascript:') === 0) {
                        $counter++;
                    }
                }
                $res                     = empty($total) ? 0 : ($counter / $total);
                $this->vectorLogs[$flag] = sprintf('null hyperlink: %s, total: %s, result: %s', $counter, $total, $res);
                return $res;
            case self::FLAG_SUSPECT_LINK:
                if (empty($body)) {
                    $this->vectorLogs[$flag] = sprintf('body is empty, result: %s', UrlNN::URL_PREDICT_OK);
                    return UrlNN::URL_PREDICT_OK;
                }
                $dom = new DOMDocument();
                @$dom->loadHTML($body);
                $xpath = new DOMXPath($dom);
                // 获取所有a标签
                $nodes   = $xpath->query('//a');
                $total   = $nodes->length;
                $counter = 0;
                $pattern = $formatRegex(self::PATTERN_SUSPECT_LINK);
                foreach ($nodes as $node) {
                    $href = $node->getAttribute('href');
                    if (preg_match($pattern, $href)) {
                        $counter++;
                    }
                }
                $res                     = empty($total) ? 0 : ($counter / $total);
                $this->vectorLogs[$flag] = sprintf('suspect link: %s, total: %s, result: %s', $counter, $total, $res);
                return $res;
            case self::FLAG_OUT_LINK:
                $body = $httpInfo['body'] ?? '';
                if (empty($body)) {
                    $this->vectorLogs[$flag] = sprintf('body is empty, result: %s', UrlNN::URL_PREDICT_OK);
                    return UrlNN::URL_PREDICT_OK;
                }
                $dom = new DOMDocument();
                @$dom->loadHTML($body);
                $xpath = new DOMXPath($dom);
                // 获取所有a标签
                $nodes   = $xpath->query('//a');
                $total   = $nodes->length;
                $counter = 0;
                $pattern = $formatRegex(self::PATTERN_OUT_LINK);
                foreach ($nodes as $node) {
                    $href = $node->getAttribute('href');
                    if (preg_match($pattern, $href)) {
                        $linkHost   = CommonUtil::getHost($href);
                        $linkDomain = new Domain($linkHost);
                        if ($domain->getRegisterable() != $linkDomain->getRegisterable()) {
                            $counter++;
                        }
                    }
                }
                $percent = empty($total) ? 0 : ($counter / $total);
                if (empty($percent) || $percent < 0.31) {
                    $this->vectorLogs[$flag] = sprintf('out link: %s, total: %s, percent: %s, result: %s', $counter, $total, $percent, UrlNN::URL_PREDICT_OK);
                    return UrlNN::URL_PREDICT_OK;
                }
                if ($percent <= 0.67) {
                    $this->vectorLogs[$flag] = sprintf('out link: %s, total: %s, percent: %s, result: %s', $counter, $total, $percent, 0.5);
                    return 0.5;
                }
                $this->vectorLogs[$flag] = sprintf('out link: %s, total: %s, percent: %s, result: %s', $counter, $total, $percent, UrlNN::URL_PREDICT_MAL);
                return UrlNN::URL_PREDICT_MAL;
            case self::FLAG_OUT_TAG:
                $body = $httpInfo['body'] ?? '';
                if (empty($body)) {
                    $this->vectorLogs[$flag] = sprintf('body is empty, result: %s', UrlNN::URL_PREDICT_OK);
                    return UrlNN::URL_PREDICT_OK;
                }
                $dom = new DOMDocument();
                @$dom->loadHTML($body);
                $xpath = new DOMXPath($dom);
                // 获取所有可带有外域或跳转的标签
                $linkTags   = $xpath->query('//link');
                $metaTags   = $xpath->query('//meta');
                $scriptTags = $xpath->query('//script');
                $nodes      = [
                    'href'       => $linkTags,
                    'src'        => $scriptTags,
                    'http-equiv' => $metaTags,
                ];
                $total      = 0;
                $counter    = 0;
                $pattern    = $formatRegex(self::PATTERN_OUT_LINK);
                foreach ($nodes as $attr => $node) {
                    foreach ($node as $item) {
                        $href = $item->getAttribute($attr);
                        if ($attr == 'http-equiv' && $href == 'refresh') {
                            $content = $item->getAttribute('content');
                            if (stripos($content, 'url=') !== false) {
                                $href = substr($content, stripos($content, 'url=') + 4);
                            }
                        }
                        if (empty($href) || !preg_match($pattern, $href)) {
                            continue;
                        }
                        if (stripos($href, '.css') === false) {
                            continue;
                        }
                        $total++;
                        if (preg_match($pattern, $href)) {
                            foreach (self::SKIP_DOMAIN_KEYWORDS as $skip) {
                                if (stripos($href, $skip) !== false) {
                                    continue 2;
                                }
                            }
                            foreach (self::SKIP_OUT_LINK_KEYWORDS as $skip) {
                                if (stripos($href, $skip) !== false) {
                                    continue 2;
                                }
                            }
                            $words = preg_split(self::PATTERN_SPLIT_URL_2_WORDS, $href);
                            foreach ($words as $word) {
                                if (isset(self::SKIP_SRC_KEYWORDS[$word])) {
                                    $this->vectorLogs[$flag] = sprintf('skip src keyword:%s - %s, result: %s', $word, $href, UrlNN::URL_PREDICT_OK);
                                    if (!$predict) {
                                        return false;
                                    }
                                    break 2;
                                }
                            }
                            $tagHost   = CommonUtil::getHost($href);
                            $tagDomain = new Domain($tagHost);
                            if ($tagDomain->getRegisterable() != $domain->getRegisterable()) {
                                $counter++;
                            }
                        }
                    }
                }
                $res                     = empty($total) ? 0 : ($counter / $total);
                $this->vectorLogs[$flag] = sprintf('out tag: %s, total: %s, result: %s', $counter, $total, $res);
                return $res;
            case self::FLAG_IP_HOST:
                if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    $this->vectorLogs[$flag] = sprintf('ip is host: %s, result: %s', $host, UrlNN::URL_PREDICT_MAL);
                    return UrlNN::URL_PREDICT_MAL;
                }
                break;
            case self::FLAG_URL_LENGTH:
                $length = mb_strlen($url);
                if ($length > 0 && $length <= 54) {
                    $this->vectorLogs[$flag] = sprintf('url length: %s, result: %s', $length, UrlNN::URL_PREDICT_OK);
                    return UrlNN::URL_PREDICT_OK;
                }
                if ($length > 54 && $length <= 75) {
                    $this->vectorLogs[$flag] = sprintf('url length: %s, result: %s', $length, 0.5);
                    return 0.5;
                }
                $this->vectorLogs[$flag] = sprintf('url length: %s, result: %s', $length, UrlNN::URL_PREDICT_MAL);
                return UrlNN::URL_PREDICT_MAL;
            case self::FLAG_SHORT_URL:
                $domainDict = explode('|', self::PATTERN_SHORT_URL);
                $domainDict = array_flip($domainDict);
                $url        = $httpInfo['http']['query_url'] ?? '';
                if (empty($url) || empty($host)) {
                    break;
                }
                $domain = new Domain($host);
                if (isset($domainDict[$domain->getRegisterable()])) {
                    $this->vectorLogs[$flag] = sprintf('short url: %s, result: %s', $host, UrlNN::URL_PREDICT_MAL);
                    return UrlNN::URL_PREDICT_MAL;
                }
                break;
            case self::FLAG_DOUBLE_SLASH:
                $_url = strtolower($url);
                if (strrpos($_url, 'http://') > 7 || strrpos($_url, 'https://') > 8) {
                    $this->vectorLogs[$flag] = sprintf('double slash: %s, result: %s', $_url, UrlNN::URL_PREDICT_MAL);
                    return UrlNN::URL_PREDICT_MAL;
                }
                $list2Count = function ($list) {
                    $res = [];
                    foreach ($list as $item) {
                        $res[$item] = isset($res[$item]) ? $res[$item] + 1 : 1;
                    }
                    return $res;
                };
                preg_match_all(self::PATTERN_SPLIT_URL_2_WORDS, $_url, $words);
                $words = $list2Count($words[0]);
                foreach (self::COMMON_TERMS as $key => $item) {
                    if (isset($words[$key]) && $words[$key] >= $item) {
                        $this->vectorLogs[$flag] = sprintf('common terms: %s, result: %s', $_url, UrlNN::URL_PREDICT_MAL);
                        return UrlNN::URL_PREDICT_MAL;
                    }
                }
                break;
            case self::FLAG_PREFIX_SUFFIX:
                if (strpos($host, '-') !== false) {
                    $this->vectorLogs[$flag] = sprintf('prefix suffix: %s, result: %s', $host, UrlNN::URL_PREDICT_MAL);
                    return UrlNN::URL_PREDICT_MAL;
                }
                break;
            case self::FLAG_HAVE_SUB_DOMAIN:
                // check for ipv4
                if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $this->vectorLogs[$flag] = sprintf('host is ipv4: %s, result: %s', $host, UrlNN::URL_PREDICT_OK);
                    return UrlNN::URL_PREDICT_OK;
                }
                $part = explode('.', $host);
                foreach ($part as $key => $item) {
                    if ($item == 'www') {
                        unset($part[$key]);
                    }
                    if (in_array($item, self::CC_TLD_LIST) || in_array($item, self::SLD_LIST)) {
                        unset($part[$key]);
                    }
                }
                $count = count($part) - 1;
                if ($count <= 1) {
                    $this->vectorLogs[$flag] = sprintf('have sub domain: %s, result: %s', $host, UrlNN::URL_PREDICT_OK);
                    return UrlNN::URL_PREDICT_OK;
                } else {
                    // 2025.04.28
                    $this->vectorLogs[$flag] = sprintf('have sub domain: %s, result: %s', $host, 0.5);
                    return 0.5;
                }
            // if ($count <= 2) {
            //     $this->vectorLogs[$flag] = sprintf('have sub domain: %s, result: %s', $host, 0.5);
            //     return 0.5;
            // }
            // $this->vectorLogs[$flag] = sprintf('have sub domain: %s, result: %s', $host, UrlNN::URL_PREDICT_MAL);
            // return UrlNN::URL_PREDICT_MAL;
            case self::FLAG_HTTPS:
                $issuer = $tlsInfo['issuer'] ?? '';
                if (empty($tlsInfo) || empty($issuer)) {
                    // $_res = UrlNN::URL_PREDICT_MAL;
                    $_res                    = 0.5; // 2025.04.16
                    $this->vectorLogs[$flag] = sprintf('empty issuer, result: %s', $_res);
                    return $_res;
                }
                // $to      = $tlsInfo['to'] ?? CommonUtil::getDate();
                // $to      = strtotime($to);
                // $now     = CommonUtil::getDate();
                // $now     = strtotime($now);
                // $oneYear = 31536000;
                // $pattern = $formatRegex(self::PATTERN_HTTPS);
                // if (preg_match($pattern, $issuer) && $now - $to >= $oneYear) {
                //     $this->vectorLogs[$flag] = sprintf('https: %s, more than one year, result: %s', $issuer, UrlNN::URL_PREDICT_OK);
                //     return UrlNN::URL_PREDICT_OK;
                // }
                $schema = parse_url($url, PHP_URL_SCHEME);
                if (strtolower($schema) == 'https') {
                    $this->vectorLogs[$flag] = sprintf('schema: %s, result: %s', $schema, 0.5);
                    return 0.5;
                }
                break;
            case self::FLAG_FAVICON:
                $body = $httpInfo['body'] ?? '';
                if (empty($body)) {
                    $this->vectorLogs[$flag] = sprintf('empty body, result: %s', UrlNN::URL_PREDICT_MAL);
                    return UrlNN::URL_PREDICT_OK;
                }
                $dom = new DOMDocument();
                @$dom->loadHTML($body);
                $xpath   = new DOMXPath($dom);
                $nodes   = $xpath->query('//link');
                $href    = '';
                $pattern = $formatRegex(self::PATTERN_OUT_LINK);
                foreach ($nodes as $node) {
                    $rel = $node->getAttribute('rel');
                    if (empty($rel) || $rel != 'icon') {
                        continue;
                    }
                    $href = $node->getAttribute('href');
                    if (!empty($href) && preg_match($pattern, $href)) {
                        break;
                    }
                }
                if (empty($href) || !preg_match($pattern, $href)) {
                    $this->vectorLogs[$flag] = sprintf('empty href || href not match:%s, result: %s', $href, UrlNN::URL_PREDICT_OK);
                    break;
                }
                $words = preg_split(self::PATTERN_SPLIT_URL_2_WORDS, $href);
                foreach ($words as $word) {
                    if (isset(self::SKIP_SRC_KEYWORDS[$word])) {
                        $this->vectorLogs[$flag] = sprintf('skip src keyword:%s - %s, result: %s', $word, $href, UrlNN::URL_PREDICT_OK);
                        if (!$predict) {
                            return false;
                        }
                        break 2;
                    }
                }
                $linkHost   = CommonUtil::getHost($href);
                $linkDomain = new Domain($linkHost);
                if ($domain->getRegisterable() != $linkDomain->getRegisterable()) {
                    $this->vectorLogs[$flag] = sprintf('link host: %s, domain host: %s, result: %s', $linkHost, $domain->getRegisterable(), UrlNN::URL_PREDICT_MAL);
                    return UrlNN::URL_PREDICT_MAL;
                }
                break;
            case self::FLAG_HTTPS_TOKEN:
                $domain = $domain->getRegisterable();
                if (stripos($domain, 'https') !== false || stripos($domain, 'http') !== false) {
                    $this->vectorLogs[$flag] = sprintf('https token: %s, result: %s', $domain, UrlNN::URL_PREDICT_MAL);
                    return UrlNN::URL_PREDICT_MAL;
                } else {
                    $this->vectorLogs[$flag] = sprintf('https token: %s, result: %s', $domain, UrlNN::URL_PREDICT_OK);
                }
                break;
            case self::FLAG_MEDIA_LINK:
                $body = $httpInfo['body'] ?? '';
                if (empty($body)) {
                    $this->vectorLogs[$flag] = sprintf('empty body, result: %s', UrlNN::URL_PREDICT_OK);
                    return UrlNN::URL_PREDICT_OK;
                }
                $dom = new DOMDocument();
                @$dom->loadHTML($body);
                $xpath     = new DOMXPath($dom);
                $nodeImg   = $xpath->query('//img');
                $nodeVideo = $xpath->query('//video');
                $total     = $counter = 0;
                $pattern   = $formatRegex(self::PATTERN_OUT_LINK);
                $nodes     = [];
                foreach ($nodeImg as $item) {
                    $nodes[] = $item;
                }
                foreach ($nodeVideo as $item) {
                    $nodes[] = $item;
                }
                foreach ($nodes as $node) {
                    $src = $node->getAttribute('src');
                    if (empty($src) || !preg_match($pattern, $src)) {
                        continue;
                    }
                    foreach (self::SKIP_DOMAIN_KEYWORDS as $skip) {
                        if (stripos($src, $skip) !== false) {
                            $this->vectorLogs[$flag] = sprintf('skip domain keyword:%s - %s, result: %s', $skip, $src, UrlNN::URL_PREDICT_OK);
                            if (!$predict) {
                                return false;
                            }
                            continue 2;
                        }
                    }
                    $words = preg_split(self::PATTERN_SPLIT_URL_2_WORDS, $src);
                    foreach ($words as $word) {
                        if (isset(self::SKIP_SRC_KEYWORDS[$word])) {
                            $this->vectorLogs[$flag] = sprintf('skip src keyword:%s - %s, result: %s', $word, $src, UrlNN::URL_PREDICT_OK);
                            if (!$predict) {
                                return false;
                            }
                            continue 2;
                        }
                        if (stripos($word, 'cdn') !== false) {
                            $this->vectorLogs[$flag] = sprintf('skip cdn keyword:%s - %s, result: %s', $word, $src, UrlNN::URL_PREDICT_OK);
                            if (!$predict) {
                                return false;
                            }
                            continue 2;
                        }
                    }
                    $total++;
                    $mediaHost   = CommonUtil::getHost($src);
                    $mediaDomain = new Domain($mediaHost);
                    if ($mediaDomain->getRegisterable() != $domain->getRegisterable()) {
                        $counter++;
                    }
                }
                $res                     = $total > 0 ? ($counter / $total) : 0;
                $this->vectorLogs[$flag] = sprintf('total: %s, counter: %s, result: %s', $total, $counter, $res);
                return $res;
            case self::FLAG_DOMAIN_LENGTH:
                $domain = $domain->getRegisterable();
                if (strlen($domain) > 20) {
                    $_res                    = UrlNN::URL_PREDICT_MAL;
                    $_res                    = 0.5; // 2025.04.28
                    $this->vectorLogs[$flag] = sprintf('domain length: %s, result: %s', $domain, $_res);
                    return $_res;
                }
                break;
            case self::FLAG_PATH_LENGTH:
                $path = parse_url($url, PHP_URL_PATH);
                if (empty($path)) {
                    $this->vectorLogs[$flag] = sprintf('empty path, result: %s', UrlNN::URL_PREDICT_OK);
                    return UrlNN::URL_PREDICT_OK;
                }
                $path = str_replace('/', '', $path);
                if (strlen($path) > 30) {
                    $this->vectorLogs[$flag] = sprintf('path length: %s, longer than 30, result: %s', $path, UrlNN::URL_PREDICT_MAL);
                    return UrlNN::URL_PREDICT_MAL;
                }
                break;
            case self::FLAG_SUB_DOMAIN_COUNT:
                $domain = $domain->getRegisterable();
                $count  = substr_count($domain, '.');
                if ($count >= 2) {
                    $this->vectorLogs[$flag] = sprintf('sub domain count: %s, result: %s', $count, UrlNN::URL_PREDICT_MAL);
                    return UrlNN::URL_PREDICT_MAL;
                }
                break;
            case self::FLAG_IS_HTTPS:
                if (!empty($httpInfo['http']['real_url'])) {
                    $url = $httpInfo['http']['real_url'];
                }
                $schema = parse_url($url, PHP_URL_SCHEME);
                if (strtolower($schema) != 'https') {
                    $_res                    = UrlNN::URL_PREDICT_MAL;
                    $_res                    = 0.5;// 2025.04.14
                    $this->vectorLogs[$flag] = sprintf('not https: %s, result: %s', $schema, $_res);
                    return $_res;
                }
                break;
            case self::FLAG_SPECIAL_CHARS:
                $list = str_split(self::SPECIAL_CHARACTER);
                foreach ($list as $char) {
                    if (stripos($url, $char) !== false) {
                        $this->vectorLogs[$flag] = sprintf('special char: %s, result: %s', $char, UrlNN::URL_PREDICT_MAL);
                        return UrlNN::URL_PREDICT_MAL;
                    }
                }
                break;
            case self::FLAG_SENSITIVE_WORD:
                foreach (self::SENSITIVE_KEYWORD as $keyword) {
                    $regx = $formatRegex('\b' . $keyword . '\b');
                    if (preg_match($regx, $url)) {
                        $this->vectorLogs[$flag] = sprintf('sensitive word: %s, result: %s', $keyword, UrlNN::URL_PREDICT_MAL);
                        return UrlNN::URL_PREDICT_MAL;
                    }
                }
                break;
            case self::FLAG_NUMBER_RATIO:
                return $this->getNumberRatio($url);
            case self::FLAG_MIX_WORD:
                $parse = parse_url($url);
                $list  = [];
                if (!empty($parse['path'])) {
                    $arr = explode('/', $parse['path']);
                    if (!empty($arr)) {
                        $list = array_merge($list, $arr);
                    }
                }
                $max = 0;
                foreach ($list as $item) {
                    $num = $this->getNumberRatio($item);
                    $max = max($max, $num);
                }
                $this->vectorLogs[$flag] = sprintf('result: %s', $max);
                return $max;
            case self::FLAG_IFRAME:
                $dom = new DOMDocument();
                @$dom->loadHTML($body);
                $xpath = new DOMXPath($dom);
                $nodes = $xpath->query('//iframe');
                if (!empty(count($nodes))) {
                    foreach ($nodes as $node) {
                        $src = $node->getAttribute('src');
                        foreach (self::SKIP_DOMAIN_KEYWORDS as $skip) {
                            if (stripos($src, $skip) !== false) {
                                $this->vectorLogs[$flag] = sprintf('skip iframe keyword:%s - %s, result: %s', $skip, $src, UrlNN::URL_PREDICT_OK);
                                if (!$predict) {
                                    return false;
                                }
                                continue 2;
                            }
                        }
                        $pattern = $formatRegex(self::PATTERN_OUT_LINK);
                        if (!preg_match($pattern, $src)) {
                            continue;
                        }
                        $frameHost   = CommonUtil::getHost($src);
                        $frameDomain = new Domain($frameHost);
                        if ($frameDomain->getRegisterable() == $domain->getRegisterable()) {
                            continue;
                        }
                        $_res                    = UrlNN::URL_PREDICT_MAL;
                        $_res                    = 0.5; // 2025.04.16
                        $this->vectorLogs[$flag] = sprintf('iframe: %s, result: %s', $src, $_res);
                        return $_res;
                    }
                }
                break;
            case self::FLAG_CONTAIN_IP:
                $pattern = $formatRegex(self::PATTERN_IP);
                $parse   = parse_url($url);
                unset($parse['scheme']);
                unset($parse['host']);
                $subject = http_build_query($parse);
                if (preg_match($pattern, $subject)) {
                    $this->vectorLogs[$flag] = sprintf('contain ip: %s, result: %s', $subject, UrlNN::URL_PREDICT_MAL);
                    return UrlNN::URL_PREDICT_MAL;
                }
                break;
        }
        if (!isset($this->vectorLogs[$flag])) {
            $this->vectorLogs[$flag] = sprintf('no malicious data found, result: %s', UrlNN::URL_PREDICT_OK);
        }
        return UrlNN::URL_PREDICT_OK;
    }

    /**
     * 获取字符串数字比例
     * @param $url
     * @return float|int
     */
    private function getNumberRatio($url)
    {
        preg_match_all('/[0-9]/', $url, $matchesDigits);
        if (is_null($matchesDigits[0])) {
            return 0;
        }
        $numDigits = count($matchesDigits[0]);
        if (empty($numDigits) || $numDigits == strlen($url)) {
            return 0;
        }
        return $numDigits / strlen($url);
    }

    /**
     * @param $data
     * @param $id
     * @return UrlVector|int|string
     */
    public function addUrlVector($data = [], $id = 0)
    {
        if (!empty($id)) {
            return $this->update($data, ['id' => $id]);
        }
        return $this->insert($data);
    }
}
