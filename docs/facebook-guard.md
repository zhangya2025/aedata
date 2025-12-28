# Facebook App ID Guard (P2)

该 MU 插件 `wp-content/mu-plugins/wla-facebook-guard.php` 为 Elementor Pro 在校验 Facebook App ID 时访问 `graph.facebook.com` 提供在中国网络下的保护层，默认仅限制超时，不改变校验结果，可按需跳过以避免阻塞。

## 默认行为与开关
- 常量 `WLA_FACEBOOK_SKIP_VERIFY`（wp-config.php 中定义，默认为 `false`）
  - `false`（默认）：仅对 `graph.facebook.com` 请求设置约 4 秒 timeout，禁止重定向，保持原校验逻辑。
  - `true`：短路校验请求，返回空响应以避免长时间阻塞（可能导致校验失败提示，但不应致 fatal）。
- 可选日志：常量 `WLA_FACEBOOK_GUARD_LOG` 设为 `true` 时，动作写入 `wp-content/uploads/wla-logs/facebook-guard.log`。

## 行为说明
- 仅命中 `graph.facebook.com` 请求；其他外链不受影响。
- 通过 WordPress HTTP API 过滤器实现，无需修改主题/Elementor 源码。
- Skip 模式返回空 JSON 与 200 响应，目标是保证保存/校验流程可继续（可能提示验证失败，属于预期风险）。

## 验收步骤
1. 默认（skip=false）：在 Elementor Pro 设置中保存 Facebook App ID，确认请求超时限制为约 4 秒且流程不长时间卡住。
2. 启用 skip：`define( 'WLA_FACEBOOK_SKIP_VERIFY', true );`，重复保存，确认不会长时间等待；即便提示校验失败也不应阻塞保存。
3. 若启用日志：检查 `wp-content/uploads/wla-logs/facebook-guard.log` 包含 timeout 限制与 skip 记录。

## 回滚方式
- 删除或重命名 `wp-content/mu-plugins/wla-facebook-guard.php`；或
- 将 `define( 'WLA_FACEBOOK_SKIP_VERIFY', false );`（或移除该常量），恢复默认行为。
