<?php
declare (strict_types=1);

namespace app\command;

use app\common\Code;
use app\library\Domain;
use app\model\CommonUtil;
use app\model\Desc;
use app\model\Jobs;
use app\model\Url;
use app\model\UrlHttpCache;
use app\model\UrlVector;
use app\util\ConsoleTable;
use app\util\UrlNN;
use DOMDocument;
use DOMXPath;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

class Fix extends Command
{
    const PATTERN_CONTENT_KEYWORD = 'Suspected phishing site|Suspected Phishing|potential phishing|301 Moved Permanently';
    const PATTERN_HTTPS = 'DigiCert|Let\'s Encrypt|Comodo|Symantec|GlobalSign|GoDaddy|Entrust|Verisign';
    const PATTERN_OUT_LINK = 'http|https.*';
    const SKIP_DOMAIN_KEYWORDS = [
        'google.com',
        'googleapis.com',
        'cloudflare.com',
        'googletagmanager',
        's3.amazonaws',
        'cdn.',
        '.cdn',
        'gravatar.com',
        '/avatar/',
    ];
    const SKIP_SRC_KEYWORDS = [
        'assets'  => 0,
        'asset'   => 0,
        'static'  => 0,
        'statics' => 0,
    ];
    const PATTERN_SPLIT_URL_2_WORDS = '/[=?\/\.-]/';
    const BATCH_COUNT = 3;

    public function formatRegex($reg)
    {
        if ($reg === '') {
            return '';
        }
        return '/' . $reg . '/';
    }

    protected function configure()
    {
        // 指令配置
        $this->setName('fix')
            ->addArgument('c', Argument::OPTIONAL, 'Command')
            ->addOption('args', 'a', Option::VALUE_OPTIONAL, 'Args', false)
            ->setDescription('the fix command');
    }

    protected function execute(Input $input, Output $output)
    {
        $command    = trim($input->getArgument('c') ?? '');
        $commandMap = [
            'b'  => 'batch',
            't'  => 'tmp',
            's'  => 'statistic',
            'r'  => 'rerun',
            'p'  => 'predict',
            'g'  => 'getData',
            'i'  => 'insert',
            'k'  => 'kill',
            'vs' => 'vectorStatistic',
        ];
        $command    = $commandMap[$command] ?? 'statistic';

        if (method_exists($this, $command)) {
            return $this->$command($input, $output);
        }
        // return $this->predict($output);
        // return $this->statistic($output);
        // return $this->getData($output);
        // return $this->rerun($output);
        return true;
    }

    public function insert($input, $output)
    {
        $urlList = [];
        foreach ($urlList as $url) {
            $url  = CommonUtil::formatUrl($url);
            $save = [
                'url'       => $url,
                'type'      => Url::TYPE_MALICIOUS,
                'set'       => Url::SET_PREDICT,
                'predict'   => 0,
                'is_del'    => 0,
                'create_at' => time(),
            ];
            $save = Url::formatSave($save);
            try {
                $res = Url::create($save);
                $output->writeln(strval($res->getLastInsID()));
            } catch (\Exception $e) {
                $output->writeln($e->getMessage());
            }
        }
        return true;
    }

    public function tmp($input, $output)
    {
        try {
            // $total = Url::count();
            // $total = Jobs::field('max(id) as total')->select();
            $total      = Url::field('max(id) as total')->select();
            $total      = $total[0]['total'];
            $model      = new UrlVector();
            $util       = new UrlNN();
            $cacheModel = new UrlHttpCache();
            $limit      = 1000;
            $field      = 'id, url, hash, predict';
            $field      = 'id, hash, url, type, set, predict, is_del';
            // $field      = 'id, type, set, predict, is_del';
            // $field      = 'id, content, r_id';
            $containsChinese = function ($str) {
                $pattern = '/[\x{4e00}-\x{9fa5}]/u';
                return preg_match($pattern, $str) === 1;
            };
            $statistic       = [];
            $ids             = [];
            $fileName        = '20250428101759.log';
            $filePath        = runtime_path('temp') . $fileName;
            $formatLength    = function ($length, $unit = 'B') {
                $list = [
                    '100 b'  => 100,
                    '100 kb' => 102400,
                    '200 kb' => 204800,
                    '500 kb' => 512000,
                    '1 mb'   => 1048576,
                    '5 mb'   => 1048576 * 5,
                    '10 mb'  => 1048576 * 10
                ];
                foreach ($list as $key => $value) {
                    if ($length < $value) {
                        return $key;
                    }
                }
                return '> 10 mb';
            };
            $getCache        = function ($url) use ($cacheModel) {
                $httpCache = $cacheModel->getByUrl($url, ['field' => 'url_http, url_code']);
                if (!empty($httpCache) && $httpCache['url_code'] == 200) {
                    $httpCache['url_http'] = json_decode($httpCache['url_http'], true);
                    // $httpCache['url_whois'] = json_decode($httpCache['url_whois'], true);
                    // $httpCache['url_tls']   = json_decode($httpCache['url_tls'], true);
                    return [$httpCache['url_http'], [], []];
                }
                return [[], [], []];
            };
            $start           = file_get_contents(runtime_path('temp') . 'id.txt');
            if (empty($start)) {
                $start = 0;
            }
            $start     = 1;
            $delRecord = function ($id) use ($output) {
                $output->writeln(sprintf('del %s', $id));
                return Url::where('id', $id)->save(['is_del' => 1]);
            };
            for ($i = $start; $i < $total; $i += $limit) {
                $end     = $i + $limit;
                $records = Url::field($field)->where('id', '>=', $i)->where('id', '<', $end)->select();
                $output->writeln(sprintf('%s - %s', $i, $end));
                // $records = Url::field($field)->where('id', 1)->select();
                foreach ($records as $record) {
                    if ($record->is_del) {
                        continue;
                    }
                    if ($record->type != Url::TYPE_NORMAL) {
                        continue;
                    }
                    if ($record->set != Url::SET_PREDICT) {
                        continue;
                    }
                    // if ($record->predict != UrlNN::URL_PREDICT_OK) {
                    //     continue;
                    // }

                    // $httpCache = $cacheModel->getByHash($record->hash, ['field' => 'url_tls']);
                    // if (empty($httpCache)) {
                    //     continue;
                    // }
                    // $tls = [];
                    // if (!empty($httpCache['url_tls'])) {
                    //     $tls = json_decode($httpCache['url_tls'], true);
                    // }
                    // $key = $this->getHttpsFlag($record->url, $tls);
                    // try {
                    //     $domain = new Domain($record->domain);
                    //     $key = $domain->getTLD();
                    //     @$statistic[$key]++;
                    // } catch (\Exception $e) {
                    //     continue;
                    // }
                    $host            = CommonUtil::getHost($record->url);
                    $part = explode('.', $host);
                    foreach ($part as $key => $item) {
                        if ($item == 'www') {
                            unset($part[$key]);
                        }
                        if (in_array($item, UrlVector::CC_TLD_LIST) || in_array($item, UrlVector::SLD_LIST)) {
                            unset($part[$key]);
                        }
                    }
                    $count = count($part) - 1;
                    if ($count > 1) {
                        $log = sprintf('have sub domain:[%s] %s', $record->id, $host);
                        $output->writeln($log);
                        file_put_contents($filePath, $log . PHP_EOL, FILE_APPEND);
                        continue;
                    }
                    continue;
                    list($httpInfo, $whoisInfo, $tls) = $getCache($record->url);
                    if (empty($httpInfo)) {
                        $delRecord($record->id);
                        continue;
                    }
                    $body = $httpInfo['body'] ?? '';
                    $pattern = $this->formatRegex(UrlVector::PATTERN_CONTENT_KEYWORD);
                    if (empty($httpInfo['body']) || empty($pattern)) {
                        continue;
                    }
                    foreach (UrlVector::SKIP_SITE_HOST as $item) {
                        if (stripos($httpInfo['body'], $item) !== false) {
                            // $output->writeln(sprintf('body contains skip site: [%s] %s', $record->id, $item));
                            continue 2;
                        }
                    }
                    if (preg_match($pattern, $httpInfo['body'])) {
                        preg_match_all($pattern, $httpInfo['body'], $matches);
                        $matches[0] = array_unique($matches[0]);
                        $log = sprintf('match:[%s] %s', $record->id, json_encode($matches[0], JSON_UNESCAPED_UNICODE));
                        $output->writeln($log);
                        file_put_contents($filePath, $log . PHP_EOL, FILE_APPEND);
                        unset($matches);
                    }
                    continue;
                    foreach (UrlVector::LIST_CONTENT_KEYWORDS as $item) {
                        if (stripos($body, $item) !== false) {
                            $output->writeln(sprintf('body contains keyword: [%s] %s', $record->id, $item));
                        }
                    }
                    $pattern = $this->formatRegex(UrlVector::PATTERN_CONTENT_KEYWORD);
                    if (empty($httpInfo['body']) || empty($pattern)) {
                        continue;
                    }
                    foreach (UrlVector::SKIP_SITE_HOST as $item) {
                        if (stripos($httpInfo['body'], $item) !== false) {
                            $output->writeln(sprintf('body contains skip site: [%s] %s', $record->id, $item));
                            continue 2;
                        }
                    }
                    if (preg_match($pattern, $httpInfo['body'])) {
                        preg_match_all($pattern, $httpInfo['body'], $matches);
                        $output->writeln(sprintf('match:[%s] %s, result: %s', $record->id, json_encode($matches[0], JSON_UNESCAPED_UNICODE), UrlNN::URL_PREDICT_MAL));
                        unset($matches);
                    }
                    //
                    // // $length = $httpInfo['header']['content_length'] ?? 0;
                    // // $len    = $formatLength($length);
                    // $ipInfo = $httpInfo['http']['ip_loc'] ?? '';
                    // $ip     = $httpInfo['http']['ip'] ?? '';
                    // // @$statistic[$record->type][$len]++;
                    // $vector = $model->getVector(UrlVector::FLAG_IPGEO, $record->url, $httpInfo, $whoisInfo, $tls, true);
                    // $output->writeln(sprintf('%s - %s', $record->id, $vector));
                    // if ($vector) {
                    //     $pattern = '/[\x{4e00}-\x{9fa5}]/u';
                    //     preg_match_all($pattern, $body, $matches);
                    //     if (count($matches[0]) < 20) {
                    //         continue;
                    //     }
                    //     $log = sprintf('%s - %s - %s => %s', $record->hash, $ipInfo, $ip, implode(' ', $matches[0]));
                    //     $output->writeln($log);
                    //     file_put_contents($filePath, $log . PHP_EOL, FILE_APPEND);
                    //     // die();
                    // }
                    // continue;
                    // $vector = UrlVector::where('url_hash', $record->hash)->findOrEmpty();
                    // if ($vector->isEmpty()) {
                    //     continue;
                    // }
                    // $vectors = json_decode($vector->url_vector, true);
                    // if (!$vectors) {
                    //     continue;
                    // }
                    // $_v = $vectors[UrlVector::FLAG_IPGEO];
                    // @$statistic['total']++;
                    // if (!empty($_v)) {
                    //     if (stripos(strval($_v), '.') !== false) {
                    //         file_put_contents(runtime_path('temp') . 'id.txt', $record->id);
                    //         $output->writeln(sprintf('%s >>>>> %s', $record->hash, $_v));
                    //         die();
                    //     }
                    //     // @$statistic['malicious'] += $_v;
                    //     // list($httpInfo, $whoisInfo, $tls) = $getCache($record->url);
                    //     // if (empty($httpInfo)) {
                    //     //     @$statistic['del']++;
                    //     //     Url::where('id', $record->id)->update(['is_del' => 1]);
                    //     // }
                    //     // $body    = $httpInfo['body'] ?? '';
                    //     // $pattern = '/[\x{4e00}-\x{9fa5}]/u';
                    //     // preg_match_all($pattern, $body, $matches);
                    //     // file_put_contents($filePath, $record->hash . "\t" . count($matches[0]) . PHP_EOL, FILE_APPEND);
                    //     // $log = sprintf('%s => %s', $record->hash, implode(' ', $matches[0]));
                    //     // // $output->writeln($record->id);
                    //     // file_put_contents($filePath, $log . PHP_EOL, FILE_APPEND);
                    //     // $output->writeln(sprintf('%s - %s - %s', $vectors['ipGeoFlag'], $ipInfo, $record->hash));
                    // }
                    // $_data          = $record->toArray();
                    // $_data['rerun'] = Code::IS_YES;
                    // $max            = Jobs::getVectorQueueThreat();
                    // $rand           = rand(1, $max);
                    // //     Jobs::where('id', $record->id)->save(['queue' => Jobs::QUEUE_VECTOR . $rand]);
                    // Queue::push(\app\job\UrlVector::class, $_data, Jobs::QUEUE_VECTOR . $rand);
                    // $output->writeln(sprintf('%s', $record->id));
                    // die();
                    // }
                    // $vectors = json_decode($vector->url_vector, true);
                    // if (!$vectors) {
                    //     continue;
                    // }
                    // $type = intval($record->type);
                    // foreach ($vectors as $k => $v) {
                    //     if (!isset($model->vectorMap[$k])) {
                    //         continue;
                    //     }
                    //     @$statistic[$type][$k]['total']++;
                    //     if ($v === UrlNN::URL_PREDICT_OK) {
                    //         @$statistic[$type][$k]['ok']++;
                    //     } else {
                    //         @$statistic[$type][$k]['malicious'] += $v;
                    //     }
                    // }
                    // $urlRecord = Url::where('hash', $record->url_hash)->findOrEmpty();
                    // if ($urlRecord->isEmpty()) {
                    //     // $output->writeln(sprintf('Cache no hit: %s', $record->id));
                    //     continue;
                    // }
                    // $predict         = $util->urlPredict($record->url);
                    // $record->predict = $predict;
                    // $record->save();
                    // $output->writeln(sprintf('%s - %s', UrlNN::URL_PREDICT_DICT[$predict], $record->url));
                }
                // file_put_contents(runtime_path('temp') . 'statistic.txt', json_encode($statistic, JSON_PRETTY_PRINT));
            }
            // $output->writeln(json_encode($statistic, JSON_PRETTY_PRINT));
            $output->writeln('done');
        } catch (\Exception $e) {
            $output->writeln($e->getMessage());
            $output->writeln($e->getTraceAsString());
        }
    }

    private function getHttpsFlag($url, $tlsInfo)
    {
        $formatRegex = function ($reg) {
            if ($reg === '') {
                return '';
            }
            return '/' . $reg . '/';
        };
        $issuer      = $tlsInfo['issuer'] ?? '';
        if (empty($tlsInfo) || empty($issuer)) {
            return UrlNN::URL_PREDICT_MAL;
        }
        $to      = $tlsInfo['to'] ?? CommonUtil::getDate();
        $to      = strtotime($to);
        $now     = CommonUtil::getDate();
        $now     = strtotime($now);
        $oneYear = 31536000;
        $pattern = $formatRegex(self::PATTERN_HTTPS);
        if (preg_match($pattern, $issuer) && $now - $to >= $oneYear) {
            // return UrlNN::URL_PREDICT_OK;
        }
        $schema = parse_url($url, PHP_URL_SCHEME);
        if (strtolower($schema) == 'https') {
            return 0.5;
        }
        return UrlNN::URL_PREDICT_OK;
    }

    public function getData($input, $output)
    {
        $formatRegex = function ($reg) {
            if ($reg === '') {
                return '';
            }
            return '/' . $reg . '/';
        };
        try {
            // $total = Url::count();
            // $total = Jobs::field('max(id) as total')->select();
            $total      = Url::field('max(id) as total')->select();
            $total      = $total[0]['total'];
            $model      = new UrlVector();
            $util       = new UrlNN();
            $cacheModel = new UrlHttpCache();
            $limit      = 1000;
            $field      = 'id, hash, url, type, set, is_del';
            $tmpFile    = runtime_path('temp') . 'id.txt';
            $resFile    = runtime_path('temp') . 'media.txt';
            $resFile    = runtime_path('temp') . 'tls.txt';
            $statistic  = [];
            $lastId     = @file_get_contents($tmpFile);
            $recordId   = function ($id) use ($tmpFile) {
                return file_put_contents($tmpFile, $id);
            };
            if (empty($lastId)) {
                $lastId = 0;
            }
            for ($i = $lastId; $i < $total; $i += $limit) {
                $end     = $i + $limit;
                $records = Url::field($field)->where('id', '>', $i)->where('id', '<=', $end)->select();
                $output->writeln(sprintf('%s - %s', $i, $end));
                foreach ($records as $record) {
                    if ($record->is_del) {
                        continue;
                    }
                    $type = $record->type;
                    if ($record->type != Url::TYPE_MALICIOUS) {
                        continue;
                    }
                    if ($record->set != Url::SET_TRAIN) {
                        continue;
                    }

                    $cache = $cacheModel->getByHash($record->hash, ['field' => 'url_tls']);
                    if (empty($cache)) {
                        continue;
                    }
                    // $host     = CommonUtil::getHost($record->url);
                    // $domain   = new Domain($host);
                    // $httpInfo = json_decode($cache['url_http'], true);
                    $tlsInfo = json_decode($cache['url_tls'], true);
                    if (empty($tlsInfo)) {
                        continue;
                    }
                    $issuer = $tlsInfo['issuer'] ?? '';
                    // $output->writeln($issuer);
                    if (empty($tlsInfo) || empty($issuer)) {
                        @$statistic[$type]['empty']++;
                    } else {
                        @$statistic[$type]['not_empty']++;
                    }
                    continue;
                    $body = $httpInfo['body'] ?? '';
                    if (!$body) {
                        continue;
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
                                continue 2;
                            }
                        }
                        $words = preg_split(self::PATTERN_SPLIT_URL_2_WORDS, $src);
                        foreach ($words as $word) {
                            if (isset(self::SKIP_SRC_KEYWORDS[$word])) {
                                continue 2;
                            }
                            if (stripos($word, 'cdn') !== false) {
                                continue 2;
                            }
                        }
                        $total++;
                        $mediaHost   = CommonUtil::getHost($src);
                        $mediaDomain = new Domain($mediaHost);
                        if ($mediaDomain->getRegisterable() != $domain->getRegisterable()) {
                            $recordId($record->id);
                            $output->writeln(strval($record->id));
                            file_put_contents($resFile, $record->type . "\t" . $host . "\t" . $src . PHP_EOL, FILE_APPEND);
                        }
                    }
                }
            }
            $output->writeln(json_encode($statistic, JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            $output->writeln($e->getMessage());
            $output->writeln($e->getTraceAsString());
        }
        return true;
    }

    public function rerun($input, $output)
    {
        try {
            $where = [
                // 'type'   => Url::TYPE_MALICIOUS,
                // 'set'    => Url::SET_PREDICT,
                'is_del' => 0,
            ];
            $total = Url::field('max(id) as total, min(id) as start')->where($where)->select();
            $total = $total->toArray();
            [$start, $total] = [$total[0]['start'], $total[0]['total']];
            $model      = new UrlVector();
            $util       = new UrlNN();
            $cacheModel = new UrlHttpCache();
            $limit      = 1000;
            $field      = 'id, url, hash, type, set, is_del';
            $statistic  = [];
            for ($i = $start; $i <= $total; $i += $limit) {
                $end     = $i + $limit;
                $records = Url::field($field)->where('id', '>=', $i)->where('id', '<', $end)->select();
                $output->writeln(sprintf('%s - %s', $i, $end));
                $insert = [];
                foreach ($records as $record) {
                    if ($record->is_del) {
                        continue;
                    }
                    // if ($record->type != Url::TYPE_MALICIOUS) {
                    //     continue;
                    // }
                    // if ($record->set != Url::SET_PREDICT) {
                    //     continue;
                    // }
                    // $vector = UrlVector::field('id')->where('url_hash', $record->hash)->findOrEmpty();
                    // if ($vector->isEmpty()) {
                    //     continue;
                    // }
                    $_data          = $record->toArray();
                    $_data['rerun'] = Code::IS_YES;
                    $rand           = rand(1, Jobs::getVectorQueueThreat());
                    $insert[]       = [
                        'queue'          => Jobs::QUEUE_VECTOR . $rand,
                        'attempts'       => 0,
                        'reserve_time'   => null,
                        'available_time' => strtotime('now'),
                        'create_time'    => strtotime('now'),
                        'payload'        => json_encode([
                            'job'      => \app\job\UrlVector::class,
                            'maxTries' => null,
                            'timeout'  => null,
                            'data'     => $_data
                        ])
                    ];
                    // Queue::push(\app\job\UrlVector::class, $_data, Jobs::QUEUE_VECTOR . $rand);
                    // $output->writeln(sprintf('%s', $record->id));
                }
                if (!empty($insert)) {
                    Jobs::insertAll($insert);
                    $output->writeln(sprintf('Count >>> %s', count($insert)));
                }
            }
            $output->writeln(json_encode($statistic, JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            $output->writeln($e->getMessage());
            $output->writeln($e->getTraceAsString());
        }
        return true;
    }

    public function statistic($input, $output)
    {
        $map                                          = [
            [
                'type' => Url::TYPE_NORMAL,
                'set'  => [
                    Url::SET_TRAIN,
                    Url::SET_PREDICT
                ]
            ],
            [
                'type' => Url::TYPE_MALICIOUS,
                'set'  => [
                    Url::SET_TRAIN,
                    Url::SET_PREDICT
                ]
            ],
        ];
        $where                                        = [];
        $where[Url::TYPE_MALICIOUS][Url::SET_TRAIN]   = ' predict != ' . UrlNN::URL_PREDICT_UNKNOWN;
        $where[Url::TYPE_MALICIOUS][Url::SET_PREDICT] = ' predict != ' . UrlNN::URL_PREDICT_UNKNOWN;
        $where[Url::TYPE_NORMAL][Url::SET_TRAIN]      = ' predict != ' . UrlNN::URL_PREDICT_UNKNOWN;
        $where[Url::TYPE_NORMAL][Url::SET_PREDICT]    = ' predict != ' . UrlNN::URL_PREDICT_UNKNOWN;
        try {
            $table = new ConsoleTable();
            // $table->setHeaders(['Type', 'Total', 'Normal', 'Ratio', 'Malicious', 'Ratio']);
            $table->setHeaders(['类型', '总数', '正常', '比例', '恶意', '比例']);
            $percent     = function ($a, $b) {
                return round($a / $b * 100, 2) . '%';
            };
            $commonWhere = ['is_del' => 0];
            foreach ($map as $item) {
                $type = $item['type'];
                foreach ($item['set'] as $set) {
                    $totalWhere = $where[$type][$set] ?? '';
                    $totalModel = Url::field('count(id) as total')->where(['type' => $type, 'set' => $set])->where($commonWhere);
                    if ($totalWhere) {
                        $totalModel->where($totalWhere);
                    }
                    $total     = $totalModel->select();
                    $ok        = Url::field('count(id) as total')->where(['type' => $type, 'set' => $set, 'predict' => UrlNN::URL_PREDICT_OK])->where($commonWhere)->select();
                    $malicious = Url::field('count(id) as total')->where(['type' => $type, 'set' => $set, 'predict' => UrlNN::URL_PREDICT_MAL])->where($commonWhere)->select();
                    $total     = $total[0]['total'];
                    $ok        = $ok[0]['total'];
                    $malicious = $malicious[0]['total'];
                    $table->addRow([
                        sprintf('%s - %s', Url::TYPE_DICT[$type], Url::SET_DICT[$set]),
                        $total, $ok, $percent($ok, $total), $malicious, $percent($malicious, $total)
                    ]);
                }
            }
            $output->writeln($table->getTable());
        } catch (\Exception $e) {
            $output->writeln($e->getMessage());
            $output->writeln($e->getTraceAsString());
        }
        return true;
    }

    public function kill($input, $output)
    {
        try {
            for ($i = 1; $i <= self::BATCH_COUNT; $i++) {
                CommonUtil::execShell(sprintf(
                    'for pid in $(ps -ef | awk \'/php think fix p --args=%s/ {print $2}\'); do kill -9 $pid; done',
                    $i
                ));
            }
            CommonUtil::execShell(sprintf(
                'for pid in $(ps -ef | awk \'/php think fix p/ {print $2}\'); do kill -9 $pid; done',
                $i
            ));
        } catch (\Exception $e) {
            $output->writeln($e->getMessage());
            $output->writeln($e->getTraceAsString());
        }
    }

    public function batch($input, $output)
    {
        try {
            for ($i = 1; $i <= self::BATCH_COUNT; $i++) {
                $command = sprintf('php think fix p --args=%s > /dev/null &', $i);
                exec($command);
            }
        } catch (\Exception $e) {
            $output->writeln($e->getMessage());
            $output->writeln($e->getTraceAsString());
        }
    }

    public function predict($input, $output)
    {
        try {
            $batch = $input->hasOption('args') ? $input->getOption('args') : 0;
            $batch = intval($batch);
            $where = [
                // 'type'   => $batch,
                'set'    => Url::SET_PREDICT,
                'is_del' => 0,
            ];
            $total = Url::field('max(id) as total, min(id) as start')->where($where)->select();
            $total = $total->toArray();
            [$start, $total] = [$total[0]['start'], $total[0]['total']];
            $model      = new UrlVector();
            $util       = new UrlNN();
            $cacheModel = new UrlHttpCache();
            $limit      = 1000;
            $field      = 'id, url, hash, predict';
            $field      = 'id, url, type, set, is_del, predict';
            $statistic  = [];
            $formatUrl  = function ($str) {
                return $str;
                $str = substr($str, 0, 64);
                return rtrim($str, '/') . '/';
            };
            $start      = 0;
            for ($i = $start; $i <= $total; $i += $limit) {
                $end     = $i + $limit;
                $records = Url::field($field)->where('id', '>=', $i)->where('id', '<', $end)->select();
                $output->writeln(sprintf('%s - %s', $i, $end));
                foreach ($records as $record) {
                    if ($record->is_del) {
                        continue;
                    }
                    // if (!empty($batch) && fmod($record->id, $batch) != 0) {
                    //     continue;
                    // }
                    // if ($record->predict != UrlNN::URL_PREDICT_UNKNOWN) {
                    //     continue;
                    // }
                    // if ($record->type != $batch) {
                    //     continue;
                    // }
                    if ($record->set != Url::SET_PREDICT) {
                        continue;
                    }
                    // var_dump($formatUrl($record->url));
                    $predict = $util->urlPredict($formatUrl($record->url));
                    // var_dump($predict, $util->errInfo);
                    // die();
                    $record->predict = $predict;
                    $record->save();
                    $first = UrlNN::URL_PREDICT_DICT[$predict];
                    Desc::saveOrUpdate(['r_id' => $record->id, 'type' => Desc::TYPE_URL, 'content' => $util->errInfo], false);
                    // if ($predict == UrlNN::URL_PREDICT_UNKNOWN) {
                    //     $first .= "\t" . $util->errInfo;
                    // } else {
                    //     $data = ['r_id' => $record->id, 'type' => Desc::TYPE_URL, 'content' => 'p'];
                    //     $data = Desc::formatSave($data);
                    //     Desc::create($data);
                    // }
                    $output->writeln(sprintf('%s - %s', $first, $record->id));
                    // if ($predict == UrlNN::URL_PREDICT_UNKNOWN) {
                    //     $output->writeln(json_encode($record));
                    //     die();
                    // }
                    // if ($predict != UrlNN::URL_PREDICT_OK) {
                    //     $output->writeln(sprintf('%s - %s', UrlNN::URL_PREDICT_DICT[$predict], $record->url));
                    //     // @$statistic[$predict]++;
                    // }
                }
                // file_put_contents(runtime_path('temp') . 'statistic.txt', json_encode($statistic, JSON_PRETTY_PRINT));
            }
            // $output->writeln(json_encode($statistic, JSON_PRETTY_PRINT));
            $output->writeln('done');
        } catch (\Exception $e) {
            $output->writeln($e->getMessage());
            $output->writeln($e->getTraceAsString());
        }
        return true;
    }

    public function vectorStatistic($input, $output)
    {
        try {
            $where = [
                // 'type'   => Url::TYPE_NORMAL,
                'set'    => Url::SET_PREDICT,
                'is_del' => 0,
            ];
            $total = Url::field('max(id) as total, min(id) as start')->where($where)->select();
            $total = $total->toArray();
            [$start, $total] = [$total[0]['start'], $total[0]['total']];
            $model      = new UrlVector();
            $util       = new UrlNN();
            $cacheModel = new UrlHttpCache();
            $limit      = 1000;
            $field      = 'id, url, hash, predict';
            $field      = 'id, url, hash, type, set, is_del, predict';
            $field      = 'id, hash, type, set, is_del, predict';
            $statistic  = [];
            $formatUrl  = function ($str) {
                return $str;
                $str = substr($str, 0, 64);
                return rtrim($str, '/') . '/';
            };
            $table      = new ConsoleTable();
            // $table->setHeaders(['Type', 'Total', 'Normal', 'Ratio', 'Malicious', 'Ratio']);
            $table->setHeaders(['恶意', 'total', 'ok', 'malicious', '正常', 'total', 'ok', 'malicious']);
            for ($i = $start; $i <= $total; $i += $limit) {
                $end     = $i + $limit;
                $records = Url::field($field)->where('id', '>=', $i)->where('id', '<', $end)->select();
                $output->writeln(sprintf('%s - %s', $i, $end));
                foreach ($records as $record) {
                    if ($record->is_del) {
                        continue;
                    }
                    // if (!empty($batch) && fmod($record->id, $batch) != 0) {
                    //     continue;
                    // }
                    // if ($record->predict != UrlNN::URL_PREDICT_UNKNOWN) {
                    //     continue;
                    // }
                    // if ($record->type != $batch) {
                    //     continue;
                    // }
                    if ($record->set != Url::SET_PREDICT) {
                        continue;
                    }
                    $vector = UrlVector::where('url_hash', $record->hash)->findOrEmpty();
                    if ($vector->isEmpty()) {
                        continue;
                    }
                    $vectors = json_decode($vector->url_vector, true);
                    if (!$vectors) {
                        continue;
                    }
                    $type = $record->type;
                    foreach ($vectors as $flag => $vector) {
                        @$statistic[$flag][$type]['total']++;
                        if (!empty($vector)) {
                            @$statistic[$flag][$type]['malicious'] += $vector;
                        } else {
                            @$statistic[$flag][$type]['ok']++;
                        }
                    }
                }
                // break;
                // file_put_contents(runtime_path('temp') . 'statistic.txt', json_encode($statistic, JSON_PRETTY_PRINT));
            }
            $flags = [
                'containIpFlag',
                'contentKeywordFlag',
                'domainLengthFlag',
                'domainRegFlag',
                'doubleSlashFlag',
                'faviconFlag',
                'haveSubDomainFlag',
                'httpLengthFlag',
                'httpTypeFlag',
                'httpsFlag',
                'httpsTokenFlag',
                'iframeFlag',
                'ipGeoFlag',
                'ipHostFlag',
                'isHttpsFlag',
                'mediaLinkFlag',
                'mixWordFlag',
                'numberRatioFlag',
                'outLinkFlag',
                'outTagFlag',
                'prefixSuffixFlag',
                'randomDomainFlag',
                'sensitiveWordFlag',
                'shortUrlFlag',
                'subDomainCountFlag',
                'tldFlag',
                'urlContainToFlag',
            ];
            foreach ($flags as $flag) {
                $table->addRow([
                    $flag,
                    $statistic[$flag][Url::TYPE_MALICIOUS]['total'] ?? 0,
                    $statistic[$flag][Url::TYPE_MALICIOUS]['ok'] ?? 0,
                    $statistic[$flag][Url::TYPE_MALICIOUS]['malicious'] ?? 0,
                    $flag,
                    $statistic[$flag][Url::TYPE_NORMAL]['total'] ?? 0,
                    $statistic[$flag][Url::TYPE_NORMAL]['ok'] ?? 0,
                    $statistic[$flag][Url::TYPE_NORMAL]['malicious'] ?? 0,
                ]);
            }
            $output->writeln($table->getTable());
        } catch (\Exception $e) {
            $output->writeln($e->getMessage());
            $output->writeln($e->getTraceAsString());
        }
        return true;
    }
}
