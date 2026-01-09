# WooCommerce 前台颜色管理插件评估与实施方案

## 目标与结论
**可行性结论**：可通过“插件 + 颜色令牌（tokens）”的方式在 Woo 前台实现 80/20 的颜色覆盖，核心方式是：
1) 在 Woo 页面作用域内重设 Woo 的 CSS 变量（`--wc-*`）。
2) 对少数关键场景（如 Blocks 的 notice banner、classic notice）的选择器做兜底覆盖。

**高覆盖/低风险（约 80%）**
- Woo 核心变量：`--wc-green / --wc-red / --wc-orange / --wc-blue / --wc-primary / --wc-primary-text / --wc-secondary / --wc-secondary-text / --wc-content-bg / --wc-subtext` 等被广泛引用，可统一替换为黑白体系。Woo 核心 CSS 中已定义这些变量作为基础色板。`wp-content/plugins/woocommerce/assets/css/woocommerce.css`、`woocommerce-layout.css` 的 `:root` 中均有 `--wc-*` 定义。 【F:wp-content/plugins/woocommerce/assets/css/woocommerce.css†L1】【F:wp-content/plugins/woocommerce/assets/css/woocommerce-layout.css†L1】
- Blocks notice banner 的状态颜色使用独立 CSS 规则，覆盖成本低。`wc-blocks.css` 中对 `.wc-block-components-notice-banner` 的颜色定义明确。 【F:wp-content/plugins/woocommerce/assets/client/blocks/wc-blocks.css†L2】

**高风险/难以完全覆盖的场景**
- **Blocks 的部分组件使用硬编码颜色或内联样式**：例如某些 Blocks 可能在 JS 生成内联样式或通过 block attributes 注入颜色。此类需要针对具体 blocks 选择器逐一覆盖。
- **第三方插件自定义颜色**：会绕过 Woo 的 `--wc-*` 变量体系，需要单独适配。
- **主题级覆盖冲突**：此前主题已有对 cart success notice 的样式覆盖，现已迁移到插件统一管理，避免双源覆盖。 【F:wp-content/themes/aegis-themes/style.css†L1-L36】

---

## Step 1：盘点 Woo 样式体系与注入点
### 1.1 当前站点 cart 模板类型
- **Cart 为 Classic Cart 模板**：主题中存在 `woocommerce/cart/cart.php` 覆盖文件，并使用 `.woocommerce-cart-form` 的经典表单结构，说明当前站点 cart 是 classic cart 而非 blocks cart。 【F:wp-content/themes/aegis-themes/woocommerce/cart/cart.php†L1-L24】

### 1.2 Woo 核心变量与关键选择器
- Woo 核心 CSS `:root` 定义了默认色板变量（`--wc-green / --wc-red / --wc-primary` 等）。【F:wp-content/plugins/woocommerce/assets/css/woocommerce.css†L1】
- Blocks notice banner 在 `wc-blocks.css` 中定义了 `is-success / is-error / is-warning / is-info` 的背景与边框色。 【F:wp-content/plugins/woocommerce/assets/client/blocks/wc-blocks.css†L2】

### 1.3 现有主题/插件覆盖
- 主题内曾存在 cart 成功 notice 的样式覆盖，已移除以保持插件单一来源。【F:wp-content/themes/aegis-themes/style.css†L1-L36】

---

## Step 2：令牌（tokens） -> Woo 映射层 覆盖策略
### 2.1 颜色令牌（建议）
> 以下 tokens 体现黑白体系，可在 POC 先硬编码，后续扩展为后台配置。

- `--aegis-fg`: 主文字色
- `--aegis-bg`: 主背景色
- `--aegis-muted`: 次要文字色
- `--aegis-border`: 边框/分割线
- `--aegis-accent`: 强调色（主按钮/链接）
- `--aegis-link`: 链接色
- `--aegis-success-bg` / `--aegis-success-fg`
- `--aegis-danger-bg` / `--aegis-danger-fg`
- `--aegis-warning-bg` / `--aegis-warning-fg`
- `--aegis-info-bg` / `--aegis-info-fg`

### 2.2 “Woo 映射层”覆盖策略
**优先策略：重设 Woo 变量**
- 将 `--wc-*` 与 tokens 绑定，保证 Woo 的默认色板整体变为黑白体系。
- 变量来源：Woo core `:root` 中已定义 `--wc-*` 变量。 【F:wp-content/plugins/woocommerce/assets/css/woocommerce.css†L1】

**兜底策略：高优先级选择器覆盖**
- **Blocks 成功通知条（cart/checkout）**：`wc-blocks.css` 中 `is-success` 使用硬编码颜色，必须通过选择器覆盖。 【F:wp-content/plugins/woocommerce/assets/client/blocks/wc-blocks.css†L2】
- **Classic success notice**：`woocommerce-message` 类仍可能存在 classic cart/checkout 等页面，需要单独覆盖。

**为什么“只改变量”不够**
- Blocks 的 notice banner 在 `wc-blocks.css` 中直接写了 `background-color` 与 `border-color`，不会自动继承 `--wc-green`。因此必须用选择器覆盖（POC 已覆盖）。 【F:wp-content/plugins/woocommerce/assets/client/blocks/wc-blocks.css†L2】

### 2.3 Woo 映射层（tokens -> 变量/选择器）
| Token | Woo 变量/选择器映射 | 说明 |
| --- | --- | --- |
| `--aegis-accent` | `--woocommerce`, `--wc-primary` | Woo 主色（按钮/强调色）由变量重写统一收敛。 【F:wp-content/plugins/woocommerce/assets/css/woocommerce.css†L1】 |
| `--aegis-success-fg` | `--wc-green` | Woo 默认 success 色板变量。 【F:wp-content/plugins/woocommerce/assets/css/woocommerce.css†L1】 |
| `--aegis-danger-fg` | `--wc-red` | Woo 默认 error 色板变量。 【F:wp-content/plugins/woocommerce/assets/css/woocommerce.css†L1】 |
| `--aegis-warning-fg` | `--wc-orange` | Woo warning 色板变量。 【F:wp-content/plugins/woocommerce/assets/css/woocommerce.css†L1】 |
| `--aegis-info-fg` | `--wc-blue` | Woo info 色板变量。 【F:wp-content/plugins/woocommerce/assets/css/woocommerce.css†L1】 |
| `--aegis-success-bg` / `--aegis-success-fg` | `.wc-block-components-notice-banner.is-success` | Blocks 成功通知条背景/边框/图标需 selector override。 【F:wp-content/plugins/woocommerce/assets/client/blocks/wc-blocks.css†L2】 |
| `--aegis-success-bg` / `--aegis-success-fg` | `.woocommerce-message` | Classic cart/checkout success notice。 【F:wp-content/plugins/woocommerce/assets/css/woocommerce.css†L1】 |

---

## Step 3：插件技术方案（实现细节）
### 3.1 插件架构
```
wp-content/plugins/aegis-woo-color-manager/
├── aegis-woo-color-manager.php
├── assets/css/
│   ├── 00-tokens.css
│   ├── 10-woo-vars.css
│   ├── 20-notices.css
│   ├── 30-mini-cart.css
│   └── 31-cart-page.css
└── registry/overrides.json
```

### 3.2 CSS 注入方式
- 使用 `wp_enqueue_scripts` + `wp_enqueue_style` 按顺序加载拆分后的 CSS（tokens -> woo vars -> components -> pages）。
- 自动检测已注册的 Woo 样式 handle（如 `woocommerce-general`、`wc-blocks-style` 等），作为依赖确保加载顺序。
- CSS 版本号使用 `filemtime`，便于缓存刷新。

### 3.3 注入时机 & 依赖
- Hook: `wp_enqueue_scripts`（优先级 20）
- 依赖探测：`woocommerce-general`, `woocommerce-layout`, `woocommerce-smallscreen`, `wc-blocks-style` 等存在则挂载。

### 3.4 作用域策略
- **all_woo**：`body.woocommerce` / `.woocommerce` 容器作用域，避免污染非 Woo 页面。
- **cart_only**：`body.woocommerce-cart`。
- **mini_cart_only**：`.aegis-mini-cart__drawer`，不依赖 `body.woocommerce`。
- 确保不会影响 wp-admin。

### 3.5 可配置性
- **POC**：tokens 直接在 CSS 中硬编码。
- **中期扩展**：使用 Settings API 提供 tokens 配置 + 导出/导入能力（JSON）。

---

## Step 4：覆盖登记册（Registry）
- 新增 `registry/overrides.json`，每个覆盖必须登记 `id/component/scope/tokens_used/selectors/status/notes/last_verified`。
- 约定：新增或调整覆盖时，先更新 registry，再更新 CSS/逻辑，避免“忘记改哪里”。【F:wp-content/plugins/aegis-woo-color-manager/registry/overrides.json†L1-L76】

## Step 5：Debug/审计入口（按颜色值索引）
- WP-CLI 命令：`wp aegis-woo-color scan`
- 默认扫描路径：`wp-content/themes/aegis-themes`、`wp-content/plugins/aegis-*`，可加 `--include-woocommerce` 扫描 Woo core assets。
- 输出：JSON（默认）或 CSV；结果按颜色值聚合，记录 occurrences（file/line/snippet）。【F:wp-content/plugins/aegis-woo-color-manager/aegis-woo-color-manager.php†L54-L208】
- 输出位置：`wp-content/uploads/aegis-woo-color-manager/color-index.json`（或 `--output` 覆盖）。【F:wp-content/plugins/aegis-woo-color-manager/aegis-woo-color-manager.php†L142-L176】
- 注意：此入口仅用于审计/排查，不作为日常改色入口。

示例：
```
wp aegis-woo-color scan
wp aegis-woo-color scan --include-woocommerce
wp aegis-woo-color scan --format=csv --output=wp-content/uploads/aegis-woo-color-manager/color-index.csv
```

## Step 6：POC 插件交付
### 6.1 POC 内容
- 插件路径：`wp-content/plugins/aegis-woo-color-manager/`
- 前台注入分层 CSS，重设 Woo 变量 + 覆盖 blocks/classic notices。
- Cart 成功提示条覆盖：
  - 背景：浅绿 -> 浅灰
  - 边框/图标：绿色 -> 深灰/黑

### 6.2 Cart 成功提示条覆盖依据
- Blocks notice banner 在 `wc-blocks.css` 中对 `.wc-block-components-notice-banner.is-success` 写死背景/边框色。 【F:wp-content/plugins/woocommerce/assets/client/blocks/wc-blocks.css†L2】
- 主题中已移除针对 cart success notice 的重复覆盖，统一由插件管理。 【F:wp-content/themes/aegis-themes/style.css†L1-L36】

---

## 维护策略
1. **Woo 版本适配方式**
   - 每次 Woo 升级后，重新扫描 `woocommerce.css`、`woocommerce-layout.css`、`wc-blocks.css` 的变量与关键选择器变化。
2. **回归测试清单**
   - Cart：增减数量 -> 更新购物车 -> 成功提示条由绿变灰黑
   - Checkout：notice banner / payment errors
   - Product：价格、sale badge、评分星星
   - Account：通知条、按钮颜色
3. **第三方插件样式处理**
   - 若插件使用 Woo 变量，直接继承；否则通过映射层追加选择器覆盖。

---

## POC 验证步骤（用于 PR 描述）
1. 前台 cart 页面增减数量 -> 点击“更新购物车” -> 成功提示条从绿色变为黑白体系。
2. checkout/product 页面颜色整体仍可用。
3. wp-admin 不受影响。
4. mini cart 抽屉仍可正常显示且不依赖 Woo body class。
