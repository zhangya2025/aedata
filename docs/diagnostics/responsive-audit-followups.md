# Responsive Audit Follow-ups

## Executive Summary
1. **Info Pages shortcode 证据链**：`aegis_info_sidebar_nav` 仅在 `functions.php` 里注册，但在主题与已 require 的文件中未找到函数定义；Block 模板使用 shortcode，PHP 模板使用独立导航实现，存在“双实现/潜在缺失定义”风险。【F:wp-content/themes/aegis-themes/functions.php†L37-L80】【F:wp-content/themes/aegis-themes/templates/aegis-info-sidebar.html†L1-L20】【F:wp-content/themes/aegis-themes/page-templates/template-info-sidebar.php†L1-L92】
2. **PDP 重复渲染根因**：未在当前代码中找到“同一模板 + 同一 hook + 同一 shortcode 二次输出”的直接证据；现状更像是“Block 模板链路”与“Classic 模板链路”并行存在，若被同时触发才会造成重复，但缺少触发两条链路并行的证据点。【F:wp-content/themes/aegis-themes/templates/single-product.html†L1-L88】【F:wp-content/themes/aegis-themes/woocommerce/content-single-product.php†L1-L104】【F:wp-content/plugins/woocommerce/includes/wc-core-functions.php†L243-L286】
3. **缺失证据点**：没有找到 `aegis_info_sidebar_nav_shortcode` 定义文件；也没有看到触发 “Block 单品模板 + Classic 单品模板同时渲染” 的 loader/hook 条件。仍需运行时日志或模板加载记录来验证重复路径。

---

## A) Info Pages：`aegis_info_sidebar_nav` 定义链

### A1. Shortcode 注册点（全站搜索结果）
- 唯一注册点在主题 `functions.php`：
  - `add_shortcode( 'aegis_info_sidebar_nav', 'aegis_info_sidebar_nav_shortcode' );`
  - Evidence: `wp-content/themes/aegis-themes/functions.php` L49-L52

### A2. include/require 链追踪
- `functions.php` 仅 require 以下文件，未包含任何 `info-sidebar` 或 `info` 类功能文件：
  - Evidence: `wp-content/themes/aegis-themes/functions.php` L37-L48
- 同文件中仅看到 `aegis_info_sidebar_get_nav_items` 逻辑（并非 shortcode 回调）。
  - Evidence: `wp-content/themes/aegis-themes/functions.php` L54-L80

> 结论：**shortcode 注册存在，但函数定义在现有 include 链路中未出现**。需要补充定义文件或确认是否被外部插件注入。

### A3. 模板使用方式（Block vs PHP）
- Block 模板 `templates/aegis-info-sidebar.html` 直接调用 `[aegis_info_sidebar_nav]` shortcode。
  - Evidence: `wp-content/themes/aegis-themes/templates/aegis-info-sidebar.html` L1-L20
- PHP 模板 `page-templates/template-info-sidebar.php` 自行渲染 `<aside class="aegis-info-nav">` 与页面树，不使用 shortcode。
  - Evidence: `wp-content/themes/aegis-themes/page-templates/template-info-sidebar.php` L1-L92

**冲突风险判断**
- **双实现风险**：Block 模板依赖 shortcode，PHP 模板不依赖 shortcode，导致两套实现并存。
- **缺失定义风险**：shortcode 回调未在 include 链中出现，可能导致 Block 模板渲染空白或 fatal（取决于运行时是否由其他插件/主题文件注入）。

---

## B) PDP 重复渲染根因定位

### B1. PDP 主链路（Block 模板的 blocks/shortcodes 列表）
`templates/single-product.html` 直接包含以下结构与 shortcode：
- `woocommerce/store-notices`
- `woocommerce/breadcrumbs`
- `shortcode [aegis_wc_gallery_wall]`
- `post-title`
- `woocommerce/product-rating`
- `woocommerce/product-price`
- `woocommerce/add-to-cart-form`
- `post-excerpt`
- `shortcode [aegis_pdp_details]`
- `woocommerce/product-meta`
- `shortcode [aegis_pdp_tech_features]`
- `shortcode [aegis_pdp_faq]`
- `shortcode [aegis_pdp_certificates]`
- `woocommerce/product-reviews`
- `pattern woocommerce-blocks/related-products`
- Sticky bar（HTML）

Evidence: `wp-content/themes/aegis-themes/templates/single-product.html` L1-L88

### B2. Shortcode 定义与注册点
- `[aegis_wc_gallery_wall]`：`inc/woocommerce-gallery-wall.php` 定义 + `add_shortcode`。
  - Evidence: `wp-content/themes/aegis-themes/inc/woocommerce-gallery-wall.php` L15-L92
- `[aegis_pdp_details]`：`inc/pdp-accordion.php` 定义。
  - Evidence: `wp-content/themes/aegis-themes/inc/pdp-accordion.php` L15-L105
- `[aegis_pdp_tech_features]`：`inc/tech-features.php` 定义 + `add_shortcode`。
  - Evidence: `wp-content/themes/aegis-themes/inc/tech-features.php` L188-L250
- `[aegis_pdp_faq]`：`inc/faq-library.php` 定义 + `add_shortcode`。
  - Evidence: `wp-content/themes/aegis-themes/inc/faq-library.php` L187-L238
- `[aegis_pdp_certificates]`：`inc/certificates.php` 定义 + `add_shortcode`。
  - Evidence: `wp-content/themes/aegis-themes/inc/certificates.php` L303-L356

### B3. PDP 相关 hooks 注册与 Classic 模板链路
- Classic PDP 模板 `woocommerce/content-single-product.php` 通过 `do_action()` 输出 `woocommerce_before_single_product_summary`, `woocommerce_single_product_summary` 等 hooks。
  - Evidence: `wp-content/themes/aegis-themes/woocommerce/content-single-product.php` L21-L104
- PDP 自定义 hooks 注册点（`aegis_wc_pdp_*`）在 `inc/woocommerce-pdp.php`：
  - Evidence: `wp-content/themes/aegis-themes/inc/woocommerce-pdp.php` L13-L45
- WooCommerce 通过 `wc_get_template_part('content', 'single-product')` 加载 Classic 模板覆盖文件：
  - Evidence: `wp-content/plugins/woocommerce/includes/wc-template-functions.php` L1003-L1010
  - 模板查找顺序由 `wc_get_template_part` 的 `locate_template` 决定（先 `content-single-product.php`，再 `woocommerce/content-single-product.php`）。
  - Evidence: `wp-content/plugins/woocommerce/includes/wc-core-functions.php` L243-L269

### B4. “第二次输出源”排查结论
**结论：当前代码中未看到同一请求内“重复调用”的确凿证据。**
- Block 模板链路（`templates/single-product.html`）与 Classic 模板链路（`wc_get_template_part` → `woocommerce/content-single-product.php`）是 **两条互斥的模板路径**。
- 如果出现“模块/格子翻倍”，更可能来自 **模板加载链路被重复触发**（比如同时走了 block template 与 classic template），但在本仓库中 **缺少触发两条链路并行的 hook/loader 证据**。

**尚需补充的证据点（运行时）**
- 实际请求中触发了哪个模板（block template vs classic template），是否被调用两次。
- 是否有插件/自定义代码在 `render_block` 或 `the_content` 中二次插入相同 shortcode/HTML。

---

## 附：重点“重复可能性”排查项（发现但未构成直接证据）

1) **重复 shortcode 定义文件**
- `inc/woocommerce-gallery-wall.php` 与 `inc/woocommerce-pdp-gallery.php` 都定义了 `aegis_wc_gallery_wall_shortcode` 并注册同名 shortcode。
- 但 `functions.php` 仅 `require_once` 了 `inc/woocommerce-gallery-wall.php`，未 include `inc/woocommerce-pdp-gallery.php`，因此当前链路下不会重复注册。
  - Evidence: `wp-content/themes/aegis-themes/functions.php` L37-L48
  - Evidence: `wp-content/themes/aegis-themes/inc/woocommerce-gallery-wall.php` L15-L92
  - Evidence: `wp-content/themes/aegis-themes/inc/woocommerce-pdp-gallery.php` L15-L85

2) **重复 Classic 模板文件**
- 存在两个 `content-single-product.php`：
  - `woocommerce/content-single-product.php`
  - `woocommerce/single-product/content-single-product.php`
- `wc_get_template_part` 只会寻找 `content-single-product.php` 与 `woocommerce/content-single-product.php`，不会匹配更深的 `woocommerce/single-product/content-single-product.php`，因此后者在当前模板加载逻辑下不会被命中。
  - Evidence: `wp-content/plugins/woocommerce/includes/wc-core-functions.php` L243-L269
  - Evidence: `wp-content/themes/aegis-themes/woocommerce/content-single-product.php` L1-L104
  - Evidence: `wp-content/themes/aegis-themes/woocommerce/single-product/content-single-product.php` L1-L88

---

## 如何使用追踪器验证（简版）
1. 确认 `WP_DEBUG` 为 `true` 且已启用 `WP_DEBUG_LOG`。
2. 访问出现“翻倍”的 PDP 页面一次。
3. 查看 `wp-content/debug.log`，搜索 `[aegis-template-trace]` 前缀日志。
4. 重点检查 `template_include` 与 `wc_get_template_part`/`wc_get_template` 的命中路径。
5. 检查三大 Woo hook 计数是否 >1。
6. 检查 PDP 相关 shortcode 的计数与调用栈摘要。
