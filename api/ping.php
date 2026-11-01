<?php
/**
 * 最小诊断接口 —— 不依赖 Meting，只回答三个问题：
 *   1. PHP 是否真的被执行了
 *   2. 服务器出口 IP 在哪
 *   3. 网易云等上游从这里能不能连通
 *
 * 部署：放到 api/ping.php
 * 访问：https://你的域名/api/ping.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$out = array();

// 1. PHP 执行标记
$out['php_executed'] = true;
$out['php_version']  = PHP_VERSION;
$out['sapi']         = php_sapi_name();
$out['time']         = date('c');

// 2. 扩展
$out['extensions'] = array(
    'bcmath'  => extension_loaded('bcmath')  ? 'OK' : 'MISSING',
    'curl'    => extension_loaded('curl')    ? 'OK' : 'MISSING',
    'openssl' => extension_loaded('openssl') ? 'OK' : 'MISSING',
    'json'    => extension_loaded('json')    ? 'OK' : 'MISSING',
    'dom'     => extension_loaded('dom')     ? 'OK' : 'MISSING',
);

// 3. 参数回显（验证 query 有没有传到 PHP）
$out['received'] = array(
    'query_string' => isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '',
    'GET'          => $_GET,
    'request_uri'  => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
);

// 4. 出口 IP
if (extension_loaded('curl')) {
    $ch = curl_init('https://api.ipify.org?format=json');
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => 0,
    ));
    $r = curl_exec($ch);
    $out['server_ip'] = $r ? json_decode($r, true) : array('errno' => curl_errno($ch), 'error' => curl_error($ch));
    curl_close($ch);
} else {
    $out['server_ip'] = 'curl missing';
}

// 5. 上游连通性 —— 直接带加密参数打网易云搜索
if (extension_loaded('curl')) {
    $probes = array();

    // 5a. 明文老接口 search/pc（GET）
    $u = 'http://music.163.com/api/search/pc?s=' . rawurlencode('晴天') . '&type=1&limit=3&offset=0';
    $probes['netease_search_pc'] = probe($u, array(
        'Referer: https://music.163.com/',
        'Cookie: appver=8.2.30; os=iPhone OS; osver=15.0',
        'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 CloudMusic/0.1.1 NeteaseMusic/8.2.30',
        'X-Real-IP: ' . long2ip(mt_rand(1884815360, 1884890111)),
    ));

    // 5b. cloudsearch/pc（源码当前使用，POST+加密，此处只探连通）
    $u2 = 'http://music.163.com/api/cloudsearch/pc';
    $probes['netease_cloudsearch'] = probe($u2, array(
        'Referer: https://music.163.com/',
        'Content-Type: application/x-www-form-urlencoded',
    ), 's=' . rawurlencode('晴天') . '&type=1&limit=3&total=true&offset=0');

    // 5c. 其它平台
    $probes['tencent'] = probe('https://c.y.qq.com/soso/fcgi-bin/client_search_cp?format=json&p=1&n=3&w=' . rawurlencode('晴天') . '&cr=1&new_json=1');
    $probes['kugou']   = probe('http://mobilecdn.kugou.com/api/v3/search/song?api_ver=1&area_code=1&correct=1&pagesize=3&plat=2&tag=1&sver=5&showtype=10&page=1&keyword=' . rawurlencode('晴天') . '&version=8990');
    $probes['kuwo']    = probe('http://www.kuwo.cn/search/searchMusicBykeyWord?all=' . rawurlencode('晴天') . '&pn=1&rn=3&vipver=1&client=kt&ft=music&cluster=0&strategy=2012&encoding=utf8&rformat=json&mobi=1&issubtitle=1&show_copyright_off=1');

    $out['upstream'] = $probes;
}

function probe($url, $headers = array(), $post = null, $timeout = 10) {
    $ch = curl_init($url);
    $opt = array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_ENCODING       => 'gzip',
        CURLOPT_IPRESOLVE      => 1,
        CURLOPT_HTTPHEADER     => $headers,
    );
    if ($post !== null) {
        $opt[CURLOPT_POST]       = 1;
        $opt[CURLOPT_POSTFIELDS] = $post;
    }
    curl_setopt_array($ch, $opt);
    $body = curl_exec($ch);
    $info = curl_getinfo($ch);
    $eno  = curl_errno($ch);
    $ems  = curl_errno($ch) ? curl_error($ch) : '';
    curl_close($ch);
    $res = array(
        'url'       => $url,
        'http_code' => isset($info['http_code']) ? $info['http_code'] : 0,
        'errno'     => $eno,
        'error'     => $ems,
        'bytes'     => is_string($body) ? strlen($body) : 0,
        'preview'   => is_string($body) ? mb_substr($body, 0, 300) : '',
    );
    return $res;
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
