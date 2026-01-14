# Shop 页面“后台内容与前台不一致”调查报告

## 现状复述（确认理解）
- 后台编辑器中的“SHOP”页面包含一套自定义内容（播放器/区块/封面图等）。
- 但前台访问 `/shop/` 时实际显示的是 WooCommerce 的商品网格/归档列表，与后台页面内容不同。
- 目标：定位 `/shop/` 前台到底命中了什么渲染链路/模板/钩子/设置，以及为何后台内容没有输出。

> 本报告基于仓库静态代码与模板分析；未做任何运行态改动或探针注入。

---

## A. 路由结论：`/shop/` 属于什么类型（普通 Page 还是 Woo Shop/产品归档）

**结论**：`/shop/` 在 WooCommerce 中会被当作产品归档（post type archive）处理，而不是普通 Page 的 `the_content` 输出。判断依据是 WooCommerce 在主查询阶段针对 `wc_get_page_id( 'shop' )` 进行改写：若主题支持 Woo/FSE，则把查询 `post_type` 改为 `product`，并标记为 `is_post_type_archive`。这会导致 `/shop/` 的渲染走产品归档链路。 

**证据（仓库片段）**：WooCommerce `class-wc-query.php` 的 `pre_get_posts` 中，对 shop 页面进行主查询改写。 
- 文件：`wp-content/plugins/woocommerce/includes/class-wc-query.php`
- 片段（行号来自本仓库读取）：

```php
// ...
if ( wc_current_theme_supports_woocommerce_or_fse() && $q->is_page() && 'page' === get_option( 'show_on_front' ) && absint( $q->get( 'page_id' ) ) === wc_get_page_id( 'shop' ) ) {
	// This is a front-page shop.
	$q->set( 'post_type', 'product' );
	$q->set( 'page_id', '' );
	// ...
	$q->is_singular          = false;
	$q->is_post_type_archive = true;
	$q->is_archive           = true;
	$q->is_page              = true;
}
```
- 行号：`377-405` 【F:wp-content/plugins/woocommerce/includes/class-wc-query.php†L377-L405】

**补充证据（配置绑定）**：`wc_get_page_id( 'shop' )` 来自 `woocommerce_shop_page_id` option。 
- 文件：`wp-content/plugins/woocommerce/includes/wc-page-functions.php`
- 片段：

```php
$page = apply_filters( 'woocommerce_get_' . $page . '_page_id', get_option( 'woocommerce_' . $page . '_page_id' ) );
return $page ? absint( $page ) : -1;
```
- 行号：`84-86` 【F:wp-content/plugins/woocommerce/includes/wc-page-functions.php†L84-L86】

---

## B. 模板结论：最终由哪个模板渲染（Block template 还是 PHP 模板），优先级链路是什么

**结论**：WooCommerce 的模板加载器在命中 shop/archive 场景时，会优先选择 `archive-product` 的 Block Template（若存在），否则回退到 `archive-product.php`。WooCommerce 插件自身提供了 `archive-product.html`，并在其中用 `woocommerce/legacy-template` 直接调用传统 `archive-product` 模板。

**证据 1（模板优先级逻辑）**：WooCommerce 模板加载逻辑对 shop/archive 的处理。 
- 文件：`wp-content/plugins/woocommerce/includes/class-wc-template-loader.php`
- 片段：

```php
} elseif (
	( is_post_type_archive( 'product' ) || is_page( wc_get_page_id( 'shop' ) ) ) &&
	! self::has_block_template( 'archive-product' )
) {
	$default_file = self::$theme_support ? 'archive-product.php' : '';
} else {
	$default_file = '';
}
```
- 行号：`180-186` 【F:wp-content/plugins/woocommerce/includes/class-wc-template-loader.php†L180-L186】

**证据 2（Woo 提供的 Block Template 内容）**：WooCommerce 自带 `archive-product.html`，其中使用 legacy-template 调用 `archive-product`。 
- 文件：`wp-content/plugins/woocommerce/templates/templates/archive-product.html`
- 片段：

```html
<main class="wp-block-group"><!-- wp:woocommerce/legacy-template {"template":"archive-product"} /--></main>
```
- 行号：`3` 【F:wp-content/plugins/woocommerce/templates/templates/archive-product.html†L3】

**证据 3（主题为块主题，支持 FSE）**：主题声明 `full-site-editing`，说明 Block Template 体系在作用。 
- 文件：`wp-content/themes/aegis-themes/style.css`
- 片段：

```css
Tags: block-styles, full-site-editing, editor-style, wide-blocks
```
- 行号：`7` 【F:wp-content/themes/aegis-themes/style.css†L7】

---

## C. 内容缺失原因：后台编辑器内容为何没输出

**结论**：`archive-product.php` 只输出 WooCommerce 的商品循环和相关 hooks，不调用 `the_content()` 或页面正文输出，因此后台编辑器中 Page 的内容不会显示在 `/shop/`。此外，Woo 的 `archive-product.html` block 模板直接调用 legacy `archive-product`，同样不会输出 Page 内容。

**证据 1（传统模板不输出页面正文）**：`archive-product.php` 只执行 WooCommerce hooks/循环，无 `the_content()`。
- 文件：`wp-content/plugins/woocommerce/templates/archive-product.php`
- 片段：

```php
get_header( 'shop' );
// ...
do_action( 'woocommerce_before_main_content' );
// ...
if ( woocommerce_product_loop() ) {
	// ...
	while ( have_posts() ) {
		the_post();
		do_action( 'woocommerce_shop_loop' );
		wc_get_template_part( 'content', 'product' );
	}
	// ...
}
// ...
get_footer( 'shop' );
```
- 行号：`20-97` 【F:wp-content/plugins/woocommerce/templates/archive-product.php†L20-L97】

**证据 2（Block Template 仍调用 legacy）**：`archive-product.html` 仅使用 `woocommerce/legacy-template`，没有 `post-content` 区块。 
- 文件：`wp-content/plugins/woocommerce/templates/templates/archive-product.html`
- 片段：

```html
<main class="wp-block-group"><!-- wp:woocommerce/legacy-template {"template":"archive-product"} /--></main>
```
- 行号：`3` 【F:wp-content/plugins/woocommerce/templates/templates/archive-product.html†L3】

**证据 3（Shop 页面编辑器模板面板被移除，暗示模板主导）**：WooCommerce 对 Shop 页面移除模板面板，说明由站点模板控制渲染。 
- 文件：`wp-content/plugins/woocommerce/src/Blocks/Templates/ProductCatalogTemplate.php`
- 片段：

```php
add_filter( 'current_theme_supports-block-templates', array( $this, 'remove_block_template_support_for_shop_page' ) );
// ...
if ( is_admin() && 'post.php' === $pagenow && ... && wc_get_page_id( 'shop' ) === $post->ID ) {
	return false;
}
```
- 行号：`25-84` 【F:wp-content/plugins/woocommerce/src/Blocks/Templates/ProductCatalogTemplate.php†L25-L84】

---

## D. 根因假设（最可能 → 次可能），含证据与不改代码验证方法

### 假设 1（最可能）：WooCommerce 把 `/shop/` 当作产品归档处理，页面内容自然不会输出
- **命中证据**：
  - `wc_get_page_id( 'shop' )` 来自 `woocommerce_shop_page_id` option。 【F:wp-content/plugins/woocommerce/includes/wc-page-functions.php†L84-L86】
  - Shop 页面主查询被改为 `post_type=product`，并标记为归档。 【F:wp-content/plugins/woocommerce/includes/class-wc-query.php†L377-L405】
  - 模板加载在有 Block Template 时不使用 `archive-product.php`，而走 Block Template。 【F:wp-content/plugins/woocommerce/includes/class-wc-template-loader.php†L180-L186】
- **不改代码验证方法**：
  1) 后台 WooCommerce → 设置 → 产品 → 商店页面，确认是否绑定当前“SHOP”页（对应 `woocommerce_shop_page_id`）。
  2) 访问 `/shop/`，观察是否出现产品归档特征（排序/分页/商品网格）。
- **若成立的修复方向（仅方案描述）**：
  - 在 Site Editor 中编辑“Product Catalog”模板以放置所需内容；或
  - 使用独立页面展示自定义内容，将导航指向该页；或
  - 在模板中加入 `Post Content` 区块以显示页面内容。

### 假设 2：WooCommerce 的 `archive-product.html` 模板优先级覆盖了主题/页面内容
- **命中证据**：
  - WooCommerce 提供 `archive-product.html`。 【F:wp-content/plugins/woocommerce/templates/templates/archive-product.html†L1-L5】
  - 模板加载逻辑优先使用 Block Template，存在时不会回退到 `archive-product.php`。 【F:wp-content/plugins/woocommerce/includes/class-wc-template-loader.php†L180-L186】
- **不改代码验证方法**：
  1) Site Editor → 模板 → “Product Catalog（archive-product）” 查看是否为 Woo 默认模板。
  2) 若主题内存在 `templates/archive-product.html`，确认其内容是否包含 `post-content`。
- **若成立的修复方向**：
  - 在主题中创建/覆盖 `archive-product.html`，加入需要的区块；或
  - 在 Site Editor 中覆盖 `Product Catalog` 模板并插入内容。

### 假设 3：主题/插件存在 Woo 模板覆盖（如 `woocommerce.php` 或 `woocommerce/archive-product.php`）
- **命中证据**：
  - WooCommerce 模板加载器会优先检查 `woocommerce.php` 与页面模板文件（若存在则覆盖默认模板）。 【F:wp-content/plugins/woocommerce/includes/class-wc-template-loader.php†L198-L212】
- **不改代码验证方法**：
  1) 在主题目录中查找 `woocommerce.php` 或 `woocommerce/archive-product.php`（主题文件编辑器或文件系统）。
  2) 若存在，检查是否输出 `the_content` 或完全自定义了 Shop 页面布局。
- **若成立的修复方向**：
  - 在覆盖模板中加入页面内容输出（如 `Post Content` 区块或 `the_content`）；或
  - 移除覆盖并用 Site Editor 的模板控制渲染。

---

## E. 在“Shop 页面绑定 Woo shop”的前提下，让后台编辑内容在前台生效的可选路径（论证）

> 仅给出实施路径与论证，不涉及任何代码修改或运行时变更。

### 路径 1：使用 Site Editor 修改“Product Catalog（archive-product）”模板，使其包含页面内容
- **论证要点**：当前 `/shop/` 由 `archive-product` 模板链路渲染，且 WooCommerce 默认 `archive-product.html` 使用 `woocommerce/legacy-template`，不会输出页面正文。若在 Site Editor 中编辑该模板并加入 `Post Content`（或等效的页面内容区块），即可在“产品归档模板”内显示 Shop 页面正文，从而让后台编辑内容生效。 【F:docs/diagnostics/shop-page-mismatch.md†L47-L122】
- **适用前提**：主题为块主题/FSE，且模板可在 Site Editor 中编辑。 【F:docs/diagnostics/shop-page-mismatch.md†L76-L83】
- **不改代码验证方式**：
  1) Site Editor → 模板 → “Product Catalog（archive-product）”，确认当前模板是否为 Woo 默认且未包含页面内容区块。 【F:docs/diagnostics/shop-page-mismatch.md†L67-L83】
  2) 若该模板可编辑，检查是否能插入“Post Content”等显示页面内容的区块。

### 路径 2：将自定义内容放到“产品目录模板”，而不是依赖 Page 内容
- **论证要点**：WooCommerce 的产品归档模板本身不会输出页面内容，但可以在模板结构中直接放置需要的区块（比如播放器/封面图等），因此在模板层面实现“前台展示”等效于后台页面内容生效。 【F:docs/diagnostics/shop-page-mismatch.md†L87-L122】
- **适用前提**：允许通过 Site Editor 管理模板内容（块主题/FSE）。 【F:docs/diagnostics/shop-page-mismatch.md†L76-L83】
- **不改代码验证方式**：
  1) 在 Site Editor 中查看“Product Catalog”模板结构，确认可以放置所需区块。
  2) 对比前台 `/shop/` 与模板内容，验证是否按模板输出而非 Page 内容。

### 路径 3：使用一个“非 Shop 页面”承载自定义内容，Shop 仅作为产品归档
- **论证要点**：Shop 页面在 WooCommerce 中是“产品归档入口”，其内容被产品归档模板接管；因此可将自定义内容迁移到另一个普通页面，并在导航/CTA 中改为指向该页面，避免与 Woo 的归档链路冲突。该路径不改变 Woo 的归档逻辑，仅规避冲突。 【F:docs/diagnostics/shop-page-mismatch.md†L12-L112】
- **适用前提**：业务允许将“内容展示页”与“产品归档页”分离。
- **不改代码验证方式**：
  1) 后台创建一个普通 Page，发布并确认内容正常输出（非 shop 绑定页面）。
  2) 在前台对比 `/shop/` 与该普通页面的渲染差异，验证 Page 内容路径可用。

### 路径 4：检查并移除主题/插件对 Woo 模板的覆盖（若存在）
- **论证要点**：若主题存在 `woocommerce.php` 或 `woocommerce/archive-product.php` 覆盖，可能导致输出路径与预期不一致。移除覆盖可回到标准的模板链路，再通过模板方式插入内容。 【F:docs/diagnostics/shop-page-mismatch.md†L164-L172】
- **适用前提**：主题/插件确实存在覆盖文件。
- **不改代码验证方式**：
  1) 检查主题目录是否存在 `woocommerce.php` 或 `woocommerce/archive-product.php`（无需改动，仅确认存在性）。
  2) 若存在，评估是否必须保留覆盖；如无需保留，可规划回退到默认模板再按路径 1/2 处理。

---

## 复现与验证步骤（不改代码）
1. 后台确认 Shop 页面绑定：WooCommerce → 设置 → 产品 → 商店页面（对应 `woocommerce_shop_page_id`）。【F:wp-content/plugins/woocommerce/includes/wc-page-functions.php†L84-L86】
2. Site Editor → 模板 → “Product Catalog（archive-product）”，检查模板内容是否为 Woo 默认并包含 `woocommerce/legacy-template`。【F:wp-content/plugins/woocommerce/templates/templates/archive-product.html†L1-L5】
3. 主题文件检查：是否存在 `woocommerce.php` 或 `woocommerce/archive-product.php`（若存在，按模板加载优先级会影响输出）。【F:wp-content/plugins/woocommerce/includes/class-wc-template-loader.php†L198-L212】

---

> 说明：本次仅做静态调查定位；未修改任何功能/样式/模板逻辑，也未引入探针。
