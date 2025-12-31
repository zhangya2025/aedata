# Aegis Page Cache (MU Plugin)

## 目标
降低匿名访问的 TTFB，尤其是首页、分类页、商品详情页，通过 MU 插件在极早期直接返回静态 HTML 文件，绕过 Elementor / Elementor Pro / The7 初始化链路。

## 启用条件与范围
- 默认开启：`AEGIS_PAGE_CACHE_ENABLE` 未显式设为 `false` 时启用。
- 仅缓存 **GET/HEAD** 请求，且需要满足以下全部条件：
  - 非后台：不匹配 `/wp-admin`、`/wp-login`、`is_admin()`。
  - 非接口：不包含 `/wp-json`、`/xmlrpc.php`。
  - 无查询字符串（`?`）。
  - 无登录态或受保护内容 cookie：`wordpress_logged_in_*`、`wp-postpass_*`。
  - 无 Woo 购物相关 cookie：`woocommerce_items_in_cart`、`woocommerce_cart_hash`、`wp_woocommerce_session_*`。
  - 路径不在 Woo 关键流程：`/cart`、`/checkout`、`/my-account` 及对应 `index.php/` 形式。
- 仅缓存 `status=200` 且 `Content-Type: text/html` 的响应。

## 缓存存储
- 目录：`wp-content/cache/aegis-page-cache/`
- 命中 Key：`md5(HTTP_HOST . REQUEST_URI)`，后缀 `.html`
- TTL：默认 60 秒，可用常量 `AEGIS_PAGE_CACHE_TTL` 自定义。
- 命中时头部：`X-Aegis-Page-Cache: HIT / MISS / BYPASS`

## 失效策略（全清）
以下事件触发清空缓存目录：`save_post`、`deleted_post`、`edit_terms`、`wp_update_nav_menu`、`switch_theme`、`activated_plugin`、`deactivated_plugin`。

## 回滚方式
- 最快：删除 `wp-content/mu-plugins/aegis-page-cache.php`。
- 或在 `wp-config.php` 中定义 `AEGIS_PAGE_CACHE_ENABLE` 为 `false`。

## 验证步骤
1) 命中验证（首页示例）：
   - `curl -I https://www.aegismax.com/` → 第一次应看到 `X-Aegis-Page-Cache: MISS` 或 `BYPASS`。
   - 再次执行相同命令 → 应看到 `X-Aegis-Page-Cache: HIT`。
2) timing.json 验证（需要服务器现有采集）：
   - 在出现 `HIT` 时查看 `/www/wwwlogs/www.aegismax.com.timing.json`，记录 `/` 的 `upstream_response_time`，应相比缓存前的 ~3.5–4.3s 显著下降（接近 0）。
   - 同样采样 `/wp-json`，预期从 ~2.1–2.6s 下降。
   - 建议各路径至少取 3 次样本求均值并写入运维记录。
3) Woo 关键路径必须 BYPASS：
   - `curl -I https://www.aegismax.com/cart`、`/checkout`、`/my-account` → 头部应为 `X-Aegis-Page-Cache: BYPASS`。
4) 登录态 BYPASS：
   - 登录后携带 `wordpress_logged_in_*` cookie 访问任意前台页，头部应为 `X-Aegis-Page-Cache: BYPASS`，确保账号、购物流程不被缓存。

## 注意事项
- 仅缓存 HTML，静态资源由现有 Web 服务器/对象缓存处理。
- HIT 时 MU 插件会在极早期直接输出并 `exit`，确保绕过其它插件/主题逻辑；MISS/BYPASS 不改变正常加载流程。
