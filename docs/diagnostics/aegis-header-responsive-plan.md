# Aegis Header Responsive Plan (Read-Only Audit + Proposal)

## Executive Summary
- 当前 header 的滚动态（overlay/solid/hidden）仅在 `isHome` 页面启用，非首页不触发状态机，导致“全站菜单态不一致”。【F:wp-content/plugins/aegis-mega-header/view.js†L32-L88】
- JS 中断点判断使用 `window.innerWidth > 960`，CSS 使用 `max-width: 960`/`min-width: 961`；断点语义需收敛到“<=960 为移动”。【F:wp-content/plugins/aegis-mega-header/view.js†L36-L37】【F:wp-content/plugins/aegis-mega-header/style.css†L924-L1198】
- CSS 断点包含 1280/1100/960/640，分别作用于 mega panel 网格与移动头部布局，存在“中间态”（Tablet）但未在 JS 中显式区分。【F:wp-content/plugins/aegis-mega-header/style.css†L898-L1169】
- 方案建议：保留首页滚动态为增强层（overlay/hidden/solid），将“移动/桌面基础菜单态 + drawer 行为”扩展为全站统一，并以单一断点常量做 JS/CSS 一致性收敛。【F:wp-content/plugins/aegis-mega-header/view.js†L283-L452】【F:wp-content/plugins/aegis-mega-header/style.css†L924-L1198】

---

## 1) 当前 Header 状态机 / 断点 / 作用域

### 1.1 JS 中 isHome gating 的真实作用范围
- `isHome` 仅在 `home/front-page/page-id-49966` 时为 true；滚动态开关 `scrollBehaviorEnabled` 仅在 `isHome && window.innerWidth > 960` 时启用。
  - Evidence: `wp-content/plugins/aegis-mega-header/view.js` L32-L37, L283-L286
- `isHome` 决定 `is-home`/`mode-overlay`/`mode-solid`/`is-header-hidden` 的状态机是否运行；非首页时这些类不会被设置。
  - Evidence: `view.js` L79-L143

### 1.2 JS 中所有断点判断
- `window.innerWidth > 960`：控制滚动态启用与 resize 自动关闭 drawer。
  - Evidence: `view.js` L36-L37, L447-L451, L283-L286

### 1.3 CSS 断点及控制范围
- `@media (max-width: 1280px)`：调整 mega panel grid 布局与 promo 区块排布。
  - Evidence: `wp-content/plugins/aegis-mega-header/style.css` L898-L909
- `@media (max-width: 1100px)`：减少 mega columns 列数。
  - Evidence: `style.css` L912-L916
- `@media (max-width: 960px)`：切换移动头部显示（隐藏桌面主栏，显示移动 topbar/search/drawer）。
  - Evidence: `style.css` L924-L975
- `@media (max-width: 640px)`：缩小 logo 与工具栏布局。
  - Evidence: `style.css` L1168-L1186
- `@media (min-width: 961px)`：仅在桌面（且 is-home）固定 header。
  - Evidence: `style.css` L1189-L1198

### 1.4 是否存在中间态（Tablet）
- CSS 有 1100/1280 两个“中间态”，但 JS 不区分 tablet，仅以 960 作为移动/桌面切分。实际表现为：
  - 960 以下移动结构；
  - 961 以上桌面结构，但 961–1280 会逐步调整 mega panel grid。
  - Evidence: `style.css` L898-L916, L924-L975

---

## 2) 统一规则提案（不含代码）

### 2.1 断点与单一真源
- 设定唯一“移动阈值”：**<= 960 为移动，> 960 为桌面**。
- JS 使用单一常量（例如 `MOBILE_BREAKPOINT = 960`），CSS 保持 `max-width: 960` 与 `min-width: 961` 语义一致。

### 2.2 布局断点 vs 交互断点职责
- **布局断点（CSS）**：仅影响结构与排版（如移动 topbar/drawer、mega grid 列数）。
- **交互断点（JS）**：使用 `hover/pointer` 判定（Touch vs Mouse），决定 hover 进入与 click 进入策略。

### 2.3 全站生效策略
- **基础菜单态（全站）**：
  - 移动 drawer 开合、遮罩、锁滚动、resize 自动收口。
- **增强滚动态（可仅首页）**：
  - overlay/solid/hidden 仍可保留“首页限定”；非首页不启用滚动隐藏逻辑。

---

## 3) 最小改动实现方向（概述）

1. 在 `view.js` 引入单一断点常量与 `isMobile` 判定，替换所有 `window.innerWidth > 960` 判断。
2. 增加基于 `matchMedia('(hover: hover) and (pointer: fine)')` 的交互判定，确保 Touch 环境不依赖 hover 触发。
3. 保持 `isHome` 对滚动态的限定，仅扩展“移动菜单态”在全站统一执行。

