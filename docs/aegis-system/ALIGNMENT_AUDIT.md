# AEGIS-SYSTEM 功能对齐审计（全量代码审计 + Gap List）

> 范围：`wp-content/plugins/aegis-system/`（含与其耦合的上传/Portal/订单/出库/经销商逻辑）。仅静态阅读与证据定位，不改代码。

## 功能地图（现状）

### 业务对象 / 状态机 / 关键动作

1) **订单（Order）**
- 状态机定义：`draft → pending_initial_review → pending_dealer_confirm → pending_hq_payment_review → approved_pending_fulfillment → shipped`，终态包括 `cancelled / cancelled_by_dealer / voided_by_hq`；并提供“逐级退回”映射规则用于 HQ 回退。证据：`AEGIS_Orders` 常量与 `get_prev_status_map()`。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L6-L45】
- 关键动作（Portal）
  - 下单/保存草稿/提交初审：`create_order|save_draft|submit_order`（写入订单、进入待初审或草稿）。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1745-L1783】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L574-L637】
  - 草稿编辑：`update_order`（仅草稿、仅归属经销商）。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1785-L1822】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L640-L709】
  - 草稿提交/撤回：`submit_draft`（草稿→待初审）、`withdraw_order`（待初审→草稿）。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1823-L1932】
  - 撤销（草稿）：`cancel_order`（草稿→cancelled_by_dealer）。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1933-L1959】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L711-L729】
  - 撤销申请/审批：`request_cancel` 写入 meta `cancel.*`；`cancel_decision` 通过后将订单更新为 `cancelled`。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L2073-L2218】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L2221-L2401】
  - 初审/退回：`save_review_draft|submit_initial_review`，并有状态 guard（必须待初审）。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L2413-L2440】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L804-L876】
  - 付款确认/审核：`submit_payment`（待确认→待审核），`review_payment`（待审核→待出库或退回待确认）。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L2628-L2718】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1324-L1465】
  - 作废与逐级退回：`void_order`（待初审/待确认/待审核→voided_by_hq），`rollback_step`（逐级退回）。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L2518-L2626】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L885-L1009】

2) **出库（Shipment）**
- 关键动作：创建出库单、扫码添加、防伪码入库、完成出库、删除出库单、导出与打印。Portal 入口统一在 `render_portal_panel()`。证据：Portal actions 与校验。【F:wp-content/plugins/aegis-system/includes/modules/class-shipments.php†L9-L164】

3) **经销商（Dealer）**
- 关键动作：经销商档案创建/更新、授权状态切换、营业执照上传（后台与 Portal 两条路径，均写入自建媒体表）。【F:wp-content/plugins/aegis-system/includes/modules/class-dealer.php†L520-L602】【F:wp-content/plugins/aegis-system/includes/modules/class-dealer.php†L1541-L1586】

4) **资产/媒体（Media）**
- 上传：自建媒体表 `aegis_media_files`（不入 WP 附件库），目录写入 `uploads/aegis-system/...`，并包含网关访问控制。证据：上传路径与写入、自建表与网关。【F:wp-content/plugins/aegis-system/includes/core/class-assets-media.php†L7-L110】【F:wp-content/plugins/aegis-system/includes/core/class-assets-media.php†L578-L620】【F:wp-content/plugins/aegis-system/includes/core/class-assets-media.php†L891-L1012】

5) **公开查询（Public Query）**
- 公开短码与后台查询入口；公开侧 nonce 校验但无角色限制（面向公众）。证据：`render_admin_page()` 与 `render_shortcode()`。【F:wp-content/plugins/aegis-system/includes/modules/class-public-query.php†L104-L220】

---

### 入口清单（含权限/nonce/白名单/state guard/失败返回）

> 备注：统一写入口校验器为 `AEGIS_Access_Audit::validate_write_request()`（cap、nonce、白名单、幂等）。【F:wp-content/plugins/aegis-system/includes/core/class-access-audit.php†L16-L81】

#### A. 后台菜单入口
- **后台菜单挂载**：`AEGIS_System::register_admin_menu()`，不同菜单按 cap 分配。
  - 根菜单：`aegis_access_root`；
  - 模块管理/全局设置：`aegis_manage_system`；
  - SKU/经销商/防伪码/查询：`aegis_manage_warehouse`；
  - 出库：`aegis_use_warehouse`；
  - 订单：`aegis_access_root`。证据：菜单注册与 cap 分配。【F:wp-content/plugins/aegis-system/includes/core/class-system.php†L220-L358】
- **后台访问拦截**：业务用户进入 admin 会被强制重定向 Portal。证据：`block_business_admin_access()`。【F:wp-content/plugins/aegis-system/includes/core/class-portal.php†L181-L206】

#### B. Portal 页面 & Shortcodes
- Portal 页面与短码：`[aegis_system_portal]`，统一入口 `AEGIS_Portal::render_portal_shortcode()`，并进行业务用户与经销商 guard。证据：Portal 短码渲染与 guard。【F:wp-content/plugins/aegis-system/includes/core/class-portal.php†L375-L487】
- 前台公共查询短码：`[aegis_query]`，nonce 校验，public 可访问。证据：`render_shortcode()`。【F:wp-content/plugins/aegis-system/includes/modules/class-public-query.php†L174-L240】
- 前台系统页面短码：`[aegis_system_page]`（入口注册）。证据：短码注册。【F:wp-content/plugins/aegis-system/includes/core/class-system.php†L167-L176】

#### C. REST Routes
- `POST /wp-json/aegis-system/v1/media/upload`
  - 权限：`assets_media` 模块开启 + 仓库管理权限。nonce: `_wpnonce`，action=`wp_rest`，白名单参数。失败返回 400/403。证据：【F:wp-content/plugins/aegis-system/includes/core/class-assets-media.php†L512-L614】
- `GET /wp-json/aegis-system/v1/media/download/{id}`
  - 权限：`assets_media` 模块开启 + 非“纯经销商”。未在 REST 层做更多细分，后续还会经过 media 网关鉴权。证据：【F:wp-content/plugins/aegis-system/includes/core/class-assets-media.php†L525-L533】【F:wp-content/plugins/aegis-system/includes/core/class-assets-media.php†L891-L974】
- `POST /wp-json/aegis-system/v1/media/cleanup`
  - 权限：`assets_media` 模块开启 + 系统管理权限。证据：【F:wp-content/plugins/aegis-system/includes/core/class-assets-media.php†L537-L545】

#### D. 下载/附件访问端点（Gateway）
- `/?aegis_media={id}`：通过 `template_redirect` 输出附件；需要通过 `stream_media()` 的可见性/角色/订单/经销商归属判断。失败 403/404。证据：query var 注册、媒体网关、鉴权与失败响应。【F:wp-content/plugins/aegis-system/includes/core/class-assets-media.php†L553-L573】【F:wp-content/plugins/aegis-system/includes/core/class-assets-media.php†L891-L1012】

#### E. Portal 表单入口（核心业务）
- **订单（Orders）Portal POST**（统一 nonce `aegis_orders_action` + 白名单 + cap）：
  - `create_order|save_draft|submit_order`: `aegis_orders_create`，白名单包含 `order_item_*`；状态：提交单进入 `pending_initial_review`。失败：错误提示 + redirect/留在页面。证据：【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1745-L1783】
  - `update_order`：仅草稿且经销商归属；失败记录 `ACCESS_DENIED`。证据：【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1785-L1822】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L640-L667】
  - `submit_draft` / `withdraw_order`：状态 guard（草稿/待初审）+ 处理锁。证据：【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1823-L1932】
  - `request_cancel`：`aegis_orders_create` + allowed_statuses；写入 `meta.cancel.*`，失败返回错误提示。证据：【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L2073-L2218】
  - `cancel_decision`：cap=read + `can_force_cancel`/`can_approve_cancel`，通过后把订单置为 `cancelled`。证据：【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L2221-L2401】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L374-L409】
  - `review_payment`：cap=`aegis_access_root` + 状态 guard（必须 `pending_hq_payment_review`）；失败记录审计。证据：【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L2694-L2718】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1389-L1403】
  - `submit_initial_review`：cap=`aegis_orders_initial_review` + 状态 guard（必须待初审）。证据：【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L2413-L2445】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L804-L830】
- **出库（Shipments）Portal POST**：`aegis_shipments_action` nonce + 白名单 + cap（出库 or 管理系统），失败返回错误提示。证据：【F:wp-content/plugins/aegis-system/includes/modules/class-shipments.php†L47-L103】
- **公开查询（Public Query）后台 POST**：nonce + cap=`aegis_manage_warehouse` + 白名单。证据：【F:wp-content/plugins/aegis-system/includes/modules/class-public-query.php†L121-L170】

---

## 角色与权限对齐检查（需求对齐）

### 需求：4 个角色（总部管理员、仓库管理员、仓库、经销商），经销商侧只保留 1 个角色

**现状角色定义（系统内置）**
- 业务角色共 6 个：HQ、仓库管理员、仓库员工、经销商、销售、财务。证据：`get_business_roles()` 与 `get_role_definitions()`。【F:wp-content/plugins/aegis-system/includes/core/class-roles.php†L183-L210】【F:wp-content/plugins/aegis-system/includes/core/class-roles.php†L218-L281】

**默认赋权（caps）**
- HQ：系统/仓库/出库/订单全权限（含审核与审计查看）。【F:wp-content/plugins/aegis-system/includes/core/class-roles.php†L220-L235】
- 仓库管理员：`aegis_manage_warehouse` + `aegis_use_warehouse` + `aegis_access_root`。【F:wp-content/plugins/aegis-system/includes/core/class-roles.php†L254-L261】
- 仓库员工：`aegis_use_warehouse` + `aegis_access_root`。【F:wp-content/plugins/aegis-system/includes/core/class-roles.php†L263-L269】
- 经销商：`aegis_access_root` + `aegis_reset_b` + `aegis_orders_create`。【F:wp-content/plugins/aegis-system/includes/core/class-roles.php†L271-L278】
- 销售、财务：订单查看/初审/付款审核等分权限。【F:wp-content/plugins/aegis-system/includes/core/class-roles.php†L237-L252】

**运行时鉴权**
- 角色辅助：`user_can_manage_system|user_can_manage_warehouse|user_can_use_warehouse|is_dealer_only` 等。证据：【F:wp-content/plugins/aegis-system/includes/core/class-roles.php†L65-L176】
- 订单可见性：`current_user_can_view_order()` 允许仓库/审核角色或同经销商查看。证据：【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1023-L1053】

### 对齐结论（Gap）
1) **角色体系不作为 Gap**：业务决策已确认保留 6 角色（HQ/仓库管理员/仓库员工/经销商/销售/财务）与现有 cap 矩阵，不做合并或删除。证据：业务角色清单与角色定义。【F:wp-content/plugins/aegis-system/includes/core/class-roles.php†L183-L281】
2) **经销商角色含 `aegis_access_root`**：该 cap 同时用于后台菜单与部分敏感动作 gate（例如订单管理菜单/取消动作）。虽有 Portal 重定向，但 cap 泛化仍需持续评估越权面。证据：经销商 cap 与菜单 cap 使用 `aegis_access_root`。【F:wp-content/plugins/aegis-system/includes/core/class-roles.php†L271-L278】【F:wp-content/plugins/aegis-system/includes/core/class-system.php†L253-L326】

### 越权风险路径（示例）
- **取消审批过宽**：`cancel_decision` 仅要求 `read` capability，并基于 `can_approve_cancel()` 的业务角色判断；而 `can_approve_cancel()` 在 `approved_pending_fulfillment` 状态下允许仓库用户审批撤销。若需求要求“撤销审批仅 HQ 或仓库管理员”，当前实现可被仓库员工执行。证据：`cancel_decision` cap 与 `can_approve_cancel()` 角色分支。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L2221-L2268】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L382-L408】

---

## 流程漏洞专项：cancel pending 未冻结审批/出库（高优先级）

### 代码推理复现路径（基于当前实现）
```
[经销商] request_cancel (待初审/待确认/待审核/待出库)
  -> 仅写入 meta.cancel.requested/decision=pending
  -> 订单 status 不变 (仍是待初审/待审核)
  -> 审核页面仍基于 status 渲染「初审/付款审核」操作
  -> 审核动作只检查 status，不检查 cancel_request 状态
```

**关键证据链**
1) 撤销申请仅写入 `meta.cancel.*`，不修改订单状态：`request_cancel` 分支更新 `cancel` meta 但不改变 `order.status`。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L2073-L2178】
2) 审核按钮展示条件只看 `order.status`：付款审核/初审表单仅依赖 `pending_hq_payment_review`/`pending_initial_review` 等状态渲染。撤销申请处于 pending 时不会禁用这些入口。证据：模板判断条件。【F:wp-content/plugins/aegis-system/templates/portal/orders.php†L575-L650】
3) 后端审核动作只做 `status` guard，不检查 `cancel_request` 是否 pending：
   - 初审：`review_order_by_hq()` 仅检查 `STATUS_PENDING_INITIAL_REVIEW`。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L804-L830】
   - 付款审核：`review_payment_by_hq()` 仅检查 `STATUS_PENDING_HQ_PAYMENT_REVIEW`。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1389-L1403】

### 结论
- **当前实现允许：cancel pending 时仍可进行初审/付款审核/出库推进动作**。根因是撤销申请只写入 meta，审批/出库入口仅校验订单状态。证据：撤销申请仅写 meta；审批与出库入口基于状态 guard 渲染与执行。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L2073-L2178】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L804-L830】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1389-L1403】【F:wp-content/plugins/aegis-system/templates/portal/orders.php†L575-L650】【F:wp-content/plugins/aegis-system/includes/modules/class-shipments.php†L185-L214】

### 最小修复建议（不写代码）
- **后端硬校验规则**（需补入审核/审核提交/出库等所有“批准类动作”入口）：
  - 初审/付款审核/出库执行必须满足：
    1) `order.status` 为对应阶段；
    2) `meta.cancel.requested` 不为 true 或 `meta.cancel.decision` 已是 `rejected`；
    3) `actor` 角色满足 HQ/仓库管理权限（按需求矩阵）。
  - 否则拒绝并写审计日志（`ACCESS_DENIED` + `reason_code=cancel_pending`）。
- **可能需要改的文件清单**：
  - `includes/modules/class-orders.php`：`review_order_by_hq()`、`review_payment_by_hq()`、`handle_portal_complete()`（出库完成）、`cancel_decision` 入口处增加统一 guard；
  - `templates/portal/orders.php`：前端按钮在 cancel pending 时灰化（前后端一致）。

---

## 附件存储方案对齐

### 需求：附件存到 `wp-content/uploads/aegis-system/` 独立分区

**现状**
- 上传根目录 `UPLOAD_ROOT = 'aegis-system'`，初始化结构与 .htaccess 禁止直链。证据：`ensure_upload_structure()`。【F:wp-content/plugins/aegis-system/includes/core/class-assets-media.php†L7-L43】
- 上传目录规则：`/uploads/aegis-system/{owner_type}/{YYYY}/{MM}`。证据：`build_upload_dir_filter()`。【F:wp-content/plugins/aegis-system/includes/core/class-assets-media.php†L95-L110】
- 业务证件/付款凭证上传均通过 `AEGIS_Assets_Media::handle_admin_upload()` 走统一路径并写入自建媒体表（不入 WP Attachment）。证据：经销商上传与付款凭证上传。【F:wp-content/plugins/aegis-system/includes/modules/class-dealer.php†L586-L600】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1206-L1263】
- 标签打印图依赖 `uploads/aegis-system/label-assets/label-title.png`（仍在 uploads 子树内）。证据：码标打印路径。【F:wp-content/plugins/aegis-system/includes/modules/class-codes.php†L977-L980】

**访问/下载鉴权**
- 统一网关 `/?aegis_media={id}`，基于可见性/角色/归属/订单访问权限判断；失败 403/404。证据：`stream_media()` 逻辑。【F:wp-content/plugins/aegis-system/includes/core/class-assets-media.php†L891-L1012】

**风险点（需评估）**
1) **下载鉴权分层不一致**：REST 下载仅拒绝“纯经销商”，更细粒度依赖 gateway 鉴权（同一请求链的责任分离）。若后续新增 bypass 路由，可能出现越权下载。证据：REST permission_callback 与 `stream_media()` 鉴权。【F:wp-content/plugins/aegis-system/includes/core/class-assets-media.php†L525-L533】【F:wp-content/plugins/aegis-system/includes/core/class-assets-media.php†L891-L950】
2) **文件名碰撞与 MIME 校验**：已使用 `wp_unique_filename` + `wp_check_filetype_and_ext`，风险相对可控，但未对 `owner_type` 做严格白名单，需关注滥用上传目录分区。证据：文件校验与命名逻辑。【F:wp-content/plugins/aegis-system/includes/core/class-assets-media.php†L85-L176】

---

## 审计与可观测性

### 审计表写入点
- 事件写入统一入口：`AEGIS_Access_Audit::log()`，表名 `aegis_audit_events`。证据：审计表常量与写入实现。【F:wp-content/plugins/aegis-system/includes/core/class-system.php†L11-L13】【F:wp-content/plugins/aegis-system/includes/core/class-access-audit.php†L113-L188】
- 关键动作已有事件：订单创建/更新/初审/付款审核、撤销申请/审批、经销商创建/更新等均使用 `record_event()`。证据示例：订单创建、撤销审批、付款审核。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L619-L628】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L2330-L2401】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1389-L1465】

### 缺口（审计不足/吞错）
1) **部分订单动作缺少失败/拒绝审计**：
   - `withdraw_order` 分支仅返回错误文本，未记录 `ACCESS_DENIED` 或失败事件（未见 `record_event` 逻辑）。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1881-L1932】
   - `submit_payment_confirmation()` 的 guard 调用未传入 `audit_event`，导致失败场景不记录拒绝事件；仅成功时记录 `ACTION_PAYMENT_CONFIRM_SUBMIT`。证据：guard 调用与成功审计。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1086-L1165】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1324-L1384】
2) **前端拦截但后端不记**：某些表单错误仅展示 `errors[]`（例如 `cancel_order` 非法订单），未统一写入审计。证据：订单取消分支仅记录 validation 失败，其他路径未记录。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1933-L1958】

---

## 一致性与债务清单（收尾）

### 重复实现/可合并校验器
- `orders` 与 `shipments` 入口均使用 `validate_write_request()`，但订单模块内部仍有零散权限/状态判断散布在模板与 handler（如撤销申请 pending 未进入统一 guard）。建议将“状态机 guard + cancel_pending guard”收敛到统一函数。证据：订单/出库入口都依赖同一 validator，但状态 guard 分散。【F:wp-content/plugins/aegis-system/includes/core/class-access-audit.php†L16-L81】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L2413-L2445】【F:wp-content/plugins/aegis-system/includes/modules/class-shipments.php†L47-L103】

### 死代码/未使用路由
- 当前未发现 `admin-ajax` 或 `admin_post` 入口；业务入口主要为 Portal 表单与 REST。若系统仍需后台 Ajax，应明确入口与审计策略。证据：入口注册列表中仅见 REST + shortcodes + template_redirect。【F:wp-content/plugins/aegis-system/includes/core/class-system.php†L167-L186】

### 需要补的测试点（仅列测试点）
1) 撤销申请 pending 时，初审/付款审核/出库是否拒绝并记录审计。
2) Cancel 决策权限：仓库员工是否被禁止（如需求限制到 HQ/仓库管理员）。
3) 下载网关：不同可见性文件的访问矩阵（HQ/仓库/经销商/销售）。
4) 角色降级：经销商仅一个角色时是否仍可完成订单创建与付款上传流程。

---

## Gap List（可执行差异清单）+ 风险分级

> 评级：P0=高危，P1=中危，P2=低危

### P0（高危）
1) **撤销申请 pending 仍可进行审批/出库**
   - 影响：撤销中的订单仍可被“批准/出库”，与业务流程冲突。
   - 证据链：撤销申请仅写 meta、不变更 status；审批入口仅按 status guard；模板仍渲染审批按钮。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L2073-L2178】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L804-L830】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1389-L1403】【F:wp-content/plugins/aegis-system/templates/portal/orders.php†L575-L650】

### P1（中危）
1) **撤销审批权限过宽（cap=read + warehouse staff 可通过）**
   - 影响：不符合“仅 HQ/仓库管理员审批撤销”的潜在需求。
   - 证据：`cancel_decision` 使用 `read` + `can_approve_cancel()`。仓库用户在 `approved_pending_fulfillment` 允许审批撤销。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L2221-L2268】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L382-L408】
2) **角色体系不对齐（需求确认：不改）**
   - 影响：不作为 Gap；需求确认保留 6 业务角色。证据：业务角色列表与 role 定义。【F:wp-content/plugins/aegis-system/includes/core/class-roles.php†L183-L281】

### P2（低危）
1) **审计覆盖不完整**
   - 影响：失败/拒绝操作缺乏可追溯事件。
   - 证据：`withdraw_order` 无审计、`submit_payment_confirmation` 失败无审计。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1881-L1932】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1324-L1384】
2) **REST 下载权限与网关权限分层**
   - 影响：权限职责分散，易在新增入口时遗漏鉴权。证据：REST permission_callback 与 `stream_media()` 鉴权逻辑分离。【F:wp-content/plugins/aegis-system/includes/core/class-assets-media.php†L525-L533】【F:wp-content/plugins/aegis-system/includes/core/class-assets-media.php†L891-L950】

---

## 建议收尾顺序（TODO，含目标/文件/验收点）

### P0
1) **阻断撤销申请 pending 的后续审批/出库**
   - 目标：撤销申请存在时，初审/付款审核/出库全部拒绝。
   - 涉及文件：`includes/modules/class-orders.php`、`templates/portal/orders.php`。
   - 验收点：撤销申请 pending 时，审批按钮灰化；后端审批/出库提交返回 `ACCESS_DENIED` 并写审计事件（`reason_code=cancel_pending`）。

### P1
2) **收敛撤销审批权限**
   - 目标：仅 HQ/仓库管理员可审批撤销，仓库员工不可审批。
   - 涉及文件：`includes/modules/class-orders.php`（`cancel_decision` 与 `can_approve_cancel`）。
   - 验收点：仓库员工提交 `cancel_decision` 返回 403 + `ACCESS_DENIED` 审计；HQ/仓库管理员成功。

3) **角色矩阵对齐（需求确认：不改）**
   - 目标：不作为收尾任务，保持现状角色矩阵。
   - 涉及文件：无。
   - 验收点：无（需求确认）。

### P2
4) **补齐审计覆盖**
   - 目标：订单核心写入口（撤回/提交确认/失败）全部记录审计事件。
   - 涉及文件：`includes/modules/class-orders.php`。
   - 验收点：所有失败路径均写入 `aegis_audit_events`（含 `ACCESS_DENIED`、`NONCE_INVALID`、`PARAMS_NOT_ALLOWED`）。
