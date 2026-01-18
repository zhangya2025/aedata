# AEGIS-DEBUG.LOG Audit

## Executive Summary
1. 未在仓库内找到名为 `AEGIS-DEBUG.LOG`/`aegis-debug.log` 的现存日志文件；代码层面仅看到写入 `wp-content/aegis-debug.log` 的实现，需在运行环境确认是否已生成该文件。【F:wp-content/themes/aegis-themes/functions.php†L3-L22】
2. 实际写入源头为 `aegis_dbg_file()`：使用 `file_put_contents()` 追加到 `WP_CONTENT_DIR . '/aegis-debug.log'`，且仅在 `AEGIS_PLP_DEBUG` 为 true 时触发。【F:wp-content/themes/aegis-themes/functions.php†L3-L29】
3. 其它调试日志（如 `error_log(...)`）存在但未指定 AEGIS-DEBUG.LOG 路径，通常写入 PHP error_log/WP_DEBUG_LOG，不等同于 AEGIS 文件。【F:wp-content/themes/aegis-themes/inc/aegis-plp-filters.php†L40-L55】
4. 写入源头数量：**1 个**（`aegis_dbg_file`）。风险级别：**Medium**（依赖 AEGIS_PLP_DEBUG 开关，若误开会持续写入）。【F:wp-content/themes/aegis-themes/functions.php†L3-L29】

---

## 1) 日志文件本体

### 1.1 文件路径与存在性
- **预期路径（代码定义）**：`wp-content/aegis-debug.log`
  - Evidence: `wp-content/themes/aegis-themes/functions.php` L3-L16
- **仓库内实际文件**：未发现 `AEGIS-DEBUG.LOG`/`aegis-debug.log` 文件（需在运行环境确认是否生成）。
  - Evidence: 代码仅定义写入路径，无现存文件可引用。【F:wp-content/themes/aegis-themes/functions.php†L3-L16】

### 1.2 文件大小
- 当前仓库内无现存文件，因此无法报告大小；运行环境中请检查 `wp-content/aegis-debug.log`。
  - Evidence: 写入路径与机制见下文写入源头。【F:wp-content/themes/aegis-themes/functions.php†L3-L16】

---

## 2) 写入源头清单（核心）

### 2.1 `aegis_dbg_file()`（唯一明确写入 AEGIS 文件的源头）
- **文件路径**：`wp-content/themes/aegis-themes/functions.php`
- **行号**：L3-L16
- **代码片段**：`@file_put_contents( WP_CONTENT_DIR . '/aegis-debug.log', $line, FILE_APPEND );`
- **写入方式说明**：直接使用 `file_put_contents()` 追加到 `wp-content/aegis-debug.log`。
- **触发条件**：仅当 `AEGIS_PLP_DEBUG` 为 true；否则直接 return。
- **触发频率**：只要调用 `aegis_dbg_file()` 的位置被执行就写入。
  - Evidence: `wp-content/themes/aegis-themes/functions.php` L3-L29

### 2.2 其它日志（非 AEGIS-DEBUG.LOG，作为关联排查）
- **`aegis_plp_log()`** 使用 `error_log(...)`，未指定日志文件路径（写入 PHP error log/WP_DEBUG_LOG）。
  - Evidence: `wp-content/themes/aegis-themes/inc/aegis-plp-filters.php` L40-L55

> 结论：**只有 `aegis_dbg_file()` 明确写入 `wp-content/aegis-debug.log`。**

---

## 3) 清理与回滚流程（关闭源头 → 删除文件 → 验证）

### 步骤 1：关闭写入源头
- **关闭开关**：确保 `AEGIS_PLP_DEBUG` 为 `false`（或不定义该常量）。
  - 逻辑依据：`aegis_dbg_file()` 在 `AEGIS_PLP_DEBUG` 为 false 时直接 return。
  - Evidence: `wp-content/themes/aegis-themes/functions.php` L3-L7
  - 若环境中曾在 `wp-config.php` 或其他插件中定义该常量，请将其设为 false（不修改代码时可通过配置/环境变量控制）。

### 步骤 2：删除日志文件
- 删除 `wp-content/aegis-debug.log`（若存在）。
  - 该文件路径由代码硬编码决定。
  - Evidence: `wp-content/themes/aegis-themes/functions.php` L15

### 步骤 3：验证不再生成
- 触发一次相关页面请求（如 PLP 页面），然后检查 `wp-content/aegis-debug.log` 是否重新生成。
- 若未生成，说明写入源头已关闭；若仍生成，表示 `AEGIS_PLP_DEBUG` 仍被开启或有新的写入源头。
  - Evidence: 写入条件仍由 `AEGIS_PLP_DEBUG` 控制。【F:wp-content/themes/aegis-themes/functions.php†L3-L7】

---

## 附：写入机制判定（符合/不符合项）
- **ini_set('error_log', ...)**：未发现相关使用。
- **error_log(..., 3, 'AEGIS-DEBUG.LOG')**：未发现相关使用。
- **自定义 logger / shutdown handler**：未发现与 AEGIS 日志文件相关的实现。
- **第三方插件 / MU 插件**：未发现向 `AEGIS-DEBUG.LOG` 指定路径写入的实现。

> 若运行环境仍产生 `AEGIS-DEBUG.LOG`，建议补充运行时搜索（文件系统层面）确认是否有外部配置或非仓库代码写入。
