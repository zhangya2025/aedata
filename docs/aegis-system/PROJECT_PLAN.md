# AEGIS-SYSTEM 规划对齐（基于最新业务决策）

## 目标声明（做什么 / 不做什么）

**做什么**
- 聚焦“撤销申请 pending 未冻结审批/出库”的高优先级风险，要求在所有推进型动作入口增加后端硬 guard，并记录审计事件。
- 收紧撤销审批 `cancel_decision` 权限：仓库员工禁止，且仅 HQ + 仓库管理员可审批。
- 完成审计缺口补齐与鉴权一致性收敛的规划，形成可拆分 PR 计划与验收用例。

**不做什么**
- **不调整角色体系**：现状保留 6 业务角色与现有 cap 矩阵，视为已确认需求，不列为 Gap。
- 不引入新订单状态、不迁移数据，仅使用现有 `meta.cancel` 字段。

## 范围内改动（P0 / P1 / P2）

### P0
1) **cancel pending 冻结推进动作**
   - 目标：当 `meta.cancel.requested=true` 且 `meta.cancel.decision=pending` 时，初审/付款审核/出库完成等推进动作必须被后端拒绝并写审计。
   - 证据（根因）：撤销申请仅写 meta，不变更状态；审批/出库入口仅做状态 guard。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L2073-L2178】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L804-L830】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1389-L1403】【F:wp-content/plugins/aegis-system/templates/portal/orders.php†L575-L650】【F:wp-content/plugins/aegis-system/includes/modules/class-shipments.php†L185-L214】

### P1
1) **cancel_decision 权限收紧**
   - 目标：仓库员工不能执行 `cancel_decision`；仅 HQ + 仓库管理员可审批。
   - 证据：当前 `cancel_decision` 使用 `read` + `can_approve_cancel()`，仓库用户在 `approved_pending_fulfillment` 可审批撤销。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L2221-L2268】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L382-L408】

### P2
1) **审计缺口补齐**
   - 目标：补齐撤回/提交确认等失败路径审计，统一输出 `ACCESS_DENIED` / `NONCE_INVALID` / `PARAMS_NOT_ALLOWED` 等事件。
   - 证据：`withdraw_order` 失败无审计、`submit_payment_confirmation` 失败缺少审计事件。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1881-L1932】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1324-L1384】
2) **下载鉴权分层一致性**
   - 目标：明确 REST 下载与网关鉴权责任，避免新增入口绕过网关。
   - 证据：REST permission_callback 仅排除“纯经销商”，细粒度仍由网关决定。【F:wp-content/plugins/aegis-system/includes/core/class-assets-media.php†L525-L533】【F:wp-content/plugins/aegis-system/includes/core/class-assets-media.php†L891-L950】

## 范围外改动
- **角色体系不改**（需求已确认）：保留 HQ/仓库管理员/仓库员工/经销商/销售/财务与现有 cap 矩阵。【F:wp-content/plugins/aegis-system/includes/core/class-roles.php†L183-L281】
- 不新增订单状态，不迁移历史数据。

## 风险清单
- **P0：cancel pending 未冻结审批/出库**（推进型动作可继续执行）。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L2073-L2178】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L804-L830】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1389-L1403】
- **P1：cancel_decision 越权**（仓库员工可审批撤销）。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L2221-L2268】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L382-L408】
- **P2：审计缺口**（失败/拒绝路径缺审计）。【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1881-L1932】【F:wp-content/plugins/aegis-system/includes/modules/class-orders.php†L1324-L1384】
- **P2：下载鉴权分层**（新增入口易绕过网关）。【F:wp-content/plugins/aegis-system/includes/core/class-assets-media.php†L525-L533】【F:wp-content/plugins/aegis-system/includes/core/class-assets-media.php†L891-L950】

## 交付物
- PR 列表（见“修改步骤/PR 计划”）。
- 回归用例清单：
  - cancel pending 时初审/付款审核/出库动作必须拒绝并写审计。
  - cancel_decision 权限：仓库员工拒绝，HQ/仓库管理员允许。
  - 关键失败路径审计覆盖（撤回/提交确认等）。

## Definition of Done（收尾验收标准）
- 所有推进型动作入口（初审、付款审核、出库完成/发货）后端都检查 cancel pending，并输出 `ACCESS_DENIED` + `reason_code=cancel_pending` 审计。
- cancel_decision 权限仅 HQ + 仓库管理员可执行；仓库员工提交返回 403/拒绝并写审计。
- 审计缺口补齐：关键失败路径均有可追溯事件。
- 不新增状态、不迁移数据、不调整角色体系。
