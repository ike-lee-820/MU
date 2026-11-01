# MU 音乐 API

聚合网易云、QQ 音乐、酷狗、酷我四大平台的单文件 PHP 音乐接口，附带接口文档与在线测试台。

## 目录结构

```
.
├── api/
│   ├── Meting.php    类库，抓取逻辑，保持上游原样不做改动
│   ├── index.php     HTTP 路由入口，只处理 HTTP 语义
│   ├── home.php      主页面，接口文档 + 在线测试台
│   └── ping.php      健康检查与连通性诊断（可删）
├── vercel.json
└── README.md
```

## 设计原则

**关注点分离。** `Meting.php` 只管怎么从上游抓数据，`index.php` 只管怎么把 HTTP 请求翻译成类方法调用。上游修复了某个平台的接口，直接覆盖 `Meting.php` 即可，路由层一行不用动。

**任何失败都有结构化输出。** 空响应是最难排查的问题，所以路由层把「空字符串」和「空数组」区分开：前者是上游失败，返回 502 并给出排查提示；后者是搜索无匹配，属于正常结果返回 200。

**配置不进代码。** 代理、跨域白名单、缓存时长、调试开关全部走环境变量，换环境不用改代码。

## 部署

### Vercel

```json
{
  "functions": {
    "api/**/*.php": {
      "runtime": "vercel-php@0.9.0"
    }
  },
  "rewrites": [
    { "source": "/", "destination": "/api/home.php" }
  ]
}
```

注意主页面必须放在 `api/` 目录下。vercel-php 只对 `api/` 内的 PHP 生效，根目录的 PHP 会被当成静态文件下载。

部署后在后台配置环境变量：

| 变量 | 默认值 | 说明 |
|---|---|---|
| `MU_DEBUG` | 空 | 设为 `1` 时错误响应附带文件名、行号与上游原始片段 |
| `MU_CORS_ORIGIN` | `*` | 跨域白名单，多域名用逗号分隔 |
| `MU_PROXY` | 空 | 出网代理，格式 `http://host:port` |
| `MU_CACHE_TTL` | `0` | 响应缓存秒数，0 为关闭 |

### 传统服务器

把整个目录丢到 Web 根目录，确保 PHP 5.4 以上并开启 BCMath、cURL、OpenSSL 三个扩展。Nginx 需要把 `/` 指向 `api/home.php`，或直接在 `api/` 下建软链。

## 验证

按顺序执行，每一步失败都能定位到具体环节。

```bash
# 1. PHP 有没有被执行，出口 IP 在哪，上游通不通
curl -sS "https://你的域名/api/ping.php"

# 2. 路由层有没有生效（应返回参数校验的 JSON 错误，而不是空白）
curl -sS "https://你的域名/api/index.php"

# 3. 用 ASCII 关键词隔离编码问题
curl -sS "https://你的域名/api/index.php?server=netease&type=search&id=hello&limit=5"

# 4. 中文关键词，交给 curl 编码
curl -sS -G "https://你的域名/api/index.php" \
  --data-urlencode "server=netease" \
  --data-urlencode "type=search" \
  --data-urlencode "id=把回忆拼好给你" \
  --data-urlencode "limit=5"
```

第 2 步如果返回空白，说明 PHP 没有被执行，检查 `vercel.json` 的 functions 匹配与部署日志。
第 3 步如果返回 502 `upstream_empty`，说明路由正常但上游拿不到数据，看第 1 步的出口 IP 是否在国内，必要时配 `MU_PROXY`。

## 接口

统一入口 `api/index.php`，参数如下。

| 参数 | 必填 | 默认 | 说明 |
|---|---|---|---|
| `server` | 否 | netease | netease / tencent / kugou / kuwo |
| `type` | 是 | — | search / song / album / artist / playlist / url / lyric / pic |
| `id` | 视接口 | — | 资源 ID，search 时为关键词 |
| `page` | 否 | 1 | 页码，仅 search |
| `limit` | 否 | 30 | 条数，search 与 artist |
| `br` | 否 | 320 | 码率 kbps，仅 url |
| `size` | 否 | 400 | 封面边长像素，仅 pic |

成功响应统一信封：

```json
{
  "ok": true,
  "data": [],
  "meta": { "server": "netease", "type": "search", "cached": false }
}
```

失败响应：

```json
{
  "ok": false,
  "error": { "code": "upstream_empty", "message": "上游返回空响应" }
}
```

错误码：`missing_type`、`unknown_type`、`missing_id`、`empty_keyword`、`invalid_param` 为 400/404；`upstream_empty`、`upstream_invalid` 为 502；`exception`、`fatal_error`、`library_missing` 为 500。

## 已知限制

`artist` 接口在源码中仅网易云分支正确，腾讯与酷狗分支误用了酷狗的歌手接口地址，非网易云场景建议改用 `search`。`playlist` 的酷狗与酷我分支未经充分验证。

源码不含鉴权与频控，对外提供服务前请在网关层补充，避免上游封禁。
