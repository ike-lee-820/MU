<?php
/**
 * MU 音乐 API — 主页面（接口文档 + 在线测试台）
 *
 * 部署方式：
 *   1) 放到项目根目录，命名 index.php（推荐）
 *      访问 https://你的域名/ 即可打开
 *   2) 若部署环境只执行 api/ 目录下的 PHP（如 Vercel 的 vercel-php），
 *      把本文件放到 api/ 下，命名 home.php，访问 https://你的域名/api/home.php
 *      页面会自动把接口地址推导为同目录的 index.php
 *
 * 环境要求：PHP 5.4+
 */

// ---------- 自动推导接口地址 ----------
$scheme = 'http';
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'];
} elseif (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' && $_SERVER['HTTPS'] !== '') {
    $scheme = 'https';
} elseif (isset($_SERVER['REQUEST_SCHEME'])) {
    $scheme = $_SERVER['REQUEST_SCHEME'];
}
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$dir  = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])) : '';
$dir  = rtrim($dir, '/');

if (substr($dir, -4) === '/api') {
    // 本页位于 api/ 目录，接口为同目录的 index.php
    $apiDefault = $dir . '/index.php';
} else {
    // 本页位于根目录，接口为 api/index.php
    $apiDefault = ($dir === '' ? '' : $dir) . '/api/index.php';
}
$apiDefault = $scheme . '://' . $host . $apiDefault;

// ---------- 接口定义（文档与测试台共用的唯一数据源） ----------
$SERVERS = array(
    array('value' => 'netease', 'label' => '网易云音乐', 'id' => '数字歌曲 ID'),
    array('value' => 'tencent', 'label' => 'QQ 音乐',    'id' => 'songmid 字符串'),
    array('value' => 'kugou',   'label' => '酷狗音乐',   'id' => 'hash 32 位字符串'),
    array('value' => 'kuwo',    'label' => '酷我音乐',   'id' => 'rid 数字'),
);

$SUPPORT = array(
    //        netease tencent kugou   kuwo
    'search'   => array(1, 1, 1, 1),
    'song'     => array(1, 1, 1, 1),
    'album'    => array(1, 1, 1, 2),
    'artist'   => array(1, 2, 2, 2),
    'playlist' => array(1, 1, 2, 2),
    'url'      => array(1, 1, 1, 1),
    'lyric'    => array(1, 1, 1, 1),
    'pic'      => array(1, 1, 1, 1),
);

$INTERFACES = array(
    'search' => array(
        'title'   => '搜索',
        'summary' => '按关键词搜索单曲，返回歌曲对象数组。',
        'returns' => '歌曲对象数组',
        'params'  => array(
            array('name' => 'id',    'label' => '关键词',   'type' => 'text',   'default' => '',   'desc' => '歌名或歌手名，必填'),
            array('name' => 'page',  'label' => '页码',     'type' => 'number', 'default' => '1',  'desc' => '默认 1'),
            array('name' => 'limit', 'label' => '每页条数', 'type' => 'number', 'default' => '30', 'desc' => '默认 30'),
        ),
        'sample'   => array('id' => '晴天', 'page' => '1', 'limit' => '10'),
        'response' => '[
  {
    "id": 35847388,
    "name": "Hello",
    "artist": ["Adele"],
    "album": "Hello",
    "pic_id": "1407374890649284",
    "url_id": 35847388,
    "lyric_id": 35847388,
    "source": "netease"
  }
]',
        'note' => '网易云侧走 cloudsearch/pc，另有一个内部子参数 type（默认 1 表示单曲）；其余平台固定搜索单曲。',
    ),
    'song' => array(
        'title'   => '歌曲详情',
        'summary' => '按歌曲 ID 查询详情，返回单元素歌曲对象数组。',
        'returns' => '歌曲对象数组',
        'params'  => array(
            array('name' => 'id', 'label' => '歌曲 ID', 'type' => 'text', 'default' => '', 'desc' => '必填'),
        ),
        'sample'   => array('id' => '35847388'),
        'response' => '[
  {
    "id": 35847388,
    "name": "Hello",
    "artist": ["Adele"],
    "album": "Hello",
    "source": "netease"
  }
]',
        'note' => '酷狗走 m.kugou.com 的 getSongInfo，酷我走 datacenter.kuwo.cn 批量查询接口。',
    ),
    'album' => array(
        'title'   => '专辑歌曲',
        'summary' => '按专辑 ID 拉取专辑内歌曲列表。',
        'returns' => '歌曲对象数组',
        'params'  => array(
            array('name' => 'id', 'label' => '专辑 ID', 'type' => 'text', 'default' => '', 'desc' => '必填'),
        ),
        'sample'   => array('id' => '34879'),
        'response' => '[ /* 歌曲对象数组 */ ]',
        'note' => '网易云固定拉取 1000 首；酷狗 pagesize 为 -1 取全部；酷我是解析专辑页 HTML，页面结构变动时会失效。',
    ),
    'artist' => array(
        'title'   => '歌手热门歌曲',
        'summary' => '按歌手 ID 拉取热门歌曲。',
        'returns' => '歌曲对象数组',
        'params'  => array(
            array('name' => 'id',    'label' => '歌手 ID', 'type' => 'text',   'default' => '',   'desc' => '必填'),
            array('name' => 'limit', 'label' => '返回条数', 'type' => 'number', 'default' => '50', 'desc' => '默认 50'),
        ),
        'sample'   => array('id' => '3684', 'limit' => '20'),
        'response' => '[ /* 歌曲对象数组 */ ]',
        'note' => '仅网易云分支可靠；源码中腾讯与酷狗分支误用了酷狗的歌手接口地址，建议改用搜索代替。',
    ),
    'playlist' => array(
        'title'   => '歌单歌曲',
        'summary' => '按歌单 ID 拉取歌单内歌曲。',
        'returns' => '歌曲对象数组',
        'params'  => array(
            array('name' => 'id', 'label' => '歌单 ID', 'type' => 'text', 'default' => '', 'desc' => '酷我填 pid，必填'),
        ),
        'sample'   => array('id' => '2250011882'),
        'response' => '[ /* 歌曲对象数组 */ ]',
        'note' => '网易云取 playlist.tracks，QQ 取 cdlist 下的 songlist，酷我最多返回 1000 首。',
    ),
    'url' => array(
        'title'   => '播放直链',
        'summary' => '获取歌曲可播放的音频直链。',
        'returns' => '单对象',
        'params'  => array(
            array('name' => 'id', 'label' => '歌曲 ID', 'type' => 'text',   'default' => '',    'desc' => '填 url_id，必填'),
            array('name' => 'br', 'label' => '目标码率', 'type' => 'number', 'default' => '320', 'desc' => '单位 kbps，默认 320'),
        ),
        'sample'   => array('id' => '35847388', 'br' => '320'),
        'response' => '{
  "url": "http://m701.music.126.net/.../xxx.mp3",
  "size": 4729252,
  "br": 320
}',
        'note' => 'url 为空串且 br 为 -1 表示无资源或无版权。QQ 音乐会二次换取 vkey 并在不高于 br 的范围内取最高音质；酷我固定 128kbps 且不含 size 字段。直链带时效与 Referer 校验，建议在有效期内使用或由服务端代理转发。',
    ),
    'lyric' => array(
        'title'   => '歌词',
        'summary' => '获取歌词原文与翻译。',
        'returns' => '单对象',
        'params'  => array(
            array('name' => 'id', 'label' => '歌曲 ID', 'type' => 'text', 'default' => '', 'desc' => '填 lyric_id，必填'),
        ),
        'sample'   => array('id' => '35847388'),
        'response' => '{
  "lyric": "[00:00.000] 作词 : ...\n[00:12.34]Hello, it is me",
  "tlyric": ""
}',
        'note' => '返回 LRC 格式文本含时间轴。酷狗与酷我的 tlyric 恒为空串；QQ 音乐返回的是 JSONP 包裹的 Base64，接口内部已解码。',
    ),
    'pic' => array(
        'title'   => '封面图',
        'summary' => '获取歌曲封面图片地址。',
        'returns' => '单对象',
        'params'  => array(
            array('name' => 'id',   'label' => '歌曲 ID', 'type' => 'text',   'default' => '',    'desc' => '填 pic_id，必填'),
            array('name' => 'size', 'label' => '图片边长', 'type' => 'number', 'default' => '400', 'desc' => '像素，正方形，默认 400'),
        ),
        'sample'   => array('id' => '35847388', 'size' => '300'),
        'response' => '{
  "url": "https://p3.music.126.net/xxx/yyy.jpg?param=300y300"
}',
        'note' => '该接口内部会先调用一次歌曲详情拿专辑信息，因此比其它接口慢一跳；酷我专辑信息缺失时返回默认占位图。',
    ),
);

$jsonFlags = 0;
if (defined('JSON_UNESCAPED_UNICODE')) { $jsonFlags |= JSON_UNESCAPED_UNICODE; }
if (defined('JSON_HEX_TAG'))          { $jsonFlags |= JSON_HEX_TAG; }
if (defined('JSON_HEX_AMP'))          { $jsonFlags |= JSON_HEX_AMP; }
if (defined('JSON_HEX_APOS'))         { $jsonFlags |= JSON_HEX_APOS; }
if (defined('JSON_HEX_QUOT'))         { $jsonFlags |= JSON_HEX_QUOT; }

$JS_INTERFACES = json_encode($INTERFACES, $jsonFlags);
$JS_SUPPORT    = json_encode($SUPPORT, $jsonFlags);

function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>MU 音乐 API · 接口文档与测试台</title>
<style>
:root{
  --p:#6750A4; --on-p:#FFFFFF; --p-c:#EADDFF; --on-p-c:#21005D;
  --s-c:#E8DEF8; --on-s-c:#1D192B;
  --surf:#FEF7FF; --surf-low:#F7F2FA; --surf-c:#F3EDF7; --surf-hi:#ECE6F0;
  --on-surf:#1D1B20; --on-surf-v:#49454F;
  --out:#79747E; --out-v:#CAC4D0;
  --err:#B3261E; --on-err:#FFFFFF; --err-c:#F9DEDC;
  --ok:#146C43; --ok-c:#D7F2E3;
  --inv:#322F35; --on-inv:#F5EFF7;
  --sh1:0 1px 2px rgba(0,0,0,.30),0 1px 3px 1px rgba(0,0,0,.15);
  --sh0:0 1px 3px rgba(0,0,0,.12);
  --r-s:4px; --r-m:12px; --r-l:16px; --r-f:28px;
}
@media (prefers-color-scheme: dark){
  :root:not([data-theme="light"]){
    --p:#D0BCFF; --on-p:#381E72; --p-c:#4F378B; --on-p-c:#EADDFF;
    --s-c:#4A4458; --on-s-c:#E8DEF8;
    --surf:#141218; --surf-low:#1D1B20; --surf-c:#211F26; --surf-hi:#2B2931;
    --on-surf:#E6E0E9; --on-surf-v:#CAC4D0;
    --out:#938F99; --out-v:#49454F;
    --err:#F2B8B5; --on-err:#601410; --err-c:#8C1D18;
    --ok:#6FDB9C; --ok-c:#0E4C2C;
    --inv:#E6E0E9; --on-inv:#322F35;
  }
}
html[data-theme="dark"]{
  --p:#D0BCFF; --on-p:#381E72; --p-c:#4F378B; --on-p-c:#EADDFF;
  --s-c:#4A4458; --on-s-c:#E8DEF8;
  --surf:#141218; --surf-low:#1D1B20; --surf-c:#211F26; --surf-hi:#2B2931;
  --on-surf:#E6E0E9; --on-surf-v:#CAC4D0;
  --out:#938F99; --out-v:#49454F;
  --err:#F2B8B5; --on-err:#601410; --err-c:#8C1D18;
  --ok:#6FDB9C; --ok-c:#0E4C2C;
  --inv:#E6E0E9; --on-inv:#322F35;
}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
body{
  margin:0; background:var(--surf); color:var(--on-surf);
  font-family:Roboto,"Noto Sans SC","PingFang SC","Microsoft YaHei",system-ui,sans-serif;
  font-size:15px; line-height:1.55; -webkit-font-smoothing:antialiased;
}
.wrap{max-width:900px;margin:0 auto;padding:0 16px 96px}

/* ---------- App bar ---------- */
.appbar{
  position:sticky;top:0;z-index:20;background:var(--surf-c);
  padding:14px 16px 0;transition:box-shadow .2s;
}
.appbar.s{box-shadow:var(--sh1)}
.appbar-in{max-width:900px;margin:0 auto;display:flex;align-items:center;gap:8px}
.appbar h1{font-size:22px;font-weight:400;margin:0;flex:1;letter-spacing:.02em}
.icobtn{
  width:40px;height:40px;border:none;background:transparent;border-radius:50%;
  color:var(--on-surf-v);cursor:pointer;display:flex;align-items:center;justify-content:center;
}
.icobtn:active{background:var(--surf-hi)}
svg.i{width:22px;height:22px;fill:currentColor;display:block}

/* ---------- Tabs ---------- */
.tabs{display:flex;gap:4px;max-width:900px;margin:0 auto}
.tab{
  flex:1;border:none;background:transparent;color:var(--on-surf-v);
  padding:12px 8px 13px;font-size:14px;font-weight:500;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:8px;
  border-bottom:2px solid transparent;position:relative;
}
.tab .ti{opacity:.9}
.tab[aria-selected="true"]{color:var(--p);border-bottom-color:var(--p)}
.tab:active{background:var(--surf-c)}

/* ---------- Card ---------- */
.card{
  background:var(--surf-low);border-radius:var(--r-m);padding:16px;margin:12px 0;
}
.card.out{background:var(--surf);border:1px solid var(--out-v)}
.card h2{font-size:16px;font-weight:500;margin:0 0 4px}
.card h3{font-size:15px;font-weight:500;margin:20px 0 8px}
.sub{color:var(--on-surf-v);font-size:13px;margin:0}
.sec-t{font-size:13px;font-weight:600;letter-spacing:.08em;color:var(--p);margin:26px 0 6px;text-transform:uppercase}

/* ---------- Table ---------- */
.tw{overflow-x:auto;-webkit-overflow-scrolling:touch;margin:8px -4px;padding:0 4px}
table{border-collapse:collapse;width:100%;font-size:13.5px;min-width:420px}
th,td{text-align:left;padding:11px 10px;border-bottom:1px solid var(--out-v);vertical-align:top}
th{color:var(--on-surf-v);font-weight:600;white-space:nowrap;font-size:12.5px}
td.c,th.c{text-align:center}
tr:last-child td{border-bottom:none}
code,.mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12.8px}
code{background:var(--surf-hi);padding:1.5px 5px;border-radius:var(--r-s)}
.pill{display:inline-block;padding:2px 9px;border-radius:var(--r-s);font-size:12px;white-space:nowrap}
.pill.y{background:var(--ok-c);color:var(--ok)}
.pill.w{background:#FFF0C2;color:#7A5600}
.pill.n{background:var(--err-c);color:var(--err)}

/* ---------- Fields ---------- */
.f{position:relative;margin:12px 0}
.f input,.f select{
  width:100%;font:inherit;font-size:16px;color:var(--on-surf);
  background:transparent;border:1px solid var(--out);border-radius:var(--r-s);
  padding:17px 12px 7px;outline:none;appearance:none;-webkit-appearance:none;
}
.f select{padding-right:34px;cursor:pointer}
.f select option{background:var(--surf);color:var(--on-surf)}
.f label{
  position:absolute;left:9px;top:15px;font-size:16px;color:var(--on-surf-v);
  pointer-events:none;transition:.15s ease;background:transparent;padding:0 3px;
}
.f input:focus+label,.f input:not(:placeholder-shown)+label,
.f select+label{
  top:-7px;font-size:12px;color:var(--p);background:var(--surf-low);border-radius:2px;
}
.f input:focus,.f select:focus{border-color:var(--p);border-width:2px;padding:16px 11px 6px}
.f .hint{font-size:12px;color:var(--on-surf-v);margin:5px 2px 0}
.f .arw{position:absolute;right:12px;top:50%;margin-top:-11px;pointer-events:none;color:var(--on-surf-v)}
.row{display:flex;gap:12px;flex-wrap:wrap}
.row>.f{flex:1 1 130px;min-width:120px}

/* ---------- Chips ---------- */
.chips{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 2px}
.chip{
  border:1px solid var(--out);background:transparent;color:var(--on-surf-v);
  border-radius:8px;height:32px;padding:0 16px;font:inherit;font-size:13px;cursor:pointer;
  display:inline-flex;align-items:center;
}
.chip[aria-pressed="true"]{background:var(--s-c);border-color:transparent;color:var(--on-s-c);font-weight:500}
.chip:active{background:var(--surf-hi)}

/* ---------- Buttons ---------- */
.btn{
  border:none;border-radius:20px;height:40px;padding:0 24px;font:inherit;font-size:14px;
  font-weight:500;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:8px;
}
.btn.f{background:var(--p);color:var(--on-p)}
.btn.f:active{box-shadow:inset 0 0 0 100px rgba(255,255,255,.12)}
.btn.t{background:var(--s-c);color:var(--on-s-c)}
.btn.o{background:transparent;color:var(--p);border:1px solid var(--out);padding:0 22px}
.btn.x{background:transparent;color:var(--p);padding:0 14px;min-width:64px}
.btn:disabled{opacity:.38;cursor:not-allowed}
.acts{display:flex;gap:10px;flex-wrap:wrap;margin:16px 0 4px}

/* ---------- Result ---------- */
.meta{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:12px 0 8px}
.tag{
  font-size:12px;padding:3px 10px;border-radius:var(--r-s);
  background:var(--surf-hi);color:var(--on-surf-v);
}
.tag.ok{background:var(--ok-c);color:var(--ok)}
.tag.bad{background:var(--err-c);color:var(--err)}
pre.j{
  background:var(--surf-hi);border-radius:var(--r-s);padding:12px;margin:0;
  overflow:auto;max-height:420px;font-size:12.6px;line-height:1.6;
  font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;white-space:pre-wrap;word-break:break-word;
}
.k{color:#7B4FC0}.s{color:#0F7B4F}.n{color:#B26A00}.b{color:#1E63B5}.z{color:var(--on-surf-v)}
html[data-theme="dark"] .k{color:#D0BCFF}
html[data-theme="dark"] .s{color:#7EE2A8}
html[data-theme="dark"] .n{color:#FFB86B}
html[data-theme="dark"] .b{color:#8FBEFF}
@media (prefers-color-scheme:dark){
  :root:not([data-theme="light"]) .k{color:#D0BCFF}
  :root:not([data-theme="light"]) .s{color:#7EE2A8}
  :root:not([data-theme="light"]) .n{color:#FFB86B}
  :root:not([data-theme="light"]) .b{color:#8FBEFF}
}
.urlbox{
  background:var(--surf-hi);border-radius:var(--r-s);padding:10px 12px;margin:8px 0;
  font-size:12.4px;word-break:break-all;display:flex;gap:8px;align-items:flex-start;
}
.urlbox span{flex:1;font-family:ui-monospace,Menlo,Consolas,monospace}

/* ---------- Song list ---------- */
.sl{margin:10px 0 0;border-radius:var(--r-m);overflow:hidden;background:var(--surf)}
.si{display:flex;align-items:center;gap:12px;padding:11px 12px;border-bottom:1px solid var(--out-v)}
.si:last-child{border-bottom:none}
.si .tx{flex:1;min-width:0}
.si .nm{font-size:14.5px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.si .ar{font-size:12.5px;color:var(--on-surf-v);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.si .op{display:flex;gap:2px;flex:none}
.mini{
  border:none;background:transparent;color:var(--p);font:inherit;font-size:12.5px;
  padding:6px 8px;border-radius:var(--r-s);cursor:pointer;white-space:nowrap;
}
.mini:active{background:var(--surf-hi)}
.src{font-size:11px;color:var(--on-surf-v);border:1px solid var(--out-v);border-radius:var(--r-s);padding:1px 6px}

/* ---------- Snackbar ---------- */
.sn{
  position:fixed;left:16px;right:16px;bottom:16px;z-index:60;
  background:var(--inv);color:var(--on-inv);border-radius:var(--r-s);
  padding:14px 16px;font-size:14px;box-shadow:var(--sh1);
  transform:translateY(140%);transition:transform .22s cubic-bezier(.2,0,0,1);
  display:flex;align-items:center;gap:16px;max-width:600px;margin:0 auto;
}
.sn.show{transform:translateY(0)}
.sn button{border:none;background:transparent;color:#C9BCFF;font:inherit;font-size:13px;font-weight:600;cursor:pointer}
html[data-theme="dark"] .sn button{color:#7A5BB0}

.hide{display:none!important}
.foot{color:var(--on-surf-v);font-size:12.5px;text-align:center;padding:24px 0 0}
hr.sep{border:none;border-top:1px solid var(--out-v);margin:22px 0}
</style>
</head>
<body>

<svg style="display:none" aria-hidden="true">
  <symbol id="i-doc" viewBox="0 0 24 24"><path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></symbol>
  <symbol id="i-lab" viewBox="0 0 24 24"><path d="M9.4 16.6 4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0 4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></symbol>
  <symbol id="i-send" viewBox="0 0 24 24"><path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/></symbol>
  <symbol id="i-copy" viewBox="0 0 24 24"><path d="M16 1H4a2 2 0 0 0-2 2v14h2V3h12V1zm3 4H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2zm0 16H8V7h11v14z"/></symbol>
  <symbol id="i-moon" viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36A5.4 5.4 0 0 1 15.6 6.1C15.16 6.04 14.7 6 14.24 6H12V3z"/></symbol>
  <symbol id="i-sun" viewBox="0 0 24 24"><path d="M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm0-5v3h0V2zm0 19v-3M2 12h3m14 0h3M4.2 4.2l2.1 2.1m11.4 11.4 2.1 2.1M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/></symbol>
  <symbol id="i-note" viewBox="0 0 24 24"><path d="M12 3v10.55A4 4 0 1 0 14 17V7h4V3h-6z"/></symbol>
  <symbol id="i-drop" viewBox="0 0 24 24"><path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z"/></symbol>
</svg>

<header class="appbar" id="bar">
  <div class="appbar-in">
    <h1>MU 音乐 API</h1>
    <button class="icobtn" id="theme" title="切换明暗主题" aria-label="切换明暗主题"><svg class="i"><use href="#i-moon"/></svg></button>
  </div>
  <div class="tabs" role="tablist">
    <button class="tab" role="tab" aria-selected="true"  data-p="doc"><svg class="i ti"><use href="#i-doc"/></svg>接口文档</button>
    <button class="tab" role="tab" aria-selected="false" data-p="lab"><svg class="i ti"><use href="#i-lab"/></svg>在线测试</button>
  </div>
</header>

<div class="wrap">

<!-- ===================== 文档 ===================== -->
<section id="p-doc">

  <div class="card out">
    <h2>概览</h2>
    <p class="sub">单文件 PHP 音乐接口，聚合网易云、QQ 音乐、酷狗、酷我四大平台，输出统一的歌曲数据结构。</p>
    <p class="sub" style="margin-top:8px">核心类 <code>Metowolf\Meting</code> 版本 1.5.20，源自 ELDment/Meting-Fixed。</p>
  </div>

  <div class="sec-t">支持平台</div>
  <div class="card out">
    <div class="tw"><table>
      <thead><tr><th>server 取值</th><th>平台</th><th>ID 类型</th></tr></thead>
      <tbody>
      <?php foreach ($SERVERS as $s) { ?>
        <tr><td><code><?php echo h($s['value']); ?></code></td><td><?php echo h($s['label']); ?></td><td><?php echo h($s['id']); ?></td></tr>
      <?php } ?>
      </tbody>
    </table></div>
    <p class="sub" style="margin-top:8px">server 缺省或取值非法时自动回落到 netease。</p>
  </div>

  <div class="sec-t">接口一览</div>
  <div class="card out">
    <div class="tw"><table>
      <thead><tr><th>type</th><th>说明</th><th class="c">网易云</th><th class="c">QQ</th><th class="c">酷狗</th><th class="c">酷我</th></tr></thead>
      <tbody>
      <?php foreach ($INTERFACES as $k => $v) {
        $sp = isset($SUPPORT[$k]) ? $SUPPORT[$k] : array(1,1,1,1); ?>
        <tr>
          <td><code><?php echo h($k); ?></code></td>
          <td><?php echo h($v['title']); ?></td>
          <?php for ($i = 0; $i < 4; $i++) {
            if ($sp[$i] === 1)      { echo '<td class="c"><span class="pill y">支持</span></td>'; }
            elseif ($sp[$i] === 2)  { echo '<td class="c"><span class="pill w">待验证</span></td>'; }
            else                    { echo '<td class="c"><span class="pill n">不支持</span></td>'; }
          } ?>
        </tr>
      <?php } ?>
      </tbody>
    </table></div>
  </div>

  <div class="sec-t">统一歌曲对象</div>
  <div class="card out">
    <p class="sub">数据类接口（search / song / album / artist / playlist）返回数组的每一项结构如下。</p>
    <div class="tw"><table>
      <thead><tr><th>字段</th><th>类型</th><th>说明</th></tr></thead>
      <tbody>
        <tr><td><code>id</code></td><td>string / number</td><td>歌曲 ID</td></tr>
        <tr><td><code>name</code></td><td>string</td><td>歌曲名</td></tr>
        <tr><td><code>artist</code></td><td>array</td><td>歌手名数组</td></tr>
        <tr><td><code>album</code></td><td>string</td><td>专辑名</td></tr>
        <tr><td><code>pic_id</code></td><td>string / number</td><td>封面 ID，供 type=pic 使用</td></tr>
        <tr><td><code>url_id</code></td><td>string / number</td><td>直链 ID，供 type=url 使用</td></tr>
        <tr><td><code>lyric_id</code></td><td>string / number</td><td>歌词 ID，供 type=lyric 使用</td></tr>
        <tr><td><code>source</code></td><td>string</td><td>来源平台标识</td></tr>
      </tbody>
    </table></div>
    <p class="sub" style="margin-top:8px">酷狗的 artist 由文件名按「 - 」拆分后再按「、」切分，个别歌曲可能拆分不准。</p>
  </div>

  <div class="sec-t">通用调用</div>
  <div class="card out">
    <div class="tw"><table>
      <thead><tr><th>参数</th><th>必填</th><th>默认</th><th>说明</th></tr></thead>
      <tbody>
        <tr><td><code>server</code></td><td>否</td><td>netease</td><td>平台，见上表</td></tr>
        <tr><td><code>type</code></td><td>是</td><td>—</td><td>接口类型</td></tr>
        <tr><td><code>id</code></td><td>视接口</td><td>—</td><td>资源 ID，搜索时为关键词</td></tr>
        <tr><td><code>page</code></td><td>否</td><td>1</td><td>页码，仅 search</td></tr>
        <tr><td><code>limit</code></td><td>否</td><td>30</td><td>每页条数，search / artist</td></tr>
        <tr><td><code>br</code></td><td>否</td><td>320</td><td>音质 kbps，仅 url</td></tr>
        <tr><td><code>size</code></td><td>否</td><td>400</td><td>封面边长像素，仅 pic</td></tr>
      </tbody>
    </table></div>
    <hr class="sep">
    <h3>返回约定</h3>
    <p class="sub">数据类接口返回 JSON 数组，无结果时为 <code>[]</code>；url / lyric / pic 返回 JSON 对象。响应统一使用未转义 Unicode，中文不做转义。</p>
  </div>

  <div class="sec-t">接口详情</div>
  <?php foreach ($INTERFACES as $k => $v) { ?>
  <div class="card out">
    <h2><code style="font-size:14px"><?php echo h($k); ?></code> · <?php echo h($v['title']); ?></h2>
    <p class="sub"><?php echo h($v['summary']); ?></p>
    <h3>请求参数</h3>
    <div class="tw"><table>
      <thead><tr><th>参数</th><th>类型</th><th>默认</th><th>说明</th></tr></thead>
      <tbody>
      <?php foreach ($v['params'] as $p) { ?>
        <tr><td><code><?php echo h($p['name']); ?></code></td><td><?php echo h($p['type']); ?></td><td><?php echo h($p['default'] === '' ? '—' : $p['default']); ?></td><td><?php echo h($p['desc']); ?></td></tr>
      <?php } ?>
      </tbody>
    </table></div>
    <h3>返回示例</h3>
    <pre class="j"><?php echo h($v['response']); ?></pre>
    <p class="sub" style="margin-top:10px"><?php echo h($v['note']); ?></p>
    <div class="acts"><button class="btn x" data-try="<?php echo h($k); ?>">在测试台打开</button></div>
  </div>
  <?php } ?>

  <div class="sec-t">运行与部署</div>
  <div class="card out">
    <p class="sub">运行环境 PHP 5.4 及以上，需开启 BCMath、cURL、OpenSSL 三个扩展，网易云加密与 QQ vkey 换取依赖它们。网络层单次请求超时 15 秒、连接超时 10 秒，失败自动重试最多 3 次。</p>
    <p class="sub" style="margin-top:10px">各平台请求头内置了固定的 Cookie、User-Agent 与 Referer，例如酷我的 kw_token，上游失效时需更新源码中的请求头配置。</p>
    <p class="sub" style="margin-top:10px">源码不含鉴权、频控与缓存逻辑，对外提供服务前请在网关层自行补充，避免上游封禁。</p>
  </div>

  <div class="sec-t">跨域说明</div>
  <div class="card out">
    <p class="sub">本页与接口同源，可直接调用。若把接口用于其它域名下的前端，需要为 api/index.php 增加响应头：</p>
    <pre class="j">header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }</pre>
  </div>

  <p class="foot">MU 音乐 API · 文档与测试台</p>
</section>

<!-- ===================== 测试台 ===================== -->
<section id="p-lab" class="hide">

  <div class="card out">
    <h2>在线测试</h2>
    <p class="sub">选择平台与接口类型，填入参数后发送请求，下方会展示状态码、耗时与格式化响应。</p>
    <div class="f" style="margin-top:14px">
      <input id="apiBase" type="text" placeholder=" " value="<?php echo h($apiDefault); ?>">
      <label for="apiBase">接口地址</label>
      <div class="hint">默认按当前部署位置自动推导，可手动修改</div>
    </div>
  </div>

  <div class="card out">
    <h2>平台</h2>
    <div class="chips" id="chips">
      <?php foreach ($SERVERS as $i => $s) { ?>
        <button class="chip" data-sv="<?php echo h($s['value']); ?>" aria-pressed="<?php echo $i === 0 ? 'true' : 'false'; ?>"><?php echo h($s['label']); ?></button>
      <?php } ?>
    </div>

    <div class="f" style="margin-top:16px">
      <select id="type">
        <?php foreach ($INTERFACES as $k => $v) { ?>
          <option value="<?php echo h($k); ?>"><?php echo h($k); ?> — <?php echo h($v['title']); ?></option>
        <?php } ?>
      </select>
      <label for="type">接口类型</label>
      <svg class="i arw" style="width:20px;height:20px"><use href="#i-drop"/></svg>
    </div>

    <div id="fields"></div>

    <div class="acts">
      <button class="btn f" id="go"><svg class="i" style="width:18px;height:18px"><use href="#i-send"/></svg>发送请求</button>
      <button class="btn o" id="clr">重置参数</button>
    </div>
  </div>

  <div class="card out hide" id="res">
    <h2>响应结果</h2>
    <div class="meta" id="meta"></div>
    <div class="urlbox">
      <span id="rurl"></span>
      <button class="mini" id="cpurl" style="flex:none">复制</button>
    </div>
    <div id="slist"></div>
    <pre class="j" id="body" style="margin-top:10px"></pre>
  </div>

  <p class="foot">响应仅供参考，实际可用性取决于上游平台状态</p>
</section>

</div>

<div class="sn" id="sn"><span id="snt"></span><button id="sna">知道了</button></div>

<script>
var ITF  = <?php echo $JS_INTERFACES ? $JS_INTERFACES : '{}'; ?>;
var SUPP = <?php echo $JS_SUPPORT ? $JS_SUPPORT : '{}'; ?>;

/* ---------- 主题 ---------- */
(function(){
  var ico = document.querySelector('#theme use');
  function cur(){
    var a = document.documentElement.getAttribute('data-theme');
    if (a) return a;
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  function paint(){
    var d = cur() === 'dark';
    ico.setAttribute('href', d ? '#i-sun' : '#i-moon');
  }
  var st = null;
  try { st = localStorage.getItem('mu-theme'); } catch (e) {}
  if (st) document.documentElement.setAttribute('data-theme', st);
  paint();
  document.getElementById('theme').onclick = function(){
    var n = cur() === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', n);
    try { localStorage.setItem('mu-theme', n); } catch (e) {}
    paint();
  };
})();

/* ---------- Tab 切换 ---------- */
var tabs = document.querySelectorAll('.tab');
tabs.forEach(function(t){
  t.onclick = function(){
    tabs.forEach(function(x){
      x.setAttribute('aria-selected', x === t ? 'true' : 'false');
    });
    var p = t.getAttribute('data-p');
    document.getElementById('p-doc').classList.toggle('hide', p !== 'doc');
    document.getElementById('p-lab').classList.toggle('hide', p !== 'lab');
    window.scrollTo(0, 0);
  };
});
function goLab(){
  tabs.forEach(function(x){ x.setAttribute('aria-selected', x.getAttribute('data-p') === 'lab' ? 'true' : 'false'); });
  document.getElementById('p-doc').classList.add('hide');
  document.getElementById('p-lab').classList.remove('hide');
  window.scrollTo(0, 0);
}

/* ---------- Snackbar ---------- */
var snT;
function snack(m){
  document.getElementById('snt').textContent = m;
  document.getElementById('sn').classList.add('show');
  clearTimeout(snT);
  snT = setTimeout(function(){ document.getElementById('sn').classList.remove('show'); }, 2600);
}
document.getElementById('sna').onclick = function(){
  document.getElementById('sn').classList.remove('show');
};

/* ---------- 平台 chips ---------- */
var server = 'netease';
var chips = document.querySelectorAll('#chips .chip');
chips.forEach(function(c){
  c.onclick = function(){
    chips.forEach(function(x){ x.setAttribute('aria-pressed', 'false'); });
    c.setAttribute('aria-pressed', 'true');
    server = c.getAttribute('data-sv');
  };
});
function setServer(v){
  chips.forEach(function(x){ x.setAttribute('aria-pressed', x.getAttribute('data-sv') === v ? 'true' : 'false'); });
  server = v;
}

/* ---------- 参数表单 ---------- */
var selType = document.getElementById('type');
var fieldBox = document.getElementById('fields');

function buildFields(t, values){
  var it = ITF[t]; if (!it) return;
  fieldBox.innerHTML = '';
  it.params.forEach(function(p){
    var wrap = document.createElement('div');
    wrap.className = 'f';
    var val = (values && values[p.name] !== undefined) ? values[p.name] : p.default;
    var lab = '<label for="q_' + p.name + '">' + p.label + '</label>';
    var inp;
    if (p.name === 'id' && (t === 'search')) {
      inp = '<input id="q_id" type="text" placeholder=" " value="' + String(val).replace(/"/g, '&quot;') + '">';
    } else if (p.type === 'number') {
      inp = '<input id="q_' + p.name + '" type="number" inputmode="numeric" placeholder=" " value="' + String(val).replace(/"/g, '&quot;') + '">';
    } else {
      inp = '<input id="q_' + p.name + '" type="text" placeholder=" " value="' + String(val).replace(/"/g, '&quot;') + '">';
    }
    wrap.innerHTML = inp + lab + (p.desc ? '<div class="hint">' + p.desc + '</div>' : '');
    fieldBox.appendChild(wrap);
  });
}

function curType(){ return selType.value; }
function collectVals(){
  var v = {};
  var it = ITF[curType()];
  if (it) it.params.forEach(function(p){
    var el = document.getElementById('q_' + p.name);
    if (el) v[p.name] = el.value;
  });
  return v;
}

selType.onchange = function(){ buildFields(curType(), null); };
buildFields(curType(), null);

document.getElementById('clr').onclick = function(){ buildFields(curType(), null); };

/* ---------- 文档里的「在测试台打开」 ---------- */
document.querySelectorAll('[data-try]').forEach(function(b){
  b.onclick = function(){
    var t = b.getAttribute('data-try');
    selType.value = t;
    buildFields(t, ITF[t].sample);
    goLab();
  };
});

/* ---------- 发送请求 ---------- */
function esc(s){
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
function hl(txt){
  var s = esc(txt);
  s = s.replace(/("(?:\\.|[^"\\])*")(\s*:)?/g, function(m, a, b){
    return '<span class="' + (b ? 'k' : 's') + '">' + a + '</span>' + (b ? '<span class="z">' + b + '</span>' : '');
  });
  s = s.replace(/\b(true|false|null)\b/g, '<span class="b">$1</span>');
  s = s.replace(/(:\s*)(-?\d+\.?\d*)/g, '$1<span class="n">$2</span>');
  return s;
}

var resBox = document.getElementById('res'),
    metaBox = document.getElementById('meta'),
    bodyBox = document.getElementById('body'),
    urlBox = document.getElementById('rurl'),
    slist = document.getElementById('slist');

function badge(txt, cls){
  return '<span class="tag ' + (cls || '') + '">' + txt + '</span>';
}

function renderList(arr){
  if (!Array.isArray(arr) || !arr.length || !arr[0] || typeof arr[0] !== 'object' || !arr[0].name) {
    slist.innerHTML = ''; return;
  }
  var html = '';
  arr.slice(0, 50).forEach(function(o, i){
    var ar = Array.isArray(o.artist) ? o.artist.join(' / ') : (o.artist || '');
    html += '<div class="si">'
      + '<svg class="i" style="width:20px;height:20px;color:var(--p);flex:none"><use href="#i-note"/></svg>'
      + '<div class="tx"><div class="nm">' + esc(o.name) + '</div>'
      + '<div class="ar">' + esc(ar) + (o.album ? ' · ' + esc(o.album) : '') + '</div></div>'
      + '<span class="src">' + esc(o.source || '') + '</span>'
      + '<div class="op">'
      + '<button class="mini" data-act="url"   data-v="' + esc(o.url_id   || o.id) + '">直链</button>'
      + '<button class="mini" data-act="lyric" data-v="' + esc(o.lyric_id || o.id) + '">歌词</button>'
      + '<button class="mini" data-act="pic"   data-v="' + esc(o.pic_id   || o.id) + '">封面</button>'
      + '</div></div>';
  });
  slist.innerHTML = html;
  slist.querySelectorAll('[data-act]').forEach(function(b){
    b.onclick = function(){
      selType.value = b.getAttribute('data-act');
      var v = {}; v.id = b.getAttribute('data-v');
      buildFields(selType.value, v);
      send();
      window.scrollTo(0, 0);
    };
  });
}

function send(){
  var base = document.getElementById('apiBase').value.trim();
  if (!base) { snack('请填写接口地址'); return; }
  var t = curType();
  var q = new URLSearchParams();
  q.set('server', server);
  q.set('type', t);
  var vals = collectVals();
  ITF[t].params.forEach(function(p){
    var v = vals[p.name];
    if (v !== undefined && v !== '') q.set(p.name, v);
  });
  var url = base + (base.indexOf('?') >= 0 ? '&' : '?') + q.toString();

  urlBox.textContent = url;
  resBox.classList.remove('hide');
  metaBox.innerHTML = badge('请求中');
  bodyBox.textContent = '';
  slist.innerHTML = '';
  document.getElementById('go').disabled = true;

  var t0 = Date.now();
  fetch(url, { method: 'GET', headers: { 'Accept': 'application/json' }, cache: 'no-store' })
    .then(function(r){
      var ms = Date.now() - t0;
      var st = r.status;
      return r.text().then(function(tx){
        metaBox.innerHTML = badge('HTTP ' + st, st >= 200 && st < 300 ? 'ok' : 'bad')
          + badge(ms + ' ms') + badge(tx.length + ' 字节');
        var parsed = null, isJson = false;
        try { parsed = JSON.parse(tx); isJson = true; } catch (e) {}
        if (isJson) {
          bodyBox.innerHTML = hl(JSON.stringify(parsed, null, 2));
          renderList(parsed);
          if (Array.isArray(parsed) && parsed.length === 0) {
            metaBox.innerHTML += badge('空结果', 'bad');
          }
        } else {
          bodyBox.innerHTML = hl(tx || '（响应体为空）');
          if (!tx) metaBox.innerHTML += badge('空响应', 'bad');
        }
      });
    })
    .catch(function(e){
      metaBox.innerHTML = badge('请求失败', 'bad');
      bodyBox.innerHTML = hl(String(e && e.message ? e.message : e)
        + '\n\n可能原因：接口地址不可达、被网关拦截，或跨域未放行。');
    })
    .then(function(){ document.getElementById('go').disabled = false; });
}

document.getElementById('go').onclick = send;

document.getElementById('cpurl').onclick = function(){
  var txt = urlBox.textContent;
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(txt).then(function(){ snack('地址已复制'); }, function(){ snack('复制失败'); });
  } else {
    snack('当前环境不支持自动复制');
  }
};

/* ---------- App bar 阴影 ---------- */
var bar = document.getElementById('bar');
window.addEventListener('scroll', function(){
  bar.classList.toggle('s', window.scrollY > 4);
}, { passive: true });
</script>
</body>
</html>
