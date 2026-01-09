# AEGIS Sleepingbag Admin

## 温标字段
该插件在 WooCommerce 产品编辑页「产品数据」区域新增一个 **Sleeping Bag** 标签页，包含 3 个温标字段：

- `sleepingbag_limit_c`：Limit (°C)
- `sleepingbag_comfort_c`：Comfort (°C)
- `sleepingbag_extreme_c`：Extreme (°C)

字段仅接受数值（允许负数与一位小数，例如 `-5`、`-5.5`）。保存时会只保存纯数值；留空则删除对应的 post meta。

## Attribute/Term 同步器
后台 **Tools → Sleepingbag Attribute Sync** 提供 Dry-run 与 Sync：

- **Dry-run**：预览将新增的 attributes/terms。
- **Sync**：只创建缺失项，不修改、不重命名、不删除现有 attribute/term。

同步器通过 `config.php` 定义属性与 terms。

## 扩展配置
编辑 `config.php`，按照以下结构新增或调整属性：

```php
return [
    [
        'slug'  => 'sleepingbag_series',
        'name'  => 'Sleepingbag Series',
        'terms' => [ 'G Series' ],
    ],
];
```

- `slug`：属性 slug（不含 `pa_` 前缀）。
- `name`：属性显示名称。
- `terms`：需要补齐的 terms 列表。

每次执行同步时都会确保所有缺失项被补齐，重复执行不会产生重复 terms。
