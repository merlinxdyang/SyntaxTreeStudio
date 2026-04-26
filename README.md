# Syntax Tree Studio

Syntax Tree Studio is a browser-based tool for creating clean syntax tree diagrams from bracket expressions. It supports automatic preview rendering, movement-link detection from matching indices, triangle notation, element-level color editing, image OCR input, and export to vector, transparent PNG, project JSON, and typesetting code.

Syntax Tree Studio 是一个基于浏览器的句法树绘制工具，可以通过括号表达式生成清晰的句法树图。它支持自动预览、根据相同下标识别移位连线、三角形标记、逐元素颜色编辑、图片 OCR 输入，以及导出矢量文件、透明 PNG、项目 JSON 和排版代码。

Version: 0.1.0  
Developed by Merlin Yang

版本：0.1.0  
开发者：Merlin Yang

## Features

Write a bracket expression and preview the rendered tree immediately. For example, `[CP Which-book_i [C' did [TP John [T' T [vP John [v' read [VP read t_i]]]]]]]` creates a tree with an automatically detected movement link between `Which-book_i` and `t_i`.

输入括号表达式后，页面会立即生成预览。例如，`[CP Which-book_i [C' did [TP John [T' T [vP John [v' read [VP read t_i]]]]]]]` 会生成一棵句法树，并自动识别 `Which-book_i` 与 `t_i` 之间的移位连线。

Use triangle notation by adding a caret to a phrase label, such as `[^TP ma bëgg t_i]`. The phrase label becomes the triangle header, and the children are shown under the triangle roof.

如果需要绘制三角形，可以在短语标签前加入插入号，例如 `[^TP ma bëgg t_i]`。短语标签会显示为三角形标题，子内容会显示在三角形下方。

Click text, branches, movement links, or triangle roofs in the preview to customize their colors. Branches can also be set to solid or dashed lines individually.

点击预览中的文字、树枝、移位连线或三角形边框，可以分别设置颜色。树枝还可以单独设置为实线或虚线。

## Windows Usage

On Windows, install Node.js first. Use Node.js 20.19 or newer, or Node.js 22.12 or newer. The easiest path is to download the LTS installer from the official Node.js website, then restart PowerShell so `node` and `npm` are available.

在 Windows 系统上，请先安装 Node.js。建议使用 Node.js 20.19 或更高版本，或者 Node.js 22.12 或更高版本。最简单的方法是从 Node.js 官方网站下载 LTS 安装包，安装完成后重新打开 PowerShell，让 `node` 和 `npm` 命令生效。

Open PowerShell, clone the repository, install dependencies, and start the local development server:

打开 PowerShell，克隆仓库、安装依赖，并启动本地开发服务器：

```powershell
git clone https://github.com/merlinxdyang/SyntaxTreeStudio.git
cd SyntaxTreeStudio
npm install
npm run dev
```

After the server starts, open the local URL shown in PowerShell. It is usually `http://127.0.0.1:5173/`.

服务器启动后，打开 PowerShell 中显示的本地网址。通常是 `http://127.0.0.1:5173/`。

To create a production build on Windows, run:

如果需要在 Windows 上生成生产版本，请运行：

```powershell
npm run build
```

## macOS Usage

On macOS, install Node.js first. You can use the official Node.js installer, Homebrew, or another Node version manager. Use Node.js 20.19 or newer, or Node.js 22.12 or newer.

在 macOS 系统上，请先安装 Node.js。你可以使用 Node.js 官方安装包、Homebrew，或者其他 Node 版本管理工具。建议使用 Node.js 20.19 或更高版本，或者 Node.js 22.12 或更高版本。

Open Terminal, clone the repository, install dependencies, and start the local development server:

打开终端，克隆仓库、安装依赖，并启动本地开发服务器：

```bash
git clone https://github.com/merlinxdyang/SyntaxTreeStudio.git
cd SyntaxTreeStudio
npm install
npm run dev
```

After the server starts, open the local URL shown in Terminal. It is usually `http://127.0.0.1:5173/`.

服务器启动后，打开终端中显示的本地网址。通常是 `http://127.0.0.1:5173/`。

To create a production build on macOS, run:

如果需要在 macOS 上生成生产版本，请运行：

```bash
npm run build
```

## How To Use The App

Enter or paste a bracket expression into the input box. Every phrase should begin with `[Label` and end with `]`; terminal words can be written directly inside their parent phrase.

在输入框中输入或粘贴括号表达式。每个短语都应以 `[标签` 开始，并以 `]` 结束；终端词可以直接写在父级短语内部。

Use matching indices such as `_i`, `_j`, or `_1` to mark movement relations. The app draws a movement link from a trace such as `t_i` to the matching indexed phrase.

使用 `_i`、`_j` 或 `_1` 这样的相同下标来标记移位关系。应用会从 `t_i` 这样的 trace 自动连到对应的同下标短语。

Use the upload button to run OCR on an image of a bracket expression. OCR works best with high-contrast, single-line images.

使用上传按钮可以对括号表达式图片进行 OCR 识别。OCR 对高对比度、单行文本图片效果最好。

Use the export buttons to save a vector file, transparent PNG, project JSON, or typesetting code. The project JSON preserves the source expression and styling choices.

使用导出按钮可以保存矢量文件、透明 PNG、项目 JSON 或排版代码。项目 JSON 会保留原始表达式和样式设置。

## Development

Install dependencies once with `npm install`, run the local server with `npm run dev`, and verify a production build with `npm run build`.

首次开发前运行 `npm install` 安装依赖，使用 `npm run dev` 启动本地服务器，并用 `npm run build` 检查生产构建是否正常。

The source code lives in `src/`. The main interface is in `src/App.tsx`, tree parsing is in `src/tree.ts`, layout logic is in `src/layout.ts`, and export helpers are in `src/exporters.ts`.

源代码位于 `src/` 目录中。主界面在 `src/App.tsx`，句法树解析在 `src/tree.ts`，布局逻辑在 `src/layout.ts`，导出工具函数在 `src/exporters.ts`。

## License

No license has been declared yet. Contact Merlin Yang before reusing or redistributing this project.

当前尚未声明开源许可证。如需复用或重新分发本项目，请先联系 Merlin Yang。
