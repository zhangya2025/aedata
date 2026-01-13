# 统一信息页模板（AEGIS Info Sidebar）

## 适用范围
该模板用于 About / Legal / Resources / Support 等信息页。所有页面只要选用 **AEGIS Info Sidebar** 模板，即可统一左侧目录与两栏布局样式。

## 如何给页面选择模板
1. 进入后台编辑页面（Page）。
2. 在右侧「页面」设置面板中找到「模板」。
3. 选择 **AEGIS Info Sidebar**。
4. 保存或更新页面。

## 如何通过 Order（menu_order）控制左侧目录顺序
左侧目录排序规则：
- 首先显示当前顶层父页（root）。
- 然后显示 root 的所有直接子页，按 `menu_order` 升序，若相同再按标题升序。

操作方式：
1. 进入子页编辑界面。
2. 在右侧「页面」设置面板里找到「排序」/「Order」。
3. 填入数字（例如 10、20、30...），数值越小排序越靠前。

## 新增子页的标准流程
1. 新建 Page。
2. 选择模板：**AEGIS Info Sidebar**。
3. 设置 Parent 为对应的父页（About / Legal / Resources / Support）。
4. 设置 Order（menu_order）为 10/20/30... 等。
5. 填写内容并发布。

## 移动端目录折叠说明
- 当屏幕宽度 <= 720px 时，左侧目录默认折叠。
- 点击「目录」按钮可展开/收起。
- 按钮会自动更新 `aria-expanded`，并通过 `aria-controls` 关联到目录列表。

## 一次性批量创建页面（Seeder 工具页）
入口：后台 **Tools → AEGIS Info Pages Seed**（仅超级管理员可见）。

使用方式：
1. 进入工具页后点击 **Create Info Pages** 按钮。
2. 系统会自动创建 About / Legal / Resources / Support 四个父页及全部子页，并套用模板。
3. 页面会写入“待完善/Content pending”占位内容，Support 相关页面会额外包含“表单区域占位”说明。

幂等说明：
- 若页面已存在，会复用并更新模板/层级/排序与占位内容，不会重复创建同名页面。
- 执行完成后会写入 `aegis_info_pages_seeded` 标记，工具页显示“已完成”并隐藏按钮，避免误触。

关闭/不再使用：
- 无需手工删除文件；工具页在已 seeded 状态下会自动禁用。
