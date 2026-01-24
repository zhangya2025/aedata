# PDF 标题中文乱码调查（可证据复现）

## 1) TCPDF 来源与能力确认

### 文件头/来源判定
目标文件：`wp-content/plugins/aegis-system/includes/third-party/tcpdf/tcpdf.php`。

文件头明确写着：

- “Minimal TCPDF-compatible shim for label printing.”
- “This is not a full TCPDF distribution; it implements only the methods used by AEGIS_Codes::handle_print().”

这已经直接表明它不是官方 TCPDF / tcpdf_min，而是**自定义 shim**。

### 能力探测脚本（临时，已删除）
为避免影响线上输出，我在 `wp-content/uploads/aegis-system/` 下创建了一个临时调试脚本：

- 临时脚本：`wp-content/uploads/aegis-system/debug-tcpdf.php`（已在提交前删除）
- 输出日志：`wp-content/uploads/aegis-system/pdf-debug.log`（已在提交前删除）
- 运行命令：

```bash
php wp-content/uploads/aegis-system/debug-tcpdf.php
```

探测内容（按要求逐条）：

- `method_exists($pdf, 'AddFont')`
- `method_exists($pdf, 'setFontSubsetting')`
- `class_exists('TCPDF_FONTS')`
- `method_exists($pdf, 'Image')`
- 同时补充了字体可读性验证：
  - `file_exists($fontPath)`
  - `is_readable($fontPath)`

探测结果（来自临时日志）：

| 探测项 | 结果 |
| --- | --- |
| `method_exists($pdf, 'AddFont')` | `false` |
| `method_exists($pdf, 'setFontSubsetting')` | `false` |
| `class_exists('TCPDF_FONTS')` | `false` |
| `method_exists($pdf, 'Image')` | `false`（修复前） |
| `file_exists(NotoSansSC-Regular.ttf)` | `true` |
| `is_readable(NotoSansSC-Regular.ttf)` | `true` |

**结论 1（来源与能力）：** 当前仓库内的 `tcpdf.php` 是自定义 shim，且缺失官方 TCPDF 关键 API（`TCPDF_FONTS` / `AddFont` / `setFontSubsetting` / `Image`）。

---

## 2) 复现 `SetFont(绝对路径TTF)` 是否生效

同样通过临时脚本完成（脚本已删除），核心步骤如下：

1. `require tcpdf.php`
2. `$pdf = new TCPDF(...)`
3. `AddPage()`
4. `SetFont($fontPath, '', 14)`
5. `Cell(..., 'Title EN / 中文标题', ...)`
6. `Output()` 到文件

### 证据 A：字体文件存在且可读
临时探测结果显示：

- `file_exists($fontPath) = true`
- `is_readable($fontPath) = true`

这可以排除“路径拼错 / 权限不可读”的方向（根因 B）。

### 证据 B：输出 PDF 仅声明 Helvetica
我对生成的最小 PDF 做了可复现检查：

```bash
strings wp-content/uploads/aegis-system/debug.pdf | head -n 40
```

关键片段显示：

- 资源字体为：`/BaseFont /Helvetica`
- 文本绘制使用：`/F1 ... Tf`

且没有任何 TTF 或 Unicode 字体嵌入痕迹。

### 证据 C：shim 的实现方式决定了不会加载 TTF
shim 中：

- `SetFont()` 只记录字号，不处理字体族/字体文件。
- `buildPdf()` 写死了 `Helvetica`：
  - `<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>`
- 不存在 `TCPDF_FONTS` / `AddFont` / `setFontSubsetting`。

**结论 2（SetFont 生效性）：** `SetFont($fontPath)` 在当前 shim 中不会真正加载 TTF，只是“看起来调用成功”，实际仍使用 Helvetica 渲染。

---

## 3) 根因判定（基于证据）

### 判定结果：根因 A（并兼具 C 的表现）

在给定选项中，我做出如下判定：

- ✅ **根因 A：shim 不支持 TTF 直载/不支持 Unicode 字体。**
  - 证据：关键 API 全部缺失（探测为 `false`）。
  - 证据：`buildPdf()` 写死 Helvetica。
  - 证据：`SetFont($fontPath)` 不会改变最终字体资源。
- ⛔ 根因 B：已被 `file_exists/is_readable = true` 排除。
- ⚠️ 根因 C：也成立（官方 TCPDF 确实需要字体定义），但在本仓库里**更本质的阻塞点是 shim 本身不是官方 TCPDF**，因此优先归类为 A。

**一句话根因：** 现有 `tcpdf.php` 是极简 shim，无法加载/嵌入 Unicode TTF 字体，中文最终被交给 Helvetica（或非 Unicode 路径）渲染，出现乱码/缺字。

---

## 修复方案选择：B（有证据的最小可落地方案）

### 为什么没有选 A（优先方案）

方案 A 要求：

- 用官方 `tcpdf_min` 替换 shim。
- 使用 `TCPDF_FONTS::addTTFfont()` + `setFontSubsetting(true)`。

但在当前环境中：

- 外网下载官方 TCPDF 资源被阻断（`curl` 返回 403）。
- 仓库内也不存在官方 TCPDF 代码可直接复用。

因此**无法在“所有文件入库且不依赖外网”的前提下完成 A**。

### 方案 B 的落地实现（本 PR 采取的方案）

方案 B 的目标是：不依赖 TTF 字体机制，而是将标题改为位图渲染。

本 PR 的具体落地方式：

1. 为 shim 增加 `Image()` 能力（最小可用）：
   - 仅支持 PNG。
   - 通过 GD 读取 PNG，并把深色像素栅格化为多个小矩形（复用已有 `drawRect()` 能力）。
2. `handle_print()` 优先使用标题图片：
   - 若 `Image()` 存在且 uploads 外置路径可读：
     - 直接在标题区域绘制该 PNG。
   - 否则回退到英文标题文本逻辑。

### 二进制不入库交付规范（新增）

- PNG 文件不随 PR 提交（遵循 GitHub 规则：二进制不入库）。
- 由用户在服务器手工上传到：
  - `wp-content/uploads/aegis-system/label-assets/label-title.png`
- 图片规格建议：
  - 物理尺寸：`60mm × 4mm`
  - 分辨率：`300DPI`
  - 像素尺寸约：`709 × 47px`
- 若文件缺失：系统自动回退英文标题 `Anti-counterfeit code`，不影响条码打印。
- 验证步骤：
  1. 上传 PNG 到上述 uploads 路径。
  2. 重新导出 PDF。
  3. 标题应显示中文且不乱码。

---

## 验收步骤（可操作清单）

> 目标：Chrome 打开 PDF 中文正常/不乱码；条码可扫；打印 100% 尺寸。

### 验收 1：功能路径

1. 进入“防伪码批次”列表。
2. 对一个批次执行“打印标签”。
3. 浏览器打开 PDF。

预期：

- 标题区域以图片方式渲染（不会再走 Helvetica 的中文路径）。
- 条码与底部编码布局不变。

### 验收 2：中文标题

1. 用你们本地工具生成中文标题 PNG（按下述规格或更高分辨率）。
   - 文案建议：`防伪码  Anti-counterfeit code`
   - 字体：`assets/fonts/NotoSansSC-Regular.ttf`
2. 上传到服务器 uploads 路径：
   - `wp-content/uploads/aegis-system/label-assets/label-title.png`
3. 重新打印标签。

预期：

- 中文标题清晰可见且不乱码。

### 验收 3：条码可扫

1. 用扫码枪/手机扫描条码。
2. 校验扫描值与底部 `XXXX-XXXX-...` 一致。

### 验收 4：打印尺寸

1. 在打印对话框中：
   - 缩放：100%
   - 关闭“适应页面/fit to page”
2. 实物测量标签尺寸接近 60mm x 30mm。

---

## 复现/调查用到的命令（供审计）

```bash
rg "class-codes|handle_print|tcpdf" wp-content/plugins/aegis-system -n
sed -n '1,120p' wp-content/plugins/aegis-system/includes/third-party/tcpdf/tcpdf.php
php wp-content/uploads/aegis-system/debug-tcpdf.php
sed -n '1,120p' wp-content/uploads/aegis-system/pdf-debug.log
strings wp-content/uploads/aegis-system/debug.pdf | head -n 40
```

（其中 uploads 目录下的调试脚本与产物均已在提交前删除。）
