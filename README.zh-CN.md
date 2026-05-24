# Merlin 的句法树形图生成器

[English](README.md) | [日本語](README.ja.md) | [한국어](README.ko.md)

Merlin 的句法树形图生成器是一个免费的在线句法树绘制工具，面向语言学研究者、句法课教师和学生。它可以把括号表达式转换成清晰的句法树形图，并支持移位线、语迹/拷贝、多行标签、希腊字母、三角形/roof 标记和论文写作常用的导出格式。

在线使用：

https://ailinguistics.cloud/mss

## 功能

- 输入括号表达式后即时生成树形图。
- 支持 `_i`、`_j`、`_k` 等可见下标。
- 支持 `_z1`、`_z2` 等隐藏移位下标：不显示下标，但仍生成对应移位线。
- 使用 `=word=` 显示删除线。
- 使用 `*word*` 显示斜体。
- 使用 `@word@` 显示空心字。
- 支持 `alpha`、`beta`、`gamma`、`phi` 等希腊字母快捷输入。
- 支持 `[^TP @he will go *where*@_i]` 这样的三角形/roof 节点。
- 每条移位线可以单独显示/隐藏、调色和手动调整。
- 支持导出 SVG、白底 PNG、透明 PNG 和完整 Forest LaTeX 代码。
- 支持电子邮件、Google 和 GitHub 登录。
- 登录用户可以保存最近 20 条树形图生成记录。
- 支持访客模式：无需注册，不保存历史记录。
- 界面语言：英语、汉语、日语、韩语。

## 示例

```text
[CP PRN|Where_i [C' C0|is_z2+phi|\[+EPP\]|\[+WH\] [TP PRN|it [T' T0|=*is*=_z2 [vP v0|phi+thought_z1 [VP V0|=thought=_z1 [CP PRN|*where*_i [C' C0|that [^TP @he will go *where*@_i ]]]]]]]]]
```

## 常用语法

| 语法 | 显示效果 |
|---|---|
| `John_i` | `John` 加斜体下标 `i` |
| `John_z1` | `John`，隐藏移位下标 `z1` |
| `=read=` | `read` 加删除线 |
| `*where*` | 斜体 `where` |
| `=*read*=` | 斜体 `read` 加删除线 |
| `@John@` | 空心字 |
| `v0` | 斜体 `v` 加上标 `0` |
| `C0\|did` | 多行节点标签 |
| `alpha`、`beta`、`gamma`、`phi` | 希腊字母 |
| `[^TP words]` | 三角形/roof 节点 |

## 本地运行

```bash
php -S 127.0.0.1:8082
```

打开：

```text
http://127.0.0.1:8082/index.php
```

第一次访问时会自动创建 SQLite 数据库：`data/syntree.sqlite`。

默认本地管理员账号：

```text
Email: admin@syntree.local
Password: admin123456
```

正式部署前请务必修改默认密码。

## 文件结构

- `index.php`：应用路由、工作区、登录注册页面、使用说明、关于页面和管理员后台。
- `admin.php`：管理员入口别名。
- `app.js`：前端解析器、布局引擎、SVG/PNG/LaTeX 导出、移位线编辑和历史记录保存。
- `style.css`：响应式界面样式。
- `src/db.php`：SQLite 数据库结构、种子账号和历史记录保留逻辑。
- `src/auth.php`：电子邮件注册、登录和会话处理。
- `src/oauth.php`：Google/GitHub OAuth 流程。

## Buy Me a Coffee

如果这个工具对您的教学、研究或写作有帮助，可以请我喝一杯加浓美式。

支付宝：

![支付宝二维码](assets/alipay-coffee.jpeg)

## 许可证

MIT License。
