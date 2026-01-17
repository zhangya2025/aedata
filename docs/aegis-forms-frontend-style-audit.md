# Aegis Forms 前台表单渲染与样式承载审计

> 范围：`wp-content/plugins/aegis-forms`。本报告只记录现状，不改动任何功能/样式。

## A) 插件前台渲染链路

### 入口文件与版本信息
- 插件入口文件：`wp-content/plugins/aegis-forms/aegis-forms.php`，版本为 `0.1.0`，并在 `plugins_loaded` 时注册前台与后台类。【F:wp-content/plugins/aegis-forms/aegis-forms.php†L1-L28】

### 注册的短代码列表
前台短代码由 `Aegis_Forms_Frontend::register()` 注册，全部映射到类内静态方法：
- `[aegis_repair_form]` → `Aegis_Forms_Frontend::render_repair_form()`【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L12-L24】
- `[aegis_dealer_form]` → `Aegis_Forms_Frontend::render_dealer_form()`【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L12-L28】
- `[aegis_contact_form]` → `Aegis_Forms_Frontend::render_contact_form()`【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L12-L33】
- `[aegis_sponsorship_form]` → `Aegis_Forms_Frontend::render_sponsorship_form()`【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L12-L37】
- `[aegis_customization_form]` → `Aegis_Forms_Frontend::render_customization_form()`【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L12-L40】

### 前台表单输出方式
- `render_*_form()` 统一调用 `render_form($type)`，该方法使用 `ob_start()` 并直接输出 HTML，最终 `return ob_get_clean()`；无模板文件 include，也未使用 `render_block()` 等块渲染方式。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L23-L207】
- `render_notice()` 通过 `$_GET['aegis_forms']` 生成前台提示 DOM（成功/错误提示），并返回 HTML 字符串，注入到表单之前。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L45-L57】【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L209-L246】

## B) DOM 结构与可控选择器（用于后续写 CSS）

### 全表单共性结构
- 所有表单直接输出 `<form ... data-aegis-forms="true">`，**没有额外 wrapper div**；可依赖的稳定选择器为 `form[data-aegis-forms="true"]`。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L54-L58】
- 每个字段区块以 `<p>` 包裹，内部结构是 `label` + `br` + `input/textarea`；无通用 class 包裹器。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L67-L183】
- 提交按钮固定 class：`.aegis-forms-submit`。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L168-L170】
- 附件字段（非 contact 表单）固定 `input#aegis-forms-attachment`，并包含 `<small>` 提示文案。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L171-L183】

### 各表单字段结构（固定 id/label）
> 说明：字段 `id` 命名为固定字符串（稳定可依赖），没有动态生成 class。

#### Repair 表单（`form_type=repair`）
- 容器：`form[data-aegis-forms="true"]`，无额外 wrapper。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L54-L58】
- 字段 ID：
  - `#aegis-repair-name`, `#aegis-repair-email`, `#aegis-repair-phone`, `#aegis-repair-country`, `#aegis-repair-order-number`, `#aegis-repair-product-sku`, `#aegis-repair-message`。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L67-L95】
- 提交按钮：`.aegis-forms-submit`。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L168-L170】
- 成功/错误提示：`.notice.notice-success` / `.notice.notice-error`（`render_notice()` 输出）。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L209-L244】

#### Dealer 表单（`form_type=dealer`）
- 容器：`form[data-aegis-forms="true"]`，无额外 wrapper。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L54-L58】
- 字段 ID：
  - `#aegis-dealer-company-name`, `#aegis-dealer-contact-name`, `#aegis-dealer-email`, `#aegis-dealer-phone`, `#aegis-dealer-country`, `#aegis-dealer-website`, `#aegis-dealer-message`。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L96-L124】
- 提交按钮：`.aegis-forms-submit`。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L168-L170】
- 成功/错误提示：`.notice.notice-success` / `.notice.notice-error`（`render_notice()` 输出）。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L209-L244】

#### Contact 表单（`form_type=contact`）
- 容器：`form[data-aegis-forms="true"]`，无额外 wrapper。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L54-L58】
- 字段 ID：
  - `#aegis-contact-name`, `#aegis-contact-email`, `#aegis-contact-phone`, `#aegis-contact-country`, `#aegis-contact-subject`, `#aegis-contact-message`。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L125-L149】
- 提交按钮：`.aegis-forms-submit`。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L168-L170】
- 成功/错误提示：`.notice.notice-success` / `.notice.notice-error`（`render_notice()` 输出）。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L209-L244】

#### Sponsorship / Customization 表单（`form_type=sponsorship|customization`）
- 当前实现使用 `else` 分支的“special”字段组（与 contact/repair/dealer 不同）：
  - `#aegis-special-name`, `#aegis-special-email`, `#aegis-special-subject`, `#aegis-special-message`。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L150-L166】
- 这两种类型还会输出附件上传区块（`#aegis-forms-attachment`）。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L171-L183】
- 提交按钮与提示 DOM 同上（`.aegis-forms-submit`, `.notice`）。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L168-L170】【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L209-L244】

### 稳定/动态选择器清单
- **稳定可依赖（代码写死）**：
  - `form[data-aegis-forms="true"]`、`.aegis-forms-submit`、`#aegis-forms-attachment`。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L54-L58】【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L168-L183】
  - 各字段固定 `id`（如 `#aegis-repair-name` / `#aegis-contact-email` / `#aegis-special-message` 等）。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L67-L166】
  - 成功/错误提示 `.notice.notice-success` / `.notice.notice-error`。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L209-L244】
- **动态/不可依赖**：
  - `form_type` 与 `request_token` 隐藏字段值是运行时生成；`_wpnonce` 值动态生成。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L58-L62】
  - 内联脚本会在提交后设置 `form.dataset.submitted = 'true'`，该 `data-submitted` 属性为运行时动态生成。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L185-L200】

## C) 资源加载方式（CSS/JS）

- 插件未看到 `wp_enqueue_style` / `wp_enqueue_script` 调用，`aegis-forms` 目录下也没有独立 CSS/JS 资源文件；前台仅使用 `render_form()` 内联 `<script>` 来防止重复提交。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L185-L203】
- 因为无 `enqueue`，不存在“仅在含短代码页面加载”或全站加载的条件逻辑。
- 当前无前台 CSS 文件、无作用域前缀策略（完全依赖 HTML 本身）。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L54-L203】

## D) 与主题 Info Sidebar 的协同点（设计约束建议）

> 本节仅给后续样式统一的“最小侵入策略”建议，不做任何代码改动。

- **推荐最小侵入方案**：后续若要新增/统一样式，最合适的是在插件内新增一个前台 CSS（如 `assets/public.css` 或 `assets/forms.css`），并在前台 `enqueue` 时使用稳定选择器作为作用域前缀，例如 `form[data-aegis-forms]` 或在主题布局容器内（如 `.aegis-info-layout form[data-aegis-forms]`）生效。当前表单没有 wrapper div，所以更推荐以 `form[data-aegis-forms]` 为根进行作用域约束。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L54-L58】
- **移动端断点建议**：与主题 Info Sidebar 对齐时，建议以 `@media (max-width: 720px)` 为关键断点（与主题 <=720px 对齐）。
- **统一风格所需标记点**：
  - 当前表单**缺少统一 wrapper class**（例如 `.aegis-forms`），若需要更精确的样式作用域，后续可考虑在 `<form>` 上添加固定 class（本步骤只记录，不改）。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L54-L58】
  - 字段 `<p>` 无 class，若后续需要栅格/布局控制，可能需要追加 field wrapper class（本步骤只记录）。【F:wp-content/plugins/aegis-forms/includes/class-aegis-forms-frontend.php†L67-L183】
