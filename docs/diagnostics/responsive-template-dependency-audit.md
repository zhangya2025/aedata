# Responsive Template & Rendering Dependency Audit

## Executive Summary
1. 主题为 **混合型（Block + Classic 兼容层）**：存在 `theme.json`、`templates/*.html` Block 模板，同时仍保留 `header.php/footer.php` 与 `woocommerce/*.php` Classic 覆盖文件。
2. /shop (PLP) 入口走 **Block 模板 + legacy-template**，最终落到 **Classic PHP** `woocommerce/archive-product.php` 与分支 partials。
3. 单品 PDP 入口走 **Block 模板**（`templates/single-product.html`），主体由 Woo blocks + 短代码渲染；Classic PDP 覆盖文件仍存在但为备用路径。
4. Header/Footer 渲染由 **Block 模板部件**触发，自定义区块分别来自 `aegis-mega-header` 与 `aegis-footer` 插件。
5. 响应式断点与菜单态主要由 **aegis-mega-header 的 JS/CSS** 控制，且只在“home”类页面启用；断点存在 960/961 的细微不一致。
6. 若按《AEGISMAX 响应式网站结构布局设计规范（初版）》落地，整体改造难度 **M~L**，最高风险点包括：Block/Classic 双模板链路、Header 状态逻辑仅首页启用、PLP 过滤逻辑深度耦合 Woo hooks。

---

## A. 模板体系与页面渲染链路总览

### A1. 主题类型判定（Block / Classic / 混合）
- **Block 证据**：存在 `theme.json` 与模板体系定义（`customTemplates` / `templateParts`）。
  - Evidence: `wp-content/themes/aegis-themes/theme.json` L90-L113
- **Block 模板文件**：`templates/*.html` 内含 `archive-product.html` / `single-product.html` / `page.html` / `single.html` / `index.html`。
  - Evidence: `wp-content/themes/aegis-themes/templates/archive-product.html` L1-L8; `templates/single-product.html` L1-L88; `templates/page.html` L1-L10; `templates/single.html` L1-L11; `templates/index.html` L1-L22
- **Classic 兼容层**：仍有 `header.php` / `footer.php` 与 `woocommerce/*.php` 覆盖。
  - Evidence: `wp-content/themes/aegis-themes/header.php` L1-L16; `footer.php` L1-L7; `woocommerce/archive-product.php` L1-L120

> 结论：**混合型（Block + Classic 兼容层）**。

### A2. 模板覆盖清单（主题内存在情况）

**Block templates（存在）**
- `templates/archive-product.html` ✅  
  - Evidence: `wp-content/themes/aegis-themes/templates/archive-product.html` L1-L8
- `templates/single-product.html` ✅  
  - Evidence: `wp-content/themes/aegis-themes/templates/single-product.html` L1-L88
- `templates/page.html` ✅
  - Evidence: `wp-content/themes/aegis-themes/templates/page.html` L1-L10
- `templates/single.html` ✅
  - Evidence: `wp-content/themes/aegis-themes/templates/single.html` L1-L11
- `templates/index.html` ✅
  - Evidence: `wp-content/themes/aegis-themes/templates/index.html` L1-L22

**Classic WooCommerce 覆盖（存在）**
- `woocommerce/archive-product.php` ✅
- `woocommerce/content-single-product.php` ✅
- `woocommerce/single-product/content-single-product.php` ✅
  - Evidence: `wp-content/themes/aegis-themes/woocommerce/archive-product.php` L1-L120; `woocommerce/content-single-product.php` L1-L117; `woocommerce/single-product/content-single-product.php` L1-L88

> 备注：主题内未发现 `woocommerce/single-product.php` 直接覆盖文件；但 `content-single-product.php` 存在两份（根目录 + single-product 子目录）。

### A3. /shop（PLP）与 PDP 真实渲染链路与优先级

**/shop (PLP)**
- Block 模板入口为 `templates/archive-product.html`，其中调用 `woocommerce/legacy-template` 触发 Classic 模板链路。
  - Evidence: `wp-content/themes/aegis-themes/templates/archive-product.html` L1-L8
- `woocommerce/legacy-template` 实际会落到主题的 `woocommerce/archive-product.php`（Classic 覆盖）。
  - Evidence: `wp-content/themes/aegis-themes/woocommerce/archive-product.php` L1-L120
- 该 Classic 模板内根据 `is_shop()` 与分类分支，可能转向 `woocommerce/partials/archive-product--sleepingbags.php` 或 `archive-product--clothes.php`；且 shop 本身会 **直接跳过默认 loop**。
  - Evidence: `woocommerce/archive-product.php` L25-L60; `woocommerce/partials/archive-product--sleepingbags.php` L1-L86

**PDP (single product)**
- Block 模板入口为 `templates/single-product.html`，主要由 Woo blocks + Shortcode 组成。
  - Evidence: `wp-content/themes/aegis-themes/templates/single-product.html` L1-L88
- Classic PDP 覆盖文件仍存在，但在 Block 模板启用情况下为备用链路。
  - Evidence: `wp-content/themes/aegis-themes/woocommerce/content-single-product.php` L1-L117

> 关键结论：
> - **/shop (PLP)** 实际渲染是 **Block 模板入口 + Classic 模板执行**（legacy-template）。
> - **PDP** 实际为 **Block 模板渲染**（Woo blocks + 短代码），Classic PDP 覆盖为 fallback。

### A4. Info Pages（信息页）渲染链路
- Block 模板：`templates/aegis-info-sidebar.html`（包含 `aegis_info_sidebar_nav` shortcode）。  
  - Evidence: `wp-content/themes/aegis-themes/templates/aegis-info-sidebar.html` L1-L20
- Classic Page Template：`page-templates/template-info-sidebar.php`（PHP 渲染 nav + content）。  
  - Evidence: `wp-content/themes/aegis-themes/page-templates/template-info-sidebar.php` L1-L92
- Shortcode 注册：`aegis_info_sidebar_nav` 在主题 `functions.php` 中注册（但未发现函数定义）。  
  - Evidence: `wp-content/themes/aegis-themes/functions.php` L49-L52

---

## B. Header / 菜单状态控制审计（“菜单态覆盖全站交互”的可行性）

### B1. Header 入口与渲染位置
- Header 模板部件由 Block 模板调用：`parts/header.html`。
  - Evidence: `wp-content/themes/aegis-themes/parts/header.html` L1
- 实际渲染为 `aegis/mega-header` Block（插件提供 render_callback）。
  - Evidence: `wp-content/plugins/aegis-mega-header/aegis-mega-header.php` L55-L99
- Block 输出 `<header class="aegis-mega-header">`，含导航和移动端结构。
  - Evidence: `wp-content/plugins/aegis-mega-header/aegis-mega-header.php` L1381-L1445

### B2. 菜单态与断点来源（JS + CSS）

**JS 状态判断（aegis-mega-header/view.js）**
- “首页态”判定依赖 body class：`home/front-page/page-id-49966`。
  - Evidence: `wp-content/plugins/aegis-mega-header/view.js` L32-L36
- 仅在 `isHome && window.innerWidth > 960` 时启用滚动/覆盖态逻辑。
  - Evidence: `view.js` L36-L37, L283-L286
- `mode-overlay / mode-solid / is-header-hidden / is-home` 的类切换集中于 JS。
  - Evidence: `view.js` L121-L143
- 移动菜单抽屉开关由 JS 控制（overlay + drawer），并在 `>960px` 时自动关闭。
  - Evidence: `view.js` L349-L452

**CSS 断点与样式态**
- 断点：`max-width: 1280/1100/960/640` 与 `min-width: 961` 用于切换移动头部与桌面头部。
  - Evidence: `wp-content/plugins/aegis-mega-header/style.css` L898-L1169
- 菜单态样式（`is-home`、`mode-solid`、`mode-overlay`、`is-header-hidden`）集中在 mega-header 样式表内。
  - Evidence: `style.css` L32-L718

**其它相关 CSS**
- 首页 hero 与 header 的相对层级/偏移定义在主题主样式。
  - Evidence: `wp-content/themes/aegis-themes/assets/css/main.css` L16-L27

### B3. 结论：菜单态是否覆盖全站？断点是否统一？
- **菜单态覆盖全站：否**。当前 JS 只在 `isHome` 条件下运行（首页/指定页面 ID），其他页面不触发 `mode-overlay/solid` 切换。
- **断点统一性：不完全**。JS 使用 `>960`，CSS 使用 `max-width: 960` + `min-width: 961`，存在 1px 交界；同时存在 1100/1280/640 等中间态断点。

### B4. 若改为“布局三段 + 交互两段 + 菜单态覆盖全站”，影响文件与难度
- **核心 JS 逻辑（S/M）**：`wp-content/plugins/aegis-mega-header/view.js`（`isHome` 判断与滚动逻辑）。
- **响应式断点（S/M）**：`wp-content/plugins/aegis-mega-header/style.css`（960/961/1100/1280/640）。
- **结构基线与首页层叠（S）**：`wp-content/themes/aegis-themes/assets/css/main.css`。
- **渲染入口（S）**：`wp-content/themes/aegis-themes/parts/header.html` 与 `aegis-mega-header` block。

难度评估：**M**（需统一断点、重新定义“首页 vs 全站”菜单态逻辑，影响 JS 与 CSS 协同）。

---

## C. PLP（分类/列表页）审计（筛选区与结果区结构）

### C1. PLP 结果区链路与 hooks
- PLP 入口为 `templates/archive-product.html` → `woocommerce/legacy-template` → `woocommerce/archive-product.php`。
  - Evidence: `archive-product.html` L1-L8; `woocommerce/archive-product.php` L1-L120
- Classic 模板使用 `woocommerce_before_shop_loop` / `woocommerce_after_shop_loop` / `woocommerce_shop_loop` hooks 渲染结果数、排序、分页等。
  - Evidence: `woocommerce/archive-product.php` L54-L99
- 分类特化：`sleepingbags` / `clothes` 会走 `partials/archive-product--*.php`（仍使用相同 hooks）。
  - Evidence: `woocommerce/archive-product.php` L36-L55; `partials/archive-product--sleepingbags.php` L24-L69

### C2. 过滤器 / 查询逻辑注入点
- PLP toolbar + 过滤抽屉由 `aegis_plp_filters_render_toolbar` 注入到 `woocommerce_before_shop_loop`。
  - Evidence: `functions.php` L151-L158
- 查询条件由 `woocommerce_product_query` 过滤器处理（非 `pre_get_posts` 主链路）。
  - Evidence: `functions.php` L159-L163; `inc/aegis-plp-filters.php` L1189-L1260
- 结果数量、排序、侧栏被移除（Classic 模板仍触发 hooks）。
  - Evidence: `inc/aegis-plp-filters.php` L625-L633
- 每页数量由 `loop_shop_per_page` 统一为 12。
  - Evidence: `functions.php` L165-L171
- 过滤 UI 交互：移动端抽屉/遮罩/锁滚动逻辑在 `assets/js/aegis-plp-filters.js`。
  - Evidence: `assets/js/aegis-plp-filters.js` L1-L66
- 过滤 UI 的响应式断点与按钮策略在 `assets/css/aegis-plp-filters.css`。
  - Evidence: `assets/css/aegis-plp-filters.css` L284-L324

### C3. “Desktop 左侧筛选 + 右侧 grid；Mobile 抽屉筛选；筛选不跳顶”改造点

**结构层（M）**
- `woocommerce/archive-product.php` 与 `partials/archive-product--*.php`：调整主容器与 sidebar/grid 布局结构。
- `inc/aegis-plp-filters.php`：toolbar 与 filter form 的 DOM 结构。

**样式层（S/M）**
- `assets/css/aegis-plp-filters.css`（drawer, toolbar, responsive）
- `assets/css/woocommerce.css`（若需调整产品 grid/两栏布局）

**JS 交互层（M）**
- `assets/js/aegis-plp-filters.js`：抽屉交互、锁滚动、按钮逻辑
- “筛选不跳顶”可能需要 JS 中阻止默认跳转或引入 AJAX（现为 GET 提交）。

难度评估：
- 结构层：**M**（经典模板+hooks+自定义 toolbar）
- 样式层：**S/M**
- 交互层：**M**（若引入 AJAX 则升为 L）

---

## D. PDP（产品详情页）审计（6 区块固定结构的实现难度）

### D1. 当前 PDP 结构来源
- Block 模板主体：`templates/single-product.html`（Gallery/Buybox/详情/FAQ/证书/Reviews/Related/Sticky）。
  - Evidence: `templates/single-product.html` L8-L85
- 关键短代码来源：
  - `[aegis_wc_gallery_wall]` → `inc/woocommerce-gallery-wall.php`（shortcode 注册）。
    - Evidence: `inc/woocommerce-gallery-wall.php` L1-L92
  - `[aegis_pdp_details]` → `inc/pdp-accordion.php`。
    - Evidence: `inc/pdp-accordion.php` L1-L110
  - `[aegis_pdp_tech_features]` / `[aegis_pdp_faq]` / `[aegis_pdp_certificates]` → `inc/tech-features.php` / `inc/faq-library.php` / `inc/certificates.php`（短代码注册）。
    - Evidence: `inc/tech-features.php` L188-L250; `inc/faq-library.php` L187-L238; `inc/certificates.php` L303-L356
- PDP 模块 hooks（Classic 兼容）：`aegis_wc_pdp_*` hooks 由 `inc/woocommerce-pdp.php` 注册。
  - Evidence: `inc/woocommerce-pdp.php` L9-L45
- Classic PDP 模板覆盖仍存在（两份 `content-single-product.php`）。
  - Evidence: `woocommerce/content-single-product.php` L1-L117; `woocommerce/single-product/content-single-product.php` L1-L88

### D2. “6 区块固定结构”落地路径建议（不写代码）
建议以 **Block 模板** 为主链路：
1. **结构层**：集中在 `templates/single-product.html` 调整模块顺序与固定 6 区块。
2. **内容来源**：
   - Gallery / Purchase 继续使用 blocks + `[aegis_wc_gallery_wall]`。
   - Selling Points / Specs / Reviews / Cross-sell 对应 `inc/pdp-accordion.php`、`inc/woocommerce-pdp.php`、Woo blocks patterns。
3. **Classic fallback**：若仍需 Classic 支持，需同步调整 `woocommerce/content-single-product.php` 与 `woocommerce/single-product/content-single-product.php`。

难度评估：**M**（Block 模板为主）；若需双链路同步则 **L**。

### D3. 重复渲染风险排查结论
- PDP 同时存在 **Block 模板 + Classic 覆盖**，潜在重复风险来自“模板链路切换”或“shortcode/Hook 同时输出”。
- 当前 Block 模板未直接调用 Classic hooks；但 Classic `content-single-product.php` 有两份（可能引起误用或维护混乱）。
  - Evidence: `woocommerce/content-single-product.php` L1-L117; `woocommerce/single-product/content-single-product.php` L1-L88

---

## E. Footer 审计（与四大信息页体系的链接/内容装配）

### E1. 渲染方式与数据来源
- Footer 模板部件为 `parts/footer.html`，渲染 `aegis/footer` block。
  - Evidence: `wp-content/themes/aegis-themes/parts/footer.html` L1
- Block 由 `aegis-footer` 插件注册并用 render_callback 输出 HTML。
  - Evidence: `wp-content/plugins/aegis-footer/aegis-footer.php` L229-L256
- Footer 数据来源为 `aegis_footer_settings` option（后台设置页），默认提供 4 列链接配置。
  - Evidence: `aegis-footer.php` L12-L74

### E2. 是否满足四列/移动折叠
- CSS 已提供 **网格四列** 与 **移动端折叠 accordion**，断点 767/768。
  - Evidence: `wp-content/plugins/aegis-footer/style.css` L23-L110

### E3. 按规范统一的改造判断
- 结构已具备（grid + accordion + 4 columns），**大概率仅需样式层微调**与“链接内容配置”。
- 风险较低（S），除非需新增动态数据源或全局 menu 绑定。

---

## F. 依赖与风险清单

### F1. 关键依赖
- **WooCommerce**：Block templates 与 legacy-template/classic hooks 并存。
  - Evidence: `templates/archive-product.html` L1-L8; `woocommerce/archive-product.php` L1-L120
- **aegis-mega-header 插件**：Header 状态逻辑与断点集中在 JS/CSS。
  - Evidence: `aegis-mega-header/view.js` L32-L452; `style.css` L898-L1199
- **aegis-footer 插件**：Footer 结构与响应式逻辑。
  - Evidence: `aegis-footer.php` L229-L256; `style.css` L23-L110
- **自定义 PLP 过滤逻辑**：`inc/aegis-plp-filters.php` + `assets/js/css`。
  - Evidence: `functions.php` L151-L171; `inc/aegis-plp-filters.php` L1189-L1260; `assets/js/aegis-plp-filters.js` L1-L66
- **PDP 短代码与模块 hooks**：`inc/pdp-accordion.php`, `inc/woocommerce-pdp.php`。
  - Evidence: `inc/pdp-accordion.php` L1-L110; `inc/woocommerce-pdp.php` L9-L45

### F2. 最小改造路径建议（分阶段）
1. **Header**
   - 改造 `aegis-mega-header/view.js` 的状态逻辑（全站生效）与 `style.css` 断点统一。
   - 风险点：首页行为与全站行为差异、960/961 断点错位。
2. **PLP**
   - 先在 `woocommerce/archive-product.php` / `partials` 维持经典链路，重新组织 layout（左筛选右 grid）。
   - 风险点：与 `woocommerce_before_shop_loop` hooks、`aegis_plp_filters_*` 逻辑耦合。
3. **PDP**
   - 以 `templates/single-product.html` 为主链路固化 6 模块。
   - 风险点：Block + Classic 双模板维护，短代码输出重复/缺失。
4. **Footer**
   - 主要为样式与内容配置层；若需统一全站菜单态，确保 CSS 与 settings 同步。

难度总评：**M~L**（取决于是否保留 Classic fallback 与跨模板同步）。
