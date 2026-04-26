import { forwardRef, useMemo, useRef, useState } from 'react';
import type { ChangeEvent, Dispatch, MouseEvent as ReactMouseEvent, SetStateAction } from 'react';
import {
  Braces,
  Clipboard,
  Download,
  FileCode2,
  ImageDown,
  Palette,
  ScanText,
  RotateCcw,
  Save,
} from 'lucide-react';
import { createWorker, PSM } from 'tesseract.js';
import { downloadSvg, downloadText, downloadTransparentPng } from './exporters';
import { layoutTree } from './layout';
import {
  detectMovementLinks,
  getTriangleText,
  isTriangleNode,
  parseBracketTree,
  parseLabelParts,
  splitIndexedLabel,
  toForestLatex,
} from './tree';
import type { MovementLink } from './tree';

const SAMPLE = `[CP Which-book_i [C' did [TP John [T' T [vP John [v' read [VP read t_i]]]]]]]`;
const APP_VERSION = '0.1.0';
type LineStyle = 'solid' | 'dashed';
type UsageLanguage = 'en' | 'zh';
type SelectableElement =
  | { type: 'text'; id: string; label: string }
  | { type: 'branch'; id: string; label: string }
  | { type: 'movement'; id: string; label: string }
  | { type: 'roof'; id: string; label: string };

type ColorMap = Record<string, string>;
type BranchStyleMap = Record<string, LineStyle>;

const DEFAULT_TEXT_COLOR = '#050505';
const DEFAULT_LINE_COLOR = '#0f172a';
const FIXED_PALETTE_COLORS = ['#050505', '#f41010', '#2563eb'];

export function App() {
  const [source, setSource] = useState(() => localStorage.getItem('syntax-tree-source') ?? SAMPLE);
  const [branchStyle, setBranchStyle] = useState<LineStyle>('solid');
  const [movementStyle, setMovementStyle] = useState<LineStyle>('dashed');
  const [showMovement, setShowMovement] = useState(true);
  const [copied, setCopied] = useState(false);
  const [usageLanguage, setUsageLanguage] = useState<UsageLanguage>('en');
  const [ocrStatus, setOcrStatus] = useState<string | null>(null);
  const [selectedElement, setSelectedElement] = useState<SelectableElement | null>(null);
  const [textColors, setTextColors] = useState<ColorMap>({});
  const [branchColors, setBranchColors] = useState<ColorMap>({});
  const [branchStyles, setBranchStyles] = useState<BranchStyleMap>({});
  const [movementColors, setMovementColors] = useState<ColorMap>({});
  const [roofColors, setRoofColors] = useState<ColorMap>({});
  const [customPaletteColors, setCustomPaletteColors] = useState<Array<string | null>>(
    Array.from({ length: 7 }, () => null),
  );
  const [pendingPaletteSlot, setPendingPaletteSlot] = useState<number | null>(null);
  const svgRef = useRef<SVGSVGElement | null>(null);
  const paletteInputRef = useRef<HTMLInputElement | null>(null);

  const parsed = useMemo(() => parseBracketTree(source), [source]);
  const movementLinks = useMemo(() => detectMovementLinks(parsed.tree), [parsed.tree]);
  const visibleMovementLinks = showMovement ? movementLinks : [];
  const layout = useMemo(() => (parsed.tree ? layoutTree(parsed.tree) : null), [parsed.tree]);
  const latex = useMemo(
    () => toForestLatex(parsed.tree, visibleMovementLinks),
    [parsed.tree, visibleMovementLinks],
  );

  const handleSaveProject = () => {
    localStorage.setItem('syntax-tree-source', source);
    downloadText(
      'syntax-tree-project.json',
      JSON.stringify({ source, textColors, branchColors, branchStyles, movementColors, roofColors }, null, 2),
      'application/json',
    );
  };

  const handleSvg = () => {
    if (svgRef.current) downloadSvg('syntax-tree.svg', svgRef.current);
  };

  const handlePng = async () => {
    if (svgRef.current) await downloadTransparentPng('syntax-tree-transparent.png', svgRef.current);
  };

  const handleLatex = () => {
    downloadText('syntax-tree-forest.tex', latex, 'text/x-tex;charset=utf-8');
  };

  const handleCopyLatex = async () => {
    await navigator.clipboard.writeText(latex);
    setCopied(true);
    window.setTimeout(() => setCopied(false), 1400);
  };

  const selectedColor = getSelectedColor(
    selectedElement,
    textColors,
    branchColors,
    movementColors,
    roofColors,
  );

  const handleSelectedColorChange = (color: string) => {
    if (!selectedElement) return;
    updateColorMap(selectedElement, color, {
      setTextColors,
      setBranchColors,
      setMovementColors,
      setRoofColors,
    });
  };

  const handlePaletteColor = (color: string) => {
    handleSelectedColorChange(color);
  };

  const handleCustomPaletteSlot = (slotIndex: number) => {
    const color = customPaletteColors[slotIndex];
    if (color) {
      handlePaletteColor(color);
      return;
    }
    setPendingPaletteSlot(slotIndex);
    paletteInputRef.current?.click();
  };

  const handleCustomPalettePick = (color: string) => {
    if (pendingPaletteSlot === null) return;
    setCustomPaletteColors((current) => {
      const next = [...current];
      next[pendingPaletteSlot] = color;
      return next;
    });
    handlePaletteColor(color);
    setPendingPaletteSlot(null);
  };

  const handleSelectedColorReset = () => {
    if (!selectedElement) return;
    updateColorMap(selectedElement, null, {
      setTextColors,
      setBranchColors,
      setMovementColors,
      setRoofColors,
    });
  };

  const handleSelectedBranchStyle = (style: LineStyle) => {
    if (selectedElement?.type !== 'branch') return;
    setBranchStyles((current) => ({
      ...current,
      [selectedElement.id]: style,
    }));
  };

  const handleSelectedBranchStyleReset = () => {
    if (selectedElement?.type !== 'branch') return;
    setBranchStyles((current) => {
      const next = { ...current };
      delete next[selectedElement.id];
      return next;
    });
  };

  const handleImageUpload = async (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;
    setOcrStatus('Preprocessing image...');
    try {
      const image = await preprocessOcrImage(file);
      setOcrStatus('Recognizing image...');
      const worker = await createWorker('eng', undefined, {
        logger: (message) => {
          if (message.status === 'recognizing text') {
            setOcrStatus(`Recognizing image... ${Math.round(message.progress * 100)}%`);
          }
        },
      });
      await worker.setParameters({
        tessedit_pageseg_mode: PSM.SINGLE_LINE,
        tessedit_char_whitelist:
          "[]ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_'′^ -",
        preserve_interword_spaces: '1',
      });
      const result = await worker.recognize(image);
      await worker.terminate();
      const cleaned = normalizeOcrText(result.data.text);
      setSource(cleaned);
      setOcrStatus('Recognition complete. The input has been filled.');
    } catch (error) {
      setOcrStatus(error instanceof Error ? `Recognition failed: ${error.message}` : 'Recognition failed.');
    } finally {
      event.target.value = '';
    }
  };

  return (
    <main className="app-shell">
      <section className="workspace">
        <aside className="control-panel" aria-label="Syntax tree controls">
          <div className="brand">
            <div>
              <h1>Syntax Tree Studio</h1>
              <p>Version {APP_VERSION} · Developed by Merlin Yang</p>
            </div>
          </div>

          <label className="field">
            <span>Bracket expression</span>
            <textarea
              value={source}
              onChange={(event: ChangeEvent<HTMLTextAreaElement>) => setSource(event.target.value)}
              spellCheck={false}
              aria-label="Bracket expression input"
            />
          </label>

          <label className="upload-control">
            <ScanText size={17} />
            Upload expression image
            <input type="file" accept="image/*" onChange={handleImageUpload} />
          </label>

          {ocrStatus ? <div className="notice neutral">{ocrStatus}</div> : null}

          {parsed.error ? (
            <div className="notice error" role="alert">
              {parsed.error}
            </div>
          ) : (
            <div className="notice">
              Found {layout?.nodes.length ?? 0} nodes and {movementLinks.length} movement links.
            </div>
          )}

          <div className="settings-grid" aria-label="Line style settings">
            <div className="setting-row">
              <span>Branch style</span>
              <LineStyleToggle value={branchStyle} onChange={setBranchStyle} />
            </div>
            <div className="setting-row">
              <span>movement</span>
              <LineStyleToggle value={movementStyle} onChange={setMovementStyle} />
            </div>
            <label className="setting-row checkbox-row">
              <span>Show movement links</span>
              <input
                type="checkbox"
                checked={showMovement}
                onChange={(event) => setShowMovement(event.target.checked)}
              />
            </label>
          </div>

          <section className="color-panel" aria-label="Element color controls">
            <div className="section-title">
              <Palette size={17} />
              <h2>Colors</h2>
            </div>
            {selectedElement ? (
              <>
                <div className="selected-target">
                  <span>{selectionTypeLabel(selectedElement.type)}</span>
                  <strong>{selectedElement.label}</strong>
                </div>
                <div className="palette-grid" aria-label="Color palette">
                  {FIXED_PALETTE_COLORS.map((color) => (
                    <button
                      key={color}
                      type="button"
                      className={color.toLowerCase() === selectedColor.toLowerCase() ? 'active' : ''}
                      style={{ background: color }}
                      title={color}
                      aria-label={`Use ${color}`}
                      onClick={() => handlePaletteColor(color)}
                    />
                  ))}
                  {customPaletteColors.map((color, index) => (
                    <button
                      key={`custom-${index}`}
                      type="button"
                      className={[
                        'custom-swatch',
                        color ? 'filled' : 'empty',
                        color?.toLowerCase() === selectedColor.toLowerCase() ? 'active' : '',
                      ].join(' ')}
                      style={color ? { background: color } : undefined}
                      title={color ?? 'Choose color'}
                      aria-label={color ? `Use ${color}` : 'Choose custom color'}
                      onClick={() => handleCustomPaletteSlot(index)}
                    />
                  ))}
                  <input
                    ref={paletteInputRef}
                    className="hidden-color-input"
                    type="color"
                    value={selectedColor}
                    onChange={(event) => handleCustomPalettePick(event.target.value)}
                  />
                </div>
                <button type="button" className="reset-color-button" onClick={handleSelectedColorReset}>
                  Reset to default color
                </button>
                {selectedElement.type === 'branch' ? (
                  <div className="branch-style-panel">
                    <span>Selected branch style</span>
                    <LineStyleToggle
                      value={branchStyles[selectedElement.id] ?? branchStyle}
                      onChange={handleSelectedBranchStyle}
                    />
                    <button type="button" className="reset-color-button" onClick={handleSelectedBranchStyleReset}>
                      Use default style
                    </button>
                  </div>
                ) : null}
              </>
            ) : (
              <p className="helper-text">Select text, branches, movement links, or triangles in the preview to set colors.</p>
            )}
          </section>

          <div className="button-grid">
            <button type="button" onClick={() => setSource(SAMPLE)}>
              <RotateCcw size={17} />
              Load sample
            </button>
            <button type="button" onClick={handleSaveProject}>
              <Save size={17} />
              Save project
            </button>
            <button type="button" onClick={handleSvg} disabled={!layout}>
              <Download size={17} />
              Vector
            </button>
            <button type="button" onClick={handlePng} disabled={!layout}>
              <ImageDown size={17} />
              Transparent PNG
            </button>
            <button type="button" onClick={handleLatex} disabled={!latex}>
              <FileCode2 size={17} />
              Typesetting code
            </button>
            <button type="button" onClick={handleCopyLatex} disabled={!latex}>
              <Clipboard size={17} />
              {copied ? 'Copied' : 'Copy code'}
            </button>
          </div>

          <section className="latex-panel" aria-label="typesetting code output">
            <div className="section-title">
              <Braces size={17} />
              <h2>Typesetting Code</h2>
            </div>
            <pre>{latex || 'Typesetting code appears after a valid parse.'}</pre>
          </section>

          <section className="usage-panel" aria-label="Usage instructions">
            <div className="usage-header">
              <div className="section-title">
                <Braces size={17} />
                <h2>Usage</h2>
              </div>
              <div className="usage-toggle" role="group" aria-label="Usage language">
                <button
                  type="button"
                  className={usageLanguage === 'en' ? 'active' : ''}
                  onClick={() => setUsageLanguage('en')}
                >
                  EN
                </button>
                <button
                  type="button"
                  className={usageLanguage === 'zh' ? 'active' : ''}
                  onClick={() => setUsageLanguage('zh')}
                >
                  中文
                </button>
              </div>
            </div>
            {usageLanguage === 'en' ? <EnglishUsage /> : <ChineseUsage />}
          </section>
        </aside>

        <section className="preview-panel" aria-label="Syntax tree preview">
          <div className="preview-toolbar">
            <div>
              <h2>Preview</h2>
              <p>Subscripts such as <code>_i</code> auto-match traces; use <code>[^TP ma bëgg t_i]</code> for triangles.</p>
            </div>
          </div>
          <div className="canvas-wrap">
            {layout ? (
              <TreeSvg
                ref={svgRef}
                layout={layout}
                links={visibleMovementLinks}
                branchStyle={branchStyle}
                branchStyles={branchStyles}
                movementStyle={movementStyle}
                selectedElement={selectedElement}
                textColors={textColors}
                branchColors={branchColors}
                movementColors={movementColors}
                roofColors={roofColors}
                onSelectElement={setSelectedElement}
              />
            ) : (
              <div className="empty-state">Enter a valid tree expression to show the preview.</div>
            )}
          </div>
        </section>
      </section>
    </main>
  );
}

type TreeSvgProps = {
  layout: NonNullable<ReturnType<typeof layoutTree>>;
  links: MovementLink[];
  branchStyle: LineStyle;
  branchStyles: BranchStyleMap;
  movementStyle: LineStyle;
  selectedElement: SelectableElement | null;
  textColors: ColorMap;
  branchColors: ColorMap;
  movementColors: ColorMap;
  roofColors: ColorMap;
  onSelectElement: (element: SelectableElement | null) => void;
};

function LineStyleToggle({
  value,
  onChange,
}: {
  value: LineStyle;
  onChange: (value: LineStyle) => void;
}) {
  return (
    <div className="segmented" role="group">
      <button
        type="button"
        className={value === 'solid' ? 'active' : ''}
        onClick={() => onChange('solid')}
      >
        Solid
      </button>
      <button
        type="button"
        className={value === 'dashed' ? 'active' : ''}
        onClick={() => onChange('dashed')}
      >
        Dashed
      </button>
    </div>
  );
}

function EnglishUsage() {
  return (
    <ol className="usage-list">
      <li>Write a bracket expression. Each phrase starts with <code>[Label</code> and ends with <code>]</code>.</li>
      <li>Use indexed labels such as <code>Which-book_i</code> and <code>t_i</code> to draw movement links automatically.</li>
      <li>Use a caret in the phrase label, for example <code>[^TP ma bëgg t_i]</code>, to draw a triangle.</li>
      <li>Select any text, branch, movement link, or triangle in the preview to adjust its color.</li>
      <li>Export a vector file, transparent PNG, project JSON, or typesetting code from the buttons above.</li>
    </ol>
  );
}

function ChineseUsage() {
  return (
    <ol className="usage-list">
      <li>输入括号表达式。每个短语以 <code>[标签</code> 开始，并以 <code>]</code> 结束。</li>
      <li>使用 <code>Which-book_i</code> 和 <code>t_i</code> 这样的同编号标签，系统会自动绘制移位连线。</li>
      <li>在短语标签中加入插入号，例如 <code>[^TP ma bëgg t_i]</code>，即可绘制三角形。</li>
      <li>点击预览中的文字、树枝、移位线或三角形，可以设置对应颜色。</li>
      <li>使用上方按钮导出矢量文件、透明 PNG、项目 JSON 或排版代码。</li>
    </ol>
  );
}

const TreeSvg = forwardRef<SVGSVGElement, TreeSvgProps>(function TreeSvg(
  {
    layout,
    links,
    branchStyle,
    branchStyles,
    movementStyle,
    selectedElement,
    textColors,
    branchColors,
    movementColors,
    roofColors,
    onSelectElement,
  },
  ref,
) {
  const byId = new Map(layout.nodes.map((node) => [node.id, node]));
  const frame = links.length > 0
    ? { left: 74, top: 44, right: 74, bottom: 190 }
    : { left: 46, top: 38, right: 46, bottom: 48 };
  const width = layout.width + frame.left + frame.right;
  const height = layout.height + frame.top + frame.bottom;

  const sx = (x: number) => x + frame.left;
  const sy = (y: number) => y + frame.top;

  return (
    <svg
      ref={ref}
      className="tree-svg"
      width={width}
      height={height}
      viewBox={`0 0 ${width} ${height}`}
      role="img"
      aria-label="Generated syntax tree"
      onClick={() => onSelectElement(null)}
    >
      <defs>
        {links.map((link) => (
          <marker
            key={`arrow-${link.id}`}
            id={`arrow-${safeSvgId(link.id)}`}
            markerWidth="11"
            markerHeight="11"
            refX="10"
            refY="5.5"
            orient="auto"
          >
            <path d="M 0 0 L 11 5.5 L 0 11 z" fill={movementColors[link.id] ?? DEFAULT_LINE_COLOR} />
          </marker>
        ))}
      </defs>

      {layout.nodes.flatMap((node) =>
        isTriangleNode(node)
          ? []
          : node.children.map((child) => {
            const branchId = makeBranchId(node.id, child.id);
            const selected = selectedElement?.type === 'branch' && selectedElement.id === branchId;
            const color = branchColors[branchId] ?? DEFAULT_LINE_COLOR;
            const effectiveBranchStyle = branchStyles[branchId] ?? branchStyle;
            return (
          <line
            key={branchId}
            x1={sx(node.x)}
            y1={sy(node.y + labelBottomOffset(node))}
            x2={sx(child.x)}
            y2={sy(child.y - labelTopOffset(child))}
            className={`branch ${effectiveBranchStyle} ${selected ? 'selected-element' : ''}`}
            stroke={color}
            style={{ stroke: color }}
            strokeWidth={selected ? '2.2' : '1.2'}
            strokeLinecap="round"
            strokeDasharray={effectiveBranchStyle === 'dashed' ? '9 7' : undefined}
            onClick={(event) => {
              event.stopPropagation();
              onSelectElement({ type: 'branch', id: branchId, label: `${node.label} → ${child.label}` });
            }}
          />
            );
          }),
      )}

      {links.map((link, index) => {
        const from = byId.get(link.from);
        const to = byId.get(link.to);
        if (!from || !to) return null;
        const distance = Math.abs(from.x - to.x);
        const sag = Math.max(88, Math.min(210, distance * 0.22 + 78 + index * 24));
        const start = movementStartAnchor(from);
        const end = movementEndAnchor(to);
        const startX = sx(start.x);
        const startY = sy(start.y);
        const endX = sx(end.x);
        const endY = sy(end.y);
        const floorY = Math.max(startY, endY) + sag;
        const selected = selectedElement?.type === 'movement' && selectedElement.id === link.id;
        const color = movementColors[link.id] ?? DEFAULT_LINE_COLOR;
        return (
          <path
            key={link.id}
            d={`M ${startX} ${startY} C ${startX} ${floorY}, ${endX} ${floorY}, ${endX} ${endY}`}
            className={`movement ${movementStyle} ${selected ? 'selected-element' : ''}`}
            fill="none"
            stroke={color}
            style={{ fill: 'none', stroke: color }}
            strokeWidth={selected ? '2.3' : '1.35'}
            strokeLinecap="round"
            strokeDasharray={movementStyle === 'dashed' ? '9 8' : undefined}
            markerEnd={`url(#arrow-${safeSvgId(link.id)})`}
            onClick={(event) => {
              event.stopPropagation();
              onSelectElement({ type: 'movement', id: link.id, label: `movement ${link.index}` });
            }}
          />
        );
      })}

      {layout.nodes.map((node) => (
        <g key={node.id} transform={`translate(${sx(node.x)}, ${sy(node.y)})`}>
          {isTriangleNode(node) ? (
            <TriangleNode
              nodeId={node.id}
              label={node.label}
              text={node.triangleText ?? getTriangleText(node)}
              width={node.width}
              textColor={textColors[node.id] ?? DEFAULT_TEXT_COLOR}
              roofColor={roofColors[node.id] ?? DEFAULT_LINE_COLOR}
              selectedElement={selectedElement}
              onSelectElement={onSelectElement}
            />
          ) : (
            <NodeLabel
              nodeId={node.id}
              label={node.label}
              isLeaf={node.children.length === 0}
              color={textColors[node.id] ?? DEFAULT_TEXT_COLOR}
              selected={selectedElement?.type === 'text' && selectedElement.id === node.id}
              onSelectElement={onSelectElement}
            />
          )}
        </g>
      ))}
    </svg>
  );
});

type LayoutNode = NonNullable<ReturnType<typeof layoutTree>>['nodes'][number];

function labelBottomOffset(node: LayoutNode): number {
  return node.children.length === 0 ? 15 : 19;
}

function labelTopOffset(node: LayoutNode): number {
  return node.children.length === 0 ? 16 : 20;
}

function movementStartAnchor(node: LayoutNode): { x: number; y: number } {
  if (isTriangleNode(node)) {
    return {
      x: node.x + Math.max(30, node.width / 2 - 18),
      y: node.y + 88,
    };
  }
  return { x: node.x, y: node.y + 34 };
}

function movementEndAnchor(node: LayoutNode): { x: number; y: number } {
  return { x: node.x - 8, y: node.y + 34 };
}

function NodeLabel({
  nodeId,
  label,
  isLeaf,
  color,
  selected,
  onSelectElement,
}: {
  nodeId: string;
  label: string;
  isLeaf: boolean;
  color: string;
  selected: boolean;
  onSelectElement: (element: SelectableElement) => void;
}) {
  return (
    <text
      className={`node-label ${isLeaf ? 'leaf' : 'phrase'} ${selected ? 'selected-text' : ''}`}
      textAnchor="middle"
      dominantBaseline="middle"
      fill={color}
      style={{ fill: color }}
      fontFamily='"Times New Roman", Times, serif'
      fontSize={isLeaf ? 20 : 25}
      fontWeight={selected ? 600 : 400}
      onClick={(event) => {
        event.stopPropagation();
        onSelectElement({ type: 'text', id: nodeId, label });
      }}
    >
      <StyledLabelParts label={label} />
    </text>
  );
}

function StyledLabelParts({ label }: { label: string }) {
  const parts = parseLabelParts(label);
  return (
    <>
      <StyledBaseLabel base={parts.stem} />
      {parts.head ? (
        <tspan className="superscript" dx="1" dy="-8" fontSize="58%">
          {parts.head}
        </tspan>
      ) : null}
      {parts.subscript ? (
        <tspan className="subscript" dx="1" dy={parts.head ? '12' : '6'} fontSize="58%" fontStyle="italic">
          {parts.subscript}
        </tspan>
      ) : null}
    </>
  );
}

function StyledBaseLabel({ base }: { base: string }) {
  if (!isLowercaseCategoryLabel(base)) {
    return <tspan>{base}</tspan>;
  }
  return (
    <>
      <tspan className="initial-lowercase" fontStyle="italic">{base[0]}</tspan>
      <tspan>{base.slice(1)}</tspan>
    </>
  );
}

function isLowercaseCategoryLabel(base: string): boolean {
  return /^[a-z](?:P|'|′)$/.test(base);
}

function TriangleNode({
  nodeId,
  label,
  text,
  width,
  textColor,
  roofColor,
  selectedElement,
  onSelectElement,
}: {
  nodeId: string;
  label: string;
  text: string;
  width: number;
  textColor: string;
  roofColor: string;
  selectedElement: SelectableElement | null;
  onSelectElement: (element: SelectableElement) => void;
}) {
  const triangleWidth = Math.max(86, width - 10);
  const top = 25;
  const bottom = 62;
  const textSelected = selectedElement?.type === 'text' && selectedElement.id === nodeId;
  const roofSelected = selectedElement?.type === 'roof' && selectedElement.id === nodeId;

  return (
    <>
      <text
        className={`node-label phrase ${textSelected ? 'selected-text' : ''}`}
        textAnchor="middle"
        dominantBaseline="middle"
        fill={textColor}
        style={{ fill: textColor }}
        fontFamily='"Times New Roman", Times, serif'
        fontSize={25}
        fontWeight={textSelected ? 600 : 400}
        onClick={(event) => {
          event.stopPropagation();
          onSelectElement({ type: 'text', id: nodeId, label });
        }}
      >
        <StyledLabelParts label={label} />
      </text>
      <path
        className={`branch triangle-roof ${roofSelected ? 'selected-element' : ''}`}
        d={`M ${-triangleWidth / 2} ${bottom} L 0 ${top} L ${triangleWidth / 2} ${bottom} Z`}
        fill="none"
        stroke={roofColor}
        style={{ fill: 'none', stroke: roofColor }}
        strokeWidth={roofSelected ? '2.2' : '1.2'}
        strokeLinecap="round"
        strokeLinejoin="round"
        onClick={(event) => {
          event.stopPropagation();
          onSelectElement({ type: 'roof', id: nodeId, label: `${label} roof` });
        }}
      />
      <TextWithSubscript
        className="node-label leaf triangle-text"
        label={text}
        x={0}
        y={bottom + 13}
        color={textColor}
        selected={textSelected}
        onClick={(event) => {
          event.stopPropagation();
          onSelectElement({ type: 'text', id: nodeId, label });
        }}
      />
    </>
  );
}

function TextWithSubscript({
  className,
  label,
  x,
  y,
  color,
  selected,
  onClick,
}: {
  className: string;
  label: string;
  x: number;
  y: number;
  color: string;
  selected: boolean;
  onClick: (event: ReactMouseEvent<SVGTextElement>) => void;
}) {
  return (
    <text
      className={`${className} ${selected ? 'selected-text' : ''}`}
      x={x}
      y={y}
      textAnchor="middle"
      dominantBaseline="middle"
      fill={color}
      style={{ fill: color }}
      fontFamily='"Times New Roman", Times, serif'
      fontSize={className.includes('leaf') ? 20 : 25}
      fontWeight={selected ? 600 : 400}
      fontStyle={className.includes('triangle-text') ? 'italic' : undefined}
      onClick={onClick}
    >
      <StyledLabelParts label={label} />
    </text>
  );
}

function getSelectedColor(
  selected: SelectableElement | null,
  textColors: ColorMap,
  branchColors: ColorMap,
  movementColors: ColorMap,
  roofColors: ColorMap,
): string {
  if (!selected) return DEFAULT_TEXT_COLOR;
  if (selected.type === 'text') return textColors[selected.id] ?? DEFAULT_TEXT_COLOR;
  if (selected.type === 'branch') return branchColors[selected.id] ?? DEFAULT_LINE_COLOR;
  if (selected.type === 'movement') return movementColors[selected.id] ?? DEFAULT_LINE_COLOR;
  return roofColors[selected.id] ?? DEFAULT_LINE_COLOR;
}

function updateColorMap(
  selected: SelectableElement,
  color: string | null,
  setters: {
    setTextColors: Dispatch<SetStateAction<ColorMap>>;
    setBranchColors: Dispatch<SetStateAction<ColorMap>>;
    setMovementColors: Dispatch<SetStateAction<ColorMap>>;
    setRoofColors: Dispatch<SetStateAction<ColorMap>>;
  },
) {
  const update = (setter: Dispatch<SetStateAction<ColorMap>>) => {
    setter((current) => {
      const next = { ...current };
      if (color) {
        next[selected.id] = color;
      } else {
        delete next[selected.id];
      }
      return next;
    });
  };

  if (selected.type === 'text') update(setters.setTextColors);
  if (selected.type === 'branch') update(setters.setBranchColors);
  if (selected.type === 'movement') update(setters.setMovementColors);
  if (selected.type === 'roof') update(setters.setRoofColors);
}

function selectionTypeLabel(type: SelectableElement['type']): string {
  if (type === 'text') return 'Text';
  if (type === 'branch') return 'Branch';
  if (type === 'movement') return 'movement';
  return 'Triangle';
}

function makeBranchId(parentId: string, childId: string): string {
  return `${parentId}->${childId}`;
}

function safeSvgId(id: string): string {
  return id.replace(/[^A-Za-z0-9_-]/g, '-');
}

function normalizeOcrText(text: string): string {
  const normalized = text
    .replace(/[“”]/g, '"')
    .replace(/[‘’]/g, "'")
    .replace(/[［【]/g, '[')
    .replace(/[］】]/g, ']')
    .replace(/[|]/g, ']')
    .replace(/\b([sS])[zZ]\b/g, 'SE')
    .replace(/\bbP\b/g, 'DP')
    .replace(/\bdP\b/g, 'DP')
    .replace(/\bDNV\b/g, 'D N] V')
    .replace(/\bD\s*N\s*V\b/g, 'D N] V')
    .replace(/\s+/g, ' ')
    .replace(/\s+]/g, ']')
    .replace(/\[\s+/g, '[')
    .trim();
  return balanceSquareBrackets(normalized);
}

async function preprocessOcrImage(file: File): Promise<Blob> {
  const bitmap = await createImageBitmap(file);
  const scale = Math.max(4, Math.ceil(1400 / Math.max(bitmap.width, bitmap.height)));
  const margin = 28 * scale;
  const canvas = document.createElement('canvas');
  canvas.width = bitmap.width * scale + margin * 2;
  canvas.height = bitmap.height * scale + margin * 2;
  const context = canvas.getContext('2d', { willReadFrequently: true });
  if (!context) throw new Error('Could not preprocess the image.');

  context.fillStyle = '#ffffff';
  context.fillRect(0, 0, canvas.width, canvas.height);
  context.imageSmoothingEnabled = true;
  context.imageSmoothingQuality = 'high';
  context.drawImage(bitmap, margin, margin, bitmap.width * scale, bitmap.height * scale);

  const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
  const data = imageData.data;
  for (let i = 0; i < data.length; i += 4) {
    const gray = data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114;
    const value = gray < 190 ? 0 : 255;
    data[i] = value;
    data[i + 1] = value;
    data[i + 2] = value;
  }
  context.putImageData(imageData, 0, 0);

  return new Promise((resolve, reject) => {
    canvas.toBlob((blob) => {
      if (!blob) {
        reject(new Error('Image preprocessing failed.'));
        return;
      }
      resolve(blob);
    }, 'image/png');
  });
}

function balanceSquareBrackets(text: string): string {
  const opens = (text.match(/\[/g) ?? []).length;
  const closes = (text.match(/\]/g) ?? []).length;
  if (opens <= closes) return text;
  return `${text}${']'.repeat(opens - closes)}`;
}
