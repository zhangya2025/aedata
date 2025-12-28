# Elementor Static Guard (WLA)

## 目标与范围
- 为现有 Elementor/Pro 安装提供“静态化/冻结”治理层，不改动插件源代码。
- 关闭所有 Elementor/Pro 更新链路、营销/通知/追踪，以及全部外部调用（默认零外联）。
- 允许牺牲云模板、AI、连接器、授权续期、营销素材等增值能力，确保现有页面可继续显示与编辑。

## 配置文件（wp-content/mu-plugins/wla-config.php）
默认配置如下，可直接修改数组值生效，无需触碰 `wp-config.php`：

```php
return [
  'enable'         => true,
  'log'            => false,
  'freeze_updates' => true,
  'silence_notices'=> true,
  'block_remote'   => true,
  'fonts_mode'     => 'system',  // system|local|allow-google
  'deny_hosts'     => [
    'assets.elementor.com',
    'my.elementor.com',
    'fonts.googleapis.com',
    'fonts.gstatic.com',
    'plugins.svn.wordpress.org',
    'github.com',
    'api.github.com',
    'raw.githubusercontent.com',
    'go.elementor.com',
  ],
  'allow_hosts'    => [],
];
```

关键说明：
- `fonts_mode`: `system`（默认）强制不输出 Google Fonts，所有 fonts.* URL 被剥离；`local` 仅在已提供本地字体时启用；`allow-google` 才允许 fonts 域名（可在 `allow_hosts` 白名单中写入）。
- `allow_hosts`: 默认空，实现“例外域名=0”；如需临时放行个别域名可在此填写。
- `log`: 打开后在 `wp-content/uploads/wla-logs/elementor-static-guard.log` 写入命中日志。

## 行为与治理点
- **冻结更新**：拦截 `pre_set_site_transient_update_plugins/site_transient_update_plugins`、`plugins_api`、`auto_update_plugin`，将 `elementor/elementor.php` 与 `pro-elements/pro-elements.php`（兼容 `elementor-pro/elementor-pro.php`）标记为“无更新”，阻断 WP.org/Beta/Canary、Pro GitHub Updater 与 my.elementor.com 下载链路。
- **静默后台/编辑器营销**：通过 `elementor/core/admin/notifications` 过滤器返回空数组；在 `plugins_loaded` 精准移除 `Elementor\Core\Admin\Admin_Notices::admin_notices` 输出；`elementor/tracker/send_override` 阻止追踪发送。
- **外联全断与空响应回填**：`pre_http_request` 对 deny_hosts 命中直接阻断；`assets.elementor.com` 返回 200 空 JSON（避免重试/报错）；其它 Elementor 云域名返回阻断错误；`http_request_args` 将相关请求超时压缩到 5 秒以内。
- **字体零外联**：`elementor/frontend/print_google_fonts` 阻止输出；`style_loader_src`/`script_loader_src` 剥离 fonts.googleapis.com / fonts.gstatic.com。

## 被阻断域名与影响
- assets.elementor.com：通知/Promotions/向导素材 → 空 JSON/缺图，不影响编辑保存。
- my.elementor.com：模板库/许可/AI/追踪 → 斩断后云模板、续费提示、AI 等不可用，已安装功能继续运行。
- fonts.googleapis.com / fonts.gstatic.com：字体回退系统字体。
- plugins.svn.wordpress.org：Beta/Canary 更新链路停用。
- github.com / api.github.com / raw.githubusercontent.com：Pro GitHub Updater 停用。
- go.elementor.com：文档/续费跳转失效（仅链接）。

## 验收清单（至少执行以下 12 项）
1. 前台首页加载成功，页面结构正常。
2. 前台 Elementor 页面字体回退可接受，无致命错误。
3. 浏览器控制台无 Elementor 相关致命报错。
4. 后台插件页不再提示 Elementor/Pro 更新。
5. 后台 Elementor 设置页无营销/upsell 通知。
6. Elementor 编辑器可打开已有页面。
7. 编辑器内保存/更新页面可成功。
8. 编辑器未弹出云模板/AI/续费等远程提示。
9. 服务器网络监控确认无 assets.elementor.com 请求。
10. 网络监控确认无 my.elementor.com 请求。
11. 网络监控确认无 fonts.googleapis.com/fonts.gstatic.com 请求。
12. 若打开日志（`log=true`），`wp-content/uploads/wla-logs/elementor-static-guard.log` 中记录命中信息。

## 回填策略说明
- assets.elementor.com 上的 JSON（通知/Promotions/实验等）直接回填空 JSON（200 响应），避免 Elementor 反复重试或阻塞后台。
- 其它被阻断域名默认返回阻断错误（若后续发现特定 endpoint 需要空结构，可在此文件补充说明并调整配置）。

## 回滚方式
执行一条命令禁用治理层（保留文件以便随时恢复）：
```
mv wp-content/mu-plugins/wla-elementor-static-guard.php wp-content/mu-plugins/wla-elementor-static-guard.disabled
```
