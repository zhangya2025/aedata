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

## 一次性批量创建页面（WP-CLI）
在服务器上执行以下命令即可批量创建父/子页面并自动套用模板：

```
wp aegis info-pages-setup
```

说明：
- 命令会为不存在的页面创建占位内容（含“待完善”提示）。
- 若页面已存在，将更新模板与层级/排序设置，但不会覆盖已有内容（除非内容为空）。
