# reCAPTCHA CN Fallback (P1)

本 MU 插件 `wp-content/mu-plugins/wla-recaptcha-guard.php` 为 Elementor Pro Forms 的 reCAPTCHA 校验在中国网络环境下提供保护层，默认不修改业务逻辑但限制超时，可按需降级。

## 开关与模式
- 常量 `WLA_RECAPTCHA_MODE`（wp-config.php 中定义，常量优先），允许值：
  - `strict`（默认）：保持原生校验，仅设置 4 秒 timeout / 禁止重定向。
  - `soft`：当请求 `https://www.google.com/recaptcha/api/siteverify` 超时/失败时，返回本地模拟成功响应（`success=true`，带 `wla_bypass` 标记），避免表单提交被阻断。
  - `off`：不做任何处理，恢复原行为。
- 常量 `WLA_RECAPTCHA_GUARD_LOG`：设为 `true` 时，将动作记录到 `wp-content/uploads/wla-logs/recaptcha-guard.log`。

## 行为说明
- 仅作用于 Google reCAPTCHA `siteverify` 校验请求；未命中不受影响。
- 前台及 AJAX/REST 表单提交会生效；常规 wp-admin 请求默认不拦截。
- soft 模式下的“绕过”存在业务风险：机器人表单可能通过 reCAPTCHA；日志会注明 bypass。

## 验收步骤
1. 默认（`strict`）：在国内网络发起 Elementor Pro 表单提交，`siteverify` 调用在约 4 秒内返回；若目标可达，保持原校验结果。
2. soft 模式：`define( 'WLA_RECAPTCHA_MODE', 'soft' );` 后，在阻断网络下提交表单，预期成功提交且日志出现 `recaptcha soft bypass triggered`。
3. off 模式：`define( 'WLA_RECAPTCHA_MODE', 'off' );`，恢复原始行为。

## 回滚方式
- 删除或重命名 `wp-content/mu-plugins/wla-recaptcha-guard.php`。
- 或在 `wp-config.php` 中设置 `define( 'WLA_RECAPTCHA_MODE', 'off' );`。
