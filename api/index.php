<?php
/**
 * MU Music API — HTTP 路由入口
 *
 * 职责边界
 *   本文件只处理 HTTP 语义：配置、CORS、参数校验、分发、响应封装、错误可观测。
 *   上游抓取逻辑全部留在 Meting.php，本文件不复制、不修改任何抓取代码。
 *
 * 部署
 *   api/index.php  +  api/Meting.php  +  api/home.php
 *
 * 环境变量（在部署平台配置，不写进代码）
 *   MU_DEBUG=1            开启调试，错误响应带 file/line 与上游原始片段
 *   MU_CORS_ORIGIN=*      跨域白名单，多域名用逗号分隔
 *   MU_PROXY=http://...   出网代理，服务器在海外且上游拿不到数据时配置
 *   MU_CACHE_TTL=0        响应缓存秒数，0 关闭
 *
 * PHP 5.4+
 */

// ---------------------------------------------------------------- 1. 配置

define('MU_API_VERSION', '1.0.0');

$CFG = array(
    'debug'     => (getenv('MU_DEBUG') === '1'),
    'cors'      => getenv('MU_CORS_ORIGIN') ? getenv('MU_CORS_ORIGIN') : '*',
    'proxy'     => getenv('MU_PROXY') ? getenv('MU_PROXY') : '',
    'cache_ttl' => intval(getenv('MU_CACHE_TTL')),
);

$VALID_SERVER = array('netease', 'tencent', 'kugou', 'kuwo');

$VALID_TYPE = array(
    'search', 'song', 'album', 'artist', 'playlist', 'url', 'lyric', 'pic',
);

// ---------------------------------------------------------------- 2. CORS

function cfg_cors_header($allow) {
    if ($allow === '*') {
        header('Access-Control-Allow-Origin: *');
        return;
    }
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    $list = array_map('trim', explode(',', $allow));
    if ($origin !== '' && in_array($origin, $list, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
}

cfg_cors_header($CFG['cors']);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('X-API-Version: ' . MU_API_VERSION);

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ------------------------------------------------- 3. 错误与输出控制

// 错误文本一律不写进响应体，避免污染 JSON
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// 致命错误兜底：解析错误、内存耗尽等不会走 try/catch
register_shutdown_function(function () use ($CFG) {
    $e = error_get_last();
    if ($e === null) {
        return;
    }
    $fatal = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR);
    if (!in_array($e['type'], $fatal, true)) {
        return;
    }
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    $body = array(
        'ok'      => false,
        'error'   => array('code' => 'fatal_error', 'message' => 'PHP 致命错误'),
    );
    if ($CFG['debug']) {
        $body['error']['detail'] = $e['message'];
        $body['error']['file']   = basename($e['file']);
        $body['error']['line']   = $e['line'];
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
});

// ---------------------------------------------------------------- 4. 响应封装

function respond($payload, $status) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function ok($data, $extra) {
    $body = array('ok' => true, 'data' => $data);
    if ($extra !== null) { $body['meta'] = $extra; }
    respond($body, 200);
}

function fail($status, $code, $message, $extra) {
    $err = array('code' => $code, 'message' => $message);
    if ($extra !== null && is_array($extra)) {
        $err = array_merge($err, $extra);
    }
    respond(array('ok' => false, 'error' => $err), $status);
}

// ---------------------------------------------------------------- 5. 缓存

function cache_dir() {
    $d = sys_get_temp_dir() . '/mu-cache';
    if (!is_dir($d)) { @mkdir($d, 0777, true); }
    return $d;
}

function cache_get($key, $ttl) {
    if ($ttl <= 0) { return null; }
    $f = cache_dir() . '/' . $key . '.json';
    if (!is_file($f)) { return null; }
    if (time() - filemtime($f) > $ttl) { @unlink($f); return null; }
    $raw = @file_get_contents($f);
    return $raw === false ? null : json_decode($raw, true);
}

function cache_put($key, $ttl, $value) {
    if ($ttl <= 0) { return; }
    @file_put_contents(cache_dir() . '/' . $key . '.json',
        json_encode($value, JSON_UNESCAPED_UNICODE));
}

// ---------------------------------------------------------------- 6. 载入类库

// 捕获载入期间的意外输出（BOM、尾部空行等），防止破坏 JSON
ob_start();

if (!class_exists('Metowolf\Meting')) {
    $lib = __DIR__ . '/Meting.php';
    if (!is_file($lib)) {
        ob_end_clean();
        fail(500, 'library_missing', 'Meting.php 不存在，请与 index.php 放在同一目录', null);
    }
    require_once $lib;
}

if (!class_exists('Metowolf\Meting')) {
    ob_end_clean();
    fail(500, 'library_broken', 'Meting.php 已载入但类未定义，文件可能损坏', null);
}

// ---------------------------------------------------------------- 7. 参数校验

$input = array_merge($_GET, $_POST);

function pick($arr, $key, $default) {
    return (isset($arr[$key]) && $arr[$key] !== '') ? $arr[$key] : $default;
}

function int_param($arr, $key, $default, $min, $max) {
    if (!isset($arr[$key]) || $arr[$key] === '') { return $default; }
    $v = intval($arr[$key]);
    if ($v < $min) { return $min; }
    if ($v > $max) { return $max; }
    return $v;
}

$server = strtolower(trim(pick($input, 'server', 'netease')));
$type   = strtolower(trim(pick($input, 'type', '')));
$id     = trim(pick($input, 'id', ''));

if (!in_array($server, $VALID_SERVER, true)) {
    $server = 'netease';
}

if ($type === '') {
    ob_end_clean();
    fail(400, 'missing_type', '缺少 type 参数',
        array('allowed' => $VALID_TYPE));
}

if (!in_array($type, $VALID_TYPE, true)) {
    ob_end_clean();
    fail(404, 'unknown_type', '未知的 type：' . $type,
        array('allowed' => $VALID_TYPE));
}

if ($type !== 'search' && $id === '') {
    ob_end_clean();
    fail(400, 'missing_id', '缺少 id 参数', null);
}

if ($type === 'search' && $id === '') {
    ob_end_clean();
    fail(400, 'empty_keyword', '搜索关键词不能为空', null);
}

// ---------------------------------------------------------------- 8. 分发

$cacheKey = null;
$result   = null;
$meta     = array();
$api      = null;

// 查询串参与缓存键，保证不同参数互不干扰
$cacheKey = md5($server . '|' . $type . '|' . $id . '|'
    . (isset($input['page'])  ? $input['page']  : '')
    . '|' . (isset($input['limit']) ? $input['limit'] : '')
    . '|' . (isset($input['br'])    ? $input['br']    : '')
    . '|' . (isset($input['size'])  ? $input['size']  : ''));

// 命中缓存直接返回，避免重复打上游
$hit = cache_get($cacheKey, $CFG['cache_ttl']);
if ($hit !== null && isset($hit['data'])) {
    ob_end_clean();
    $hit['meta']['cached'] = true;
    respond($hit, 200);
}

try {
    $api = new Metowolf\Meting($server);
    $api->format(true);
    if ($CFG['proxy'] !== '') {
        $api->proxy($CFG['proxy']);
    }

    switch ($type) {
        case 'search':
            $page  = int_param($input, 'page', 1, 1, 500);
            $limit = int_param($input, 'limit', 30, 1, 100);
            $result = $api->search($id, array('page' => $page, 'limit' => $limit));
            $meta = array('page' => $page, 'limit' => $limit);
            break;

        case 'song':
            $result = $api->song($id);
            break;

        case 'album':
            $result = $api->album($id);
            break;

        case 'artist':
            $limit    = int_param($input, 'limit', 50, 1, 100);
            $result   = $api->artist($id, $limit);
            $meta     = array('limit' => $limit);
            break;

        case 'playlist':
            $result = $api->playlist($id);
            break;

        case 'url':
            $br       = int_param($input, 'br', 320, 64, 2000);
            $result   = $api->url($id, $br);
            $meta     = array('br' => $br);
            break;

        case 'lyric':
            $result = $api->lyric($id);
            break;

        case 'pic':
            $size     = int_param($input, 'size', 400, 64, 2000);
            $result   = $api->pic($id, $size);
            $meta     = array('size' => $size);
            break;
    }
} catch (Exception $e) {
    ob_end_clean();
    $extra = null;
    if ($CFG['debug']) {
        $extra = array('detail' => $e->getMessage(),
                       'file'   => basename($e->getFile()),
                       'line'   => $e->getLine());
    }
    fail(500, 'exception', '处理请求时发生异常', $extra);
}

// ---------------------------------------------------------------- 9. 结果校验

// 关键区分：空字符串是上游失败，[] 是正常的空结果
if ($result === null || $result === false || $result === '') {
    ob_end_clean();
    $extra = array('server' => $server, 'type' => $type, 'hint' =>
        '上游未返回数据。常见原因：服务器在海外被风控、网络不通、需要代理、或该资源无版权');
    if ($CFG['debug']) {
        $extra['info']   = isset($api->info)  ? $api->info  : null;
        $extra['errno']  = isset($api->error) ? $api->error : null;
        $extra['curlerr']= isset($api->status)? $api->status: null;
        if (isset($api->raw)) { $extra['raw'] = mb_substr($api->raw, 0, 500); }
    }
    fail(502, 'upstream_empty', '上游返回空响应', $extra);
}

$decoded = json_decode($result, true);
if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    ob_end_clean();
    $extra = array('json_error' => json_last_error_msg());
    if ($CFG['debug']) { $extra['raw'] = mb_substr($result, 0, 500); }
    fail(502, 'upstream_invalid', '上游返回的不是合法 JSON', $extra);
}

// 丢弃载入阶段的意外输出
ob_end_clean();

// ---------------------------------------------------------------- 10. 输出

$meta['server'] = $server;
$meta['type']   = $type;
$meta['cached'] = false;

$envelope = array('ok' => true, 'data' => $decoded, 'meta' => $meta);
if ($CFG['cache_ttl'] > 0 && $cacheKey !== null) {
    cache_put($cacheKey, $CFG['cache_ttl'], $envelope);
}

respond($envelope, 200);
