# WLA Site Stability Lock (S1-lite)

站点稳定锁用于防止后台误操作导致主题/插件/核心被升级、安装、删除或通过在线编辑器被修改，同时阻断更新相关的外联与计划任务。支持按需开启“删除窗口”以临时允许删除插件/主题。

## 功能
- 启用后默认禁止后台安装、更新、删除插件与主题
- 禁止后台主题/插件文件编辑器（即便开启删除窗口也保持禁用）
- 将插件/主题/核心更新提示冻结为“无更新”
- 关闭更新相关的 cron 钩子（wp_update_plugins/wp_update_themes/wp_version_check）
- 仅阻断更新相关域名（api.wordpress.org 等），避免误伤业务外联
- 可选日志输出到 `wp-content/uploads/wla-logs/site-stability-lock.log`

## 配置
编辑 `wp-content/mu-plugins/wla-site-config.php`：
```php
return [
  'enable' => true,
  'log' => false,
  'lock_file_mods' => true,
  'lock_caps' => true,
  'freeze_updates' => true,
  'disable_update_cron' => true,
  'block_update_hosts' => true,
  'update_deny_hosts' => [
    'api.wordpress.org',
    'downloads.wordpress.org',
    'plugins.svn.wordpress.org',
    'github.com',
    'api.github.com',
    'raw.githubusercontent.com',
  ],
  'allow_delete' => false,
  'allow_delete_until' => 0,
];
```

### 删除窗口（仅放开删除，不放开安装/更新）
- 将 `allow_delete` 设为 `true`，后台可删除插件/主题，其它安装/更新/编辑仍被禁止
- 或设置 `allow_delete_until` 为未来的 Unix 时间戳（秒），临时放行删除，时间到期后自动恢复锁定
- 如果 `wp-config.php` 里预先定义了 `DISALLOW_FILE_MODS`，则无法通过配置放开删除（常量不可撤销），请在启用删除前确认未在 wp-config.php 设置

## 回滚方法
1. 将配置中的 `enable` 改为 `false`（保存即可生效）
2. 或将 `wp-content/mu-plugins/wla-site-stability-lock.php` 改名为 `.disabled`（或删除）

## 验收清单
- plugins.php 不出现插件更新提示/黄条
- themes.php 不出现主题更新提示
- update-core.php 显示“暂无更新”
- 插件/主题自动更新开关不可用
- 插件安装、主题安装入口被禁用（提示权限不足）
- 插件删除、主题删除操作被拒绝（除非开启删除窗口）
- 插件编辑器、主题编辑器页面不可用
- wp-cron 不再调度 `wp_update_plugins`/`wp_update_themes`/`wp_version_check`
- 更新相关域名请求被短路（日志可选）
- 删除/改名 MU 文件即可立即恢复原行为
