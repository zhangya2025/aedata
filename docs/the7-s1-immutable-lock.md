# The7 S1 Immutable Lock

## 目标与范围
- 将站点锁定为“不可变部署”：后台用户无法通过 UI/URL 安装/更新/删除插件与主题，无法访问主题/插件文件编辑器。
- 禁用 The7 推荐插件（TGMPA）、Demo/向导/模板下载与写文件通道，阻断 The7 远程 API（repo.the7.io / my.the7.io / themeforest.net）。
- 冻结全站 plugins/themes/core 更新检查、计划任务与外联，保留现有前台与后台编辑/发布能力（字体允许回退系统字体）。
- 所有治理均在 MU 插件层完成，移除或停用 MU 插件即可回滚。

## 配置文件（wp-content/mu-plugins/wla-config.php）
默认关键开关（仅列与 S1 相关）：

```php
return [
  'immutable_lock' => true,     // S1 总开关
  'lock_caps' => true,          // 移除 update/install/delete/edit_* 能力
  'lock_file_mods' => true,     // 定义 DISALLOW_FILE_MODS / DISALLOW_FILE_EDIT
  'disable_wp_update_cron' => true, // 清理 wp_update_* / wp_version_check 计划任务
  'disable_the7_tgmpa' => true, // 禁用 The7 TGMPA 推荐插件机制
  'disable_the7_wizard' => true,// 禁用 The7 Demo/向导下载与写入
  'freeze_all_updates' => true, // 全站插件更新冻结（保留插件页无黄条）
  'freeze_themes'  => true,
  'freeze_core'    => true,
  'block_remote'   => true,
  'deny_hosts'     => [
    'repo.the7.io', 'my.the7.io', 'themeforest.net',
    'api.wordpress.org', 'downloads.wordpress.org', 'plugins.svn.wordpress.org',
    'github.com', 'api.github.com', 'raw.githubusercontent.com',
    'assets.elementor.com', 'my.elementor.com', 'fonts.googleapis.com', 'fonts.gstatic.com',
  ],
  'allow_hosts' => [],
];
```

调整方式：直接编辑数组值保存即可，无需改 wp-config.php；若需临时恢复更新/安装，可将对应键设为 `false`。

## 行为与锁死点
- **常量级锁**：`immutable_lock` + `lock_file_mods` 时在 MU 阶段定义 `DISALLOW_FILE_MODS` 与 `DISALLOW_FILE_EDIT`，彻底禁用 WP 升级器及文件编辑器。
- **能力锁**：`user_has_cap`/`map_meta_cap` 过滤器强制 `update_*`、`install_*`、`delete_*`、`edit_*` 能力为 false，访问 update-core.php / plugin-editor.php / theme-editor.php 将提示无权限。
- **更新冻结**：
  - 插件/主题：`pre_set_site_transient_update_plugins/themes` 与对应 `site_transient_*` 清空 `response`，填充已安装版本到 `checked/no_update`，全站不再显示更新提示（含 plugins.php/themes.php）。
  - 核心：`pre_site_transient_update_core`/`site_transient_update_core` 返回空 offers，并通过 `allow_major/minor_auto_core_updates` 与 `auto_update_core` 全部返回 false。
  - Cron：`wp_clear_scheduled_hook` 清理 `wp_update_plugins/wp_update_themes/wp_version_check`（受 `disable_wp_update_cron` 控制）。
- **外联阻断与回填**：`pre_http_request` 对 deny_hosts 命中短路；
  - `api.wordpress.org`：返回 200 空结构，避免“未知错误”提示。
  - `repo.the7.io`：`info/list` 等 JSON 返回 `{}`/`[]` 占位；下载/zip 请求返回阻断错误。
  - `downloads.wordpress.org`、`github.com` 系列：返回阻断错误，防止下载包。
  - 字体/其它：fonts.googleapis.com/gstatic 移除标签并阻断请求，允许系统字体回退。
- **The7 专项禁用**（仅在 dt-the7 模板激活时）：
  - TGMPA：过滤 `presscore_tgmpa_module_plugins_list` 为 `[]`，移除 `Presscore_Modules_TGMPAModule::register_plugins_action` 和其更新过滤器，兜底 `remove_all_actions( 'tgmpa_register' )`。
  - Demo/向导：移除 `the7_demo_content` 相关 admin/AJAX 钩子（导入/删除/keep/状态检查）、Meta Box 及菜单注册，`the7_demo_content_list` 返回空数组。
  - 菜单兜底：`admin_menu` 阶段移除 `the7-dashboard` 及其 plugins/demo 子菜单。

## 被阻断域名与影响
- repo.the7.io / my.the7.io / themeforest.net：注册/更新/插件列表/Demo/模板下载全部停用；后台可能显示注册不可用，但不阻塞页面。
- api.wordpress.org / downloads.wordpress.org / plugins.svn.wordpress.org：WP 更新检查与下载通道停用；不会再出现更新黄条。
- github.com / api.github.com / raw.githubusercontent.com：任何从 GitHub 拉包的更新链路停用。
- fonts.googleapis.com / fonts.gstatic.com：远程字体不再加载，页面回退系统字体。

## 验收清单（至少执行以下 12 项）
1. 访问 `/wp-admin/update-core.php` 显示无权限或无更新。
2. 打开 `plugins.php`：无“有新版本可用”黄条，更新/安装/删除操作不可用。
3. 打开 `themes.php`：无更新提示，无法删除/安装主题。
4. 访问 `/wp-admin/plugin-editor.php` 与 `/wp-admin/theme-editor.php`：提示被禁用/无权限。
5. 浏览器网络面板无 `api.wordpress.org` / `downloads.wordpress.org` / `repo.the7.io` / `github.com` 等请求。
6. The7 “Recommended Plugins”/TGMPA 页面无法列出或执行安装更新。
7. The7 Demo/向导页面无法列出/导入 Demo，相关 AJAX 请求被拦截。
8. 站点健康（Site Health）与 The7 检查不会因 repo.the7.io 超时而卡住。
9. Elementor 编辑器可打开并保存页面（字体回退系统字体可接受）。
10. 前台页面正常加载，无致命报错，外联被阻断。
11. `wp cron event list`（若可用）不包含 `wp_update_plugins/wp_update_themes/wp_version_check`。
12. 若开启日志（log=true），`wp-content/uploads/wla-logs/elementor-static-guard.log` 记录阻断命中。

## 回滚方式
- 配置回滚：在 `wla-config.php` 将 `immutable_lock`（或相关开关）设为 `false` 保存。
- 快速禁用：
```
mv wp-content/mu-plugins/wla-elementor-static-guard.php wp-content/mu-plugins/wla-elementor-static-guard.disabled
```
- 恢复后重新访问后台即可恢复默认更新/安装行为。
