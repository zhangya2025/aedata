# CN 网络优化保护层（P1）

本版本通过 MU 插件 `wp-content/mu-plugins/wla-cn-guard.php` 提供可开关的降级策略，避免中国网络环境下的慢源阻塞首屏或后台。删除/改名该文件即可完全回滚。

## 功能概览

- **Google Maps 懒加载（前台）**：拦截 `maps.googleapis.com/maps/api/js` 的内联/模板输出，默认替换为“点击加载”按钮，避免首屏阻塞；可切换为关闭或彻底屏蔽。
- **Elementor 外网请求保护（后台/编辑器）**：对 `assets.elementor.com`、`my.elementor.com` 的 HTTP 请求设置超时上限与最小重定向，Mixpanel 配置成功时本地缓存，失败或跳过时用缓存/空 JSON 回填；可选跳过远程。
- **可选日志**：`define( 'WLA_CN_GUARD_LOG', true );` 时将行为写入 `wp-content/uploads/wla-logs/cn-guard.log`。
- **失败安全**：所有拦截均在失败时自动回退或以空响应结束，不改动主题或 Elementor 源码。

## 开关与模式

通过在 `wp-config.php`（或其他早期加载位置）定义常量控制，未定义时使用默认值。

| 常量 | 取值 | 默认 | 作用 |
| ---- | ---- | ---- | ---- |
| `WLA_CN_GUARD_ENABLED` | `true` / `false` | `true` | 总开关，`false` 时插件不做任何处理。 |
| `WLA_CN_GUARD_MAPS_MODE` | `off` / `lazy` / `block` | `lazy` | `lazy`：替换 Google Maps 脚本为点击后加载；`block`：直接移除，不提供按钮；`off`：不拦截。 |
| `WLA_CN_GUARD_HTTP_TIMEOUT` | 浮点秒数 | `4.0` | 命中域名请求的最大 timeout，同时将重定向上限压到 0/1。 |
| `WLA_CN_GUARD_SKIP_ELEMENTOR_REMOTE` | `true` / `false` | `false` | 是否直接跳过 `assets.elementor.com`、`my.elementor.com`，Mixpanel 会返回缓存/空 JSON；Pro 授权接口会 WP_Error（不会 fatal）。 |
| `WLA_CN_GUARD_LOG` | `true` / `false` | `false` | 开启后写入 `wp-content/uploads/wla-logs/cn-guard.log`，记录命中、缓存、跳过。 |

## 默认行为（开箱即用）

- Maps：`lazy` 模式，首屏不请求 `maps.googleapis.com`，点击按钮后再加载原始脚本。
- Elementor：默认只做 timeout+缓存保护：timeout 最长 4 秒、重定向压到 0/1；Mixpanel 成功会缓存，失败或跳过可回填缓存/空 JSON；不默认跳过远程。
- 总开关：开启。

## 验收标准

1. 默认模式下，前台首屏 Network 中不出现 `maps.googleapis.com`；点击“加载地图”后才加载，且页面无 JS 报错。
2. Elementor 编辑器/后台访问 `assets.elementor.com`、`my.elementor.com` 时，超时缩短至约 4 秒以内；Mixpanel 在失败/跳过时回填缓存或空 JSON，页面不中断。
3. 可选跳过开启时，后台不会长时间等待跨境请求；无 fatal，功能允许降级。
4. 关闭总开关或设置 `WLA_CN_GUARD_MAPS_MODE=off` / 关闭跳过时，恢复原始行为。

## 回滚方式

- 删除或改名 `wp-content/mu-plugins/wla-cn-guard.php`，所有行为即刻恢复原状。
- 或在 `wp-config.php` 中设置 `define( 'WLA_CN_GUARD_ENABLED', false );` 以临时停用。

## 运行时说明

- 仅对 text/html 输出尝试替换 Google Maps `<script>`，不存在则不做任何修改。
- Elementor 域名之外的 HTTP 请求不受影响。
- 采用 MU 插件实现，无需后台启用，保持最小侵入且易于回滚。
