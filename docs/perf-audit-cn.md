# WordPress 中国网络性能审计报告

## 1) 外部域名总览表
| 域名 | 用途 | 类型 | 触发方式 | 建议 | 优先级 | 风险说明 |
| --- | --- | --- | --- | --- | --- | --- |
| cdnjs.cloudflare.com | jquery-mousewheel 依赖（自定义滚动条） | JS | 主题脚本自动插入（无本地备份） | 本地化或改为本地备份；加失败降级 | P0 | Cloudflare 在部分地区高延迟，资源为同步插入，缺失会导致滚动体验异常【F:wp-content/themes/dt-the7/lib/custom-scrollbar/custom-scrollbar.js†L48-L60】 |
| fonts.googleapis.com / fonts.gstatic.com | Web 字体 CSS/字体 | font/css | 主题压缩器生成 Google Fonts URL；Elementor 按需下载 | P0 | 提供本地字体或可选关闭；默认改为本地托管 | 首屏阻塞；在中国常被拦截，可能导致闪烁或回退字体【F:wp-content/themes/dt-the7/inc/class-the7-web-fonts-compressor.php†L45-L56】【F:wp-content/plugins/elementor/core/files/fonts/google-font.php†L170-L195】 |
| maps.googleapis.com | Google Maps JS | JS/API | 主题元框工具直接硬编码 script | P1 | 提供本地/可选地图方案（高德/百度）或延迟加载 | 首屏阻塞，若被拦截地图不可用【F:wp-content/themes/dt-the7/inc/extensions/meta-box/inc/helpers.php†L281-L303】 |
| assets.elementor.com | 采集 Mixpanel 配置 | API/analytics | Elementor 获取编辑器事件配置 | P1 | 提供开关并缓存/本地镜像；编辑器端可关闭 | 需外网；在中国可能慢，影响编辑器加载【F:wp-content/plugins/elementor/core/common/modules/events-manager/module.php†L18-L33】 |
| my.elementor.com | Elementor Pro 许可/功能 API | API | Elementor Pro 许可与远程信息 | P1 | 增加超时与失败降级；允许本地跳过 | 影响后台许可校验，连通性差时编辑器功能受限【F:wp-content/plugins/pro-elements/license/api.php†L22-L119】 |
| www.google.com | reCAPTCHA 校验 | API | Elementor Pro 表单验证码 | P1 | 提供后端/本地验证替代或可关闭 | 在中国被阻断，导致表单提交失败【F:wp-content/plugins/pro-elements/modules/forms/classes/recaptcha-handler.php†L150-L191】 |
| graph.facebook.com | Facebook SDK 校验 | API | Elementor Pro 社交组件 | P2 | 仅在填写 App ID 时校验，可提供可选跳过 | 在中国不可达，可能阻塞保存/校验【F:wp-content/plugins/pro-elements/modules/social/classes/facebook-sdk-manager.php†L150-L178】 |

## 2) 证据与代码定位
- **cdnjs.cloudflare.com**：`custom-scrollbar.js` 若未加载 mousewheel 插件，会动态写入 CDN 脚本。【F:wp-content/themes/dt-the7/lib/custom-scrollbar/custom-scrollbar.js†L48-L60】
- **Google Fonts**（主题）：字体压缩器直接生成 `https://fonts.googleapis.com/css` 请求。【F:wp-content/themes/dt-the7/inc/class-the7-web-fonts-compressor.php†L45-L56】
- **Google Fonts**（Elementor）：获取 CSS 并解析字体文件，下载远程字体时使用 `https://fonts.googleapis.com/earlyaccess/` 等地址。【F:wp-content/plugins/elementor/core/files/fonts/google-font.php†L170-L195】
- **Google Maps**：元框地图字段硬编码 `https://maps.googleapis.com/maps/api/js?sensor=false` 脚本。【F:wp-content/themes/dt-the7/inc/extensions/meta-box/inc/helpers.php†L281-L303】
- **Elementor Mixpanel 配置**：常量 `REMOTE_MIXPANEL_CONFIG_URL` 指向 `https://assets.elementor.com/mixpanel/v1/mixpanel.json` 并在加载编辑器时请求。【F:wp-content/plugins/elementor/core/common/modules/events-manager/module.php†L18-L33】
- **Elementor 许可 API**：`BASE_URL` 为 `https://my.elementor.com/api/v2/`，后台会通过 `wp_remote_post` 访问。【F:wp-content/plugins/pro-elements/license/api.php†L22-L119】
- **reCAPTCHA**：表单提交阶段向 `https://www.google.com/recaptcha/api/siteverify` 发送校验请求；失败会阻断提交。【F:wp-content/plugins/pro-elements/modules/forms/classes/recaptcha-handler.php†L150-L191】
- **Facebook Graph**：社交组件验证 App ID 时请求 `https://graph.facebook.com/<app_id>`。【F:wp-content/plugins/pro-elements/modules/social/classes/facebook-sdk-manager.php†L150-L178】

## 3) 页面路径视角
- **首页/文章页（前台）**：The7 主题默认会加载自定义滚动条脚本（潜在引入 Cloudflare CDN mousewheel）与 Google Fonts（主题压缩器）。
- **包含地图的内容页**：使用 The7 元框地图字段时，页面会在 HTML 中插入 Google Maps JS，影响首屏加载。
- **Elementor 编辑器**：进入 Elementor 或 Elementor Pro 编辑器时，会调用 assets.elementor.com 获取事件配置；若使用 Pro 许可或实验功能，还会触发 my.elementor.com 许可校验与 Google Fonts 远程下载。
- **含表单验证码的页面**：开启 reCAPTCHA 的表单会在提交时访问 google.com，失败会直接导致表单提交错误。
- **社交登录/分享设置页**：设置 Facebook App ID 时会向 graph.facebook.com 发送校验请求，国内网络可能导致保存等待较长。

（运行时进一步核实可通过新增的 MU 插件日志，见下方行动计划。）

## 4) 结论与分阶段行动计划
- **阶段 0（P0）**：
  - 本地化/打包 jquery-mousewheel，移除对 cdnjs.cloudflare.com 的依赖；同时给滚动条脚本增加存在性检测，避免阻塞。
  - 为主题与 Elementor 提供本地字体包或切换到系统字体，默认关闭对 fonts.googleapis.com 的直连；加入回退/开关。
  - 在模板中为 Google Maps 提供占位与本地地图替代（或按需延迟加载）。

- **阶段 1（P1）**：
  - 为 Elementor 采集与许可 API（assets.elementor.com、my.elementor.com）增加超时、失败缓存与手动开关；必要时提供后端代理或静态镜像。
  - 为 reCAPTCHA 提供国内可用的验证开关/兼容方案（如滑块验证码或后端验证代理）；若不可用则允许表单绕过或回退到简单验证码。

- **阶段 2（P2）**：
  - 在构建流程中统一白名单外链，提供自动镜像/本地化脚本（包含 Google Maps、Facebook SDK 等），并在主题/插件设置中暴露“国内加速模式”开关。
  - 持续利用 MU 插件的运行时日志，收集各页面真实外链与 HTTP 请求耗时，作为优化回归验证。

## 5) 运行时观测插件
- 新增 MU 插件 `wp-content/mu-plugins/wla-observer.php`：在 `WP_DEBUG` 开启、URL 带 `?wla_observe=1`、或定义常量 `WLA_OBSERVE=1` 时启用。
- 插件会：
  - 在 `wp_print_scripts/wp_print_styles` 前后捕获脚本/样式队列，记录最终 URL 并标记外部域名。
  - 通过 `http_request_args` 与 `http_api_debug` 钩子记录所有远程 HTTP 请求的 URL、耗时、状态码。
  - 日志写入 `wp-content/uploads/wla-logs/observe.log`，按请求分隔，便于在服务器验证外链与耗时。

