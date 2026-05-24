# Merlin の Syntax Studio

[English](README.md) | [中文](README.zh-CN.md) | [한국어](README.ko.md)

Merlin の Syntax Studio は、言語学研究者、統語論の授業担当者、学生のための無料ブラウザベース統語樹生成ツールです。括弧表記を読み取り、見やすい統語樹に変換します。移動線、痕跡/コピー、複数行ラベル、ギリシア文字、三角形/roof 表記、論文や教材で使いやすいエクスポート形式に対応しています。

オンライン版：

https://ailinguistics.cloud/mss

## 主な機能

- 括弧表記を入力すると、統語樹を即時プレビュー。
- `_i`、`_j`、`_k` などの可視下付き文字。
- `_z1`、`_z2` などの非表示移動インデックス。下付き文字は表示せず、移動線だけを生成。
- `=word=` による取り消し線。
- `*word*` によるイタリック。
- `@word@` による袋文字。
- `alpha`、`beta`、`gamma`、`phi` などのギリシア文字ショートカット。
- `[^TP @he will go *where*@_i]` のような三角形/roof ノード。
- 移動線ごとの表示/非表示、色変更、手動調整。
- SVG、白背景 PNG、透明 PNG、完全な Forest LaTeX コードのエクスポート。
- メール、Google、GitHub ログイン。
- ログインユーザーは直近 20 件の生成履歴を保存可能。
- 登録不要のゲストモード。
- インターフェース言語：英語、中国語、日本語、韓国語。

## 例

```text
[CP PRN|Where_i [C' C0|is_z2+phi|\[+EPP\]|\[+WH\] [TP PRN|it [T' T0|=*is*=_z2 [vP v0|phi+thought_z1 [VP V0|=thought=_z1 [CP PRN|*where*_i [C' C0|that [^TP @he will go *where*@_i ]]]]]]]]]
```

## よく使う記法

| 記法 | 表示 |
|---|---|
| `John_i` | `John` にイタリックの下付き `i` |
| `John_z1` | `John`、非表示移動インデックス `z1` |
| `=read=` | `read` に取り消し線 |
| `*where*` | イタリックの `where` |
| `=*read*=` | イタリックの `read` に取り消し線 |
| `@John@` | 袋文字 |
| `v0` | イタリックの `v` に上付き `0` |
| `C0\|did` | 複数行ノードラベル |
| `alpha`、`beta`、`gamma`、`phi` | ギリシア文字 |
| `[^TP words]` | 三角形/roof ノード |

## ローカル実行

```bash
php -S 127.0.0.1:8082
```

開く URL：

```text
http://127.0.0.1:8082/index.php
```

初回アクセス時に SQLite データベース `data/syntree.sqlite` が自動作成されます。

ローカル管理者アカウント：

```text
Email: admin@syntree.local
Password: admin123456
```

ローカル開発以外で使う前に、必ずこのパスワードを変更してください。

## Buy Me a Coffee

このツールが授業、研究、執筆に役立った場合は、コーヒーでサポートできます。

https://paypal.me/yxd76

## ライセンス

MIT License。
