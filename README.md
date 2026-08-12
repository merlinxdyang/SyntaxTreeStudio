# Merlin's Syntax Studio

[简体中文](README.zh-CN.md)

Merlin's Syntax Studio is a free browser-based syntax tree generator for linguists, syntax instructors, and students. It converts bracket expressions into editable tree diagrams with movement links, empty positions, triangle roofs, annotations, manual layout controls, and publication-oriented exports.

- Online: <https://ailinguistics.cloud/mss>
- Current application version: **0.2.2**
- Developer: **Merlin X. D. Yang**
- License: [MIT](LICENSE)

## Current Features

### Tree editing

- Immediate preview from square-bracket or parenthesis notation.
- Visible indices such as `_i`, `_j`, and `_k` and hidden movement indices such as `_z1` and `_z2`.
- Independent solid/dashed styles, colors, visibility, and manual geometry for each movement link.
- Draggable labels, branches, movement links, and triangle roofs.
- Select any branch, hide it, and restore it later from the controls.
- Input undo/redo, buttons and trackpad pinch zoom, annotation colors, free notes, and three-point curves.
- Empty terminals, truly empty nodes, indexed empty movement positions, and fully silent nodes.

### Export

- SVG.
- Transparent PNG and white-background PNG.
- **Forest LaTeX** for structurally editable output.
- **Visual TikZ LaTeX** for preserving manual positions, hidden branches, line styles, colors, annotations, and curves.
- CJK-capable XeLaTeX output.

### Accounts, feedback, and administration

- Guest use without registration.
- Email registration and optional Google/GitHub OAuth configuration.
- The latest 20 generated trees are retained for signed-in users.
- A public Feedback BBS that anyone can read; only registered users can post.
- First-post review, administrator-only replies, editing, approval, recycle, restore, and permanent deletion.
- Duplicate, link-count, cooldown, daily-posting, registration, and login rate limits.
- Standalone administrator dashboard with all-time user, feedback, country, institution, and visitor data.
- Pagination options of 20, 40, or 100 rows.
- English, Simplified Chinese, Spanish, Japanese, and Korean application interfaces. Chinese, English, and Spanish are enabled by default on a new installation.

## Syntax Reference

| Syntax | Example | Effect |
|---|---|---|
| `[XP child child]` | `[TP John [T' T VP]]` | Basic node. The first item is the label and the remaining items are children. |
| `(...)` | `(TP John (VP ...))` | Parentheses can also express tree structure. |
| `A\|B\|C` | `T0\|\[+PST\]\|\[+3SG\]` | Line breaks inside one node. Literal square brackets must be escaped as `\[` and `\]`. |
| `_i`, `_j`, `_k` | `John_i` | Visible italic subscript; matching indices create a movement link. |
| `_z1`, `_z2` | `thought_z1 ... is_z2+phi` | Hidden movement index; it participates in linking without being displayed. |
| `t_i` / `trace_i` | `t_i` | Trace or gap label for movement. |
| `=word=` | `=read=_k` | Strikethrough. The older `-word-` form remains compatible. |
| `*word*` | `*where*_i` | Italic text. |
| `=*word*=` | `=*read*=_k` | Italic text with strikethrough. |
| `@word@` | `@he will go *where*@_i` | Outline text; inner italics are supported. |
| `"text with spaces"` | `"(head)"` / `"New York"` | Preserve spaces or brackets as one literal label. |
| `v0` / `X0` | `v0\|read_k`, `C0` | Display a superscript zero. |
| `X1`, `X2` | `DP1` | Display a trailing non-zero digit as a subscript. |
| `alpha`, `beta`, `phi` | `alpha_i`, `phi+thought_z1` | Greek-letter shortcuts; theta, lambda, omega, and others are also supported. |
| `[^TP words]` | `[^TP @he will go *where*@_i]` | Triangle/roof node. |
| `@empty` | `[TP @empty T']` | Keep an empty terminal branch without displaying text. |
| `[]`, `[_i]`, `[_z2]` | `[TP [] [_i] [_z2]]` | Create a true empty node or an indexed empty movement position. |
| `@silent` | `[C @silent]` | Hide the label completely and draw no branch to it. |

Example:

```text
[CP Which-book_i [C' C0|did [TP John_j [T' T0|\[+PST\] [vP =John=_j [v' v0|read_k [VP =read=_k t_i]]]]]]]
```

## Run Locally

Requirements:

- PHP 8.1 or newer.
- PDO SQLite, mbstring, and fileinfo extensions.
- A modern browser.

```bash
git clone https://github.com/merlinxdyang/SyntaxTreeStudio.git
cd SyntaxTreeStudio
php -S 127.0.0.1:8082
```

Open:

- Home: <http://127.0.0.1:8082/index.php>
- Workspace: <http://127.0.0.1:8082/index.php?action=workspace>
- Administrator: <http://127.0.0.1:8082/admin/admin.php>

SQLite is created automatically at `data/syntree.sqlite` on first access. Runtime databases and uploaded feedback images are excluded from version control.

The standalone administrator seeded on a new database is:

```text
Username: admin
Password: Admin123456
```

Change this password immediately from the administrator Security page before exposing the application outside local development.

## Validate the Code

```bash
find . -name '*.php' -type f -exec php -l {} \;
node --check app.js
node --check feedback.js
node --check landing.js
for test in tests/*.php; do php "$test"; done
```

The regression suite covers language defaults, editor features, dual export controls, administrator all-time pagination, the Feedback BBS security model, the generation counter, release notes, and responsive support-footer contracts.

## Project Structure

| Path | Purpose |
|---|---|
| `index.php` | Public router, landing page, authentication, Workspace, help, Feedback BBS, and localization. |
| `admin/admin.php` | Standalone private administrator dashboard and BBS moderation. |
| `app.js` | Parser, layout engine, editor interactions, movement links, and SVG/PNG/LaTeX export. |
| `feedback.js` | Safe Feedback BBS Markdown toolbar and attachment UI. |
| `landing.js` | Alipay support-dialog interaction. |
| `style.css` | Responsive public, Workspace, BBS, and administrator styles. |
| `src/db.php` | SQLite schema, migrations, queries, counters, throttles, and retention. |
| `src/auth.php` | Registration, email login, session handling, and authentication throttles. |
| `src/oauth.php` | Optional Google and GitHub OAuth flow. |
| `tests/` | Dependency-free PHP regression tests. |

## Version History

### Current `main` after 0.2.2 — 2026-07-18 onward

These changes are present on `main` but the application version constant has not yet been advanced beyond 0.2.2.

#### Added

- Public Feedback BBS: guests can read and registered users can post without exposing email addresses.
- First-post moderation and administrator-only official replies.
- Administrator editing, revision history, approval, soft deletion, restoration, and permanent deletion for feedback.
- Feedback and authentication anti-spam controls.
- Latest Update internal scrolling and compact developer/version/support footers on the home page and Workspace.
- Button-based Alipay QR dialog for the Chinese interface; PayPal link for other languages.

#### Changed

- Feedback opens as a dedicated page instead of a modal form.
- The administrator Overview contains feedback management.
- Administrator statistics use all-time data rather than a three-day window and paginate at 20, 40, or 100 rows.
- The standalone About page was retired; developer, version, and support information moved into page footers.

### 0.2.2 — 2026-07-17

#### Added

- Input undo/redo, trackpad pinch zoom, annotation colors, and a complete in-app syntax guide.
- True empty nodes with `[]`, `[_i]`, and `[_z2]`.
- Independent solid/dashed styles for each movement link.
- Selectable hidden branches with restoration controls.
- Separate Forest and geometry-preserving visual TikZ export modes.
- Site-wide generated-tree counter, incremented after an image or LaTeX download.

#### Fixed

- Literal brackets inside multiline labels now use `\[` and `\]`, for example `T0|\[+PST\]`.
- Empty indexed positions can receive movement arrows cleanly.
- Forest output remains structurally editable while visual TikZ preserves manual layout and styling.

### 0.2.1 — 2026-07-16

#### Added

- Complete Spanish interface and Spanish help content.
- Chinese, English, and Spanish as the default enabled language set.

#### Fixed

- Spanish pages no longer fall back to Korean strings when a translation is requested.

#### Changed

- Administrator Overview became the default dashboard view and incorporated recent feedback.
- Administrator user, feedback, country, institution, and visitor lists moved to all-time pagination with 20/40/100-row options.

### 0.2.0 — 2026-05-24

#### Changed

- Rebuilt the application as a dependency-light PHP and SQLite website and moved it to the repository root.
- Introduced the current MerlinSyntaxStudio landing page and responsive Workspace layout.

#### Added

- Guest mode, email accounts, optional OAuth, sessions, and the latest 20 saved trees per registered user.
- SQLite-backed administration and visitor statistics.
- SVG, white-background PNG, transparent PNG, and Forest LaTeX export.
- Hidden movement indices, strikethrough, italics, outline text, Greek shortcuts, multiline labels, and triangle roofs.
- English, Chinese, Japanese, and Korean interfaces.

#### Fixed

- Hidden movement-label rendering and tree alignment.

### 0.1.0 — 2026-04-26

#### Added

- Initial React/Vite browser application.
- Bracket-expression parsing and immediate syntax-tree preview.
- Automatic movement-link detection from matching indices.
- Triangle notation, per-element color editing, and individual branch styles.
- Image OCR input.
- Vector, transparent PNG, project JSON, and typesetting-code export.

The 0.1.0 React/Vite implementation was replaced by the PHP/SQLite architecture in 0.2.0; it remains available in Git history under tag [`v0.1.0`](https://github.com/merlinxdyang/SyntaxTreeStudio/tree/v0.1.0).

## Support

If Merlin's Syntax Studio helps your teaching, research, or writing, you can support the project through [PayPal](https://paypal.me/yxd76). The Chinese interface also provides an Alipay QR dialog.
