# The7 模块瘦身（MU 插件）

## 背景与目的
- Nginx timing 日志显示：首页 `upstream_response_time≈3.5s`，`/wp-json/` `upstream_response_time≈2.47s`，静态资源响应很快，瓶颈在 PHP 初始化阶段。
- PHP-FPM slowlog（1s 阈值）在前台请求中记录到 Elementor/Elementor Pro 初始化链路，以及 The7 `demo-content`/`dev-mode` 相关模块加载，属于生产前台不需要的开销。
- 通过 `the7_active_modules` 过滤器在前台/REST 请求中移除上述非必要模块，减少 include 与初始化成本，目标降低首页和 `/wp-json/` 的 TTFB。

## 实现范围
- 仅针对前台和 REST（含 `/wp-json/`）请求生效；`is_admin()`（后台）不做任何修改。
- 移除的模块：`demo-content`、`bundled-content`、`dev-mode`、`dev-tools`。
- The7 父主题和其他插件代码未改动，逻辑封装在 MU 插件 `wp-content/mu-plugins/aegis-perf-the7-modules.php` 中。

## 验证与观测
1. 部署后运行 3 次取均值（示例命令，可结合现有 timing 日志）：
   - 首页：`curl -s -o /dev/null -w "%{time_starttransfer}" https://www.aegismax.com/`，随后 `tail /www/wwwlogs/www.aegismax.com.timing.json | jq '.upstream_response_time'` 校对。
   - REST：`curl -s -o /dev/null -w "%{time_starttransfer}" https://www.aegismax.com/index.php/wp-json/`，同样查看 timing 日志均值。
2. 样本记录（在可联网服务器执行后填写）：
   | 场景 | 部署前均值 (s) | 部署后均值 (s) | 备注 |
   | --- | --- | --- | --- |
   | 首页 `/` | 3.50 | _待填写_ | 取 timing 日志最近 3 条均值 |
   | `/wp-json/` | 2.47 | _待填写_ | 同上 |

> 说明：本地容器无法访问外网，未直接获取“部署后”样本，请在生产环境执行上述命令后补全表格。

## 回滚方式
- 将 `wp-content/mu-plugins/aegis-perf-the7-modules.php` 中的常量 `AEGIS_THE7_FRONT_MODULE_SLIM` 设为 `false`，即可恢复 The7 默认模块列表。
- 或直接删除/停用该 MU 插件文件，恢复原状。

## 自测清单
- 首页打开无报错、样式和交互正常。
- 产品详情页正常展示 The7 组件、图片和脚本（检查 SKU、价格、变体展示）。
- WooCommerce 购物车、结算流程可完成（支付/运费计算不受影响）。
- Elementor 前台渲染正常（若使用 Elementor 模板，确保未依赖被移除的 The7 dev/demo 模块）。
