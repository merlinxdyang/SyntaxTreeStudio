# Merlin's Syntax Studio

[中文](README.zh-CN.md) | [日本語](README.ja.md) | [한국어](README.ko.md)

Merlin's Syntax Studio is a free, browser-based syntax tree generator for linguists, syntax instructors, and students. It converts bracketed expressions into clean syntactic tree diagrams and supports movement links, traces/copies, multiline labels, Greek letters, triangle/roof notation, and publication-oriented exports.

Use it online:

https://ailinguistics.cloud/mss

## Features

- Interactive bracket-expression editor with immediate tree preview.
- Visible indices such as `_i`, `_j`, and `_k`.
- Hidden movement indices such as `_z1` and `_z2`, which generate movement links without displaying subscripts.
- Strikethrough copies with `=word=`.
- Italic text with `*word*`.
- Outline text with `@word@`.
- Greek-letter shortcuts such as `alpha`, `beta`, `gamma`, and `phi`.
- Triangle/roof notation with forms such as `[^TP @he will go *where*@_i]`.
- Adjustable movement links with individual show/hide controls and reusable color choices.
- Export to SVG, white-background PNG, transparent PNG, and complete Forest LaTeX code.
- Optional user accounts with email, Google, or GitHub login.
- Recent 20 saved trees for signed-in users.
- Guest mode with no registration and no saved history.
- Interface languages: English, Chinese, Japanese, and Korean.

## Example

```text
[CP PRN|Where_i [C' C0|is_z2+phi|\[+EPP\]|\[+WH\] [TP PRN|it [T' T0|=*is*=_z2 [vP v0|phi+thought_z1 [VP V0|=thought=_z1 [CP PRN|*where*_i [C' C0|that [^TP @he will go *where*@_i ]]]]]]]]]
```

## Syntax Reference

| Syntax | Display |
|---|---|
| `John_i` | `John` with italic subscript `i` |
| `John_z1` | `John`, with hidden movement index `z1` |
| `=read=` | `read` with strikethrough |
| `*where*` | italic `where` |
| `=*read*=` | italic `read` with strikethrough |
| `@John@` | outline text |
| `v0` | italic `v` with superscript `0` |
| `C0\|did` | multiline node label |
| `alpha`, `beta`, `gamma`, `phi` | Greek letters |
| `[^TP words]` | triangle/roof node |

## Run Locally

```bash
php -S 127.0.0.1:8082
```

Open:

```text
http://127.0.0.1:8082/index.php
```

SQLite is created automatically at `data/syntree.sqlite` on first request.

The backend seeds one local admin account:

```text
Email: admin@syntree.local
Password: admin123456
```

Change that password before using the app outside local development.

## Files

- `index.php`: app router, workspace, auth views, help page, about page, and admin dashboard.
- `admin.php`: admin alias.
- `app.js`: browser-side parser, layout engine, SVG/PNG/LaTeX export, movement editing, and history save.
- `style.css`: responsive interface styling.
- `src/db.php`: SQLite schema, seed account, and record retention.
- `src/auth.php`: email registration, login, and session handling.
- `src/oauth.php`: Google/GitHub OAuth flow.

## Buy Me a Coffee

If Merlin's Syntax Studio helps your teaching, research, or writing, you can support the project by buying me a strong Americano:

https://paypal.me/yxd76

## License

MIT License.
