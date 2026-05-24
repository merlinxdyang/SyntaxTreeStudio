const SAMPLE = "[CP PRN|Where_i [C' C0|is_z2+phi|\\[+EPP\\]|\\[+WH\\] [TP PRN|it [T' T0|=*is*=_z2 [vP v0|phi+thought_z1 [VP V0|=thought=_z1 [CP PRN|*where*_i [C' C0|that [^TP @he will go *where*@_i ]]]]]]]]]";
const LEGACY_SAMPLE = "[CP Which-book_i [C' C0|did [TP John_j [T' [T0|\\[+PST\\]] [vP -John-_j [v' read_k [VP -read-_k t_i]]]]]]]";
const PREVIOUS_SAMPLE = "[CP Which-book_i [C' C0|did [TP John_j [T' [T0|\\[+PST\\]] [vP =John=_j [v' read_k [VP =read=_k t_i]]]]]]]";
const SVG_NS = "http://www.w3.org/2000/svg";
const NODE_LABEL_X_OFFSET = -22;
const RIGHT_SUBTREE_LABEL_X_CORRECTION = 22;
const BRANCH_LABEL_GAP = 8;
const TRIANGLE_ROOF_X_OFFSET = 12;
const MOVEMENT_COLOR_STORAGE_KEY = "syntree-movement-custom-colors";
const DEFAULT_MOVEMENT_COLOR = "#0f172a";
const DEFAULT_MOVEMENT_COLORS = [DEFAULT_MOVEMENT_COLOR, "#1d4ed8", "#dc2626"];
const GREEK_LETTERS = {
  alpha: { text: "α", latex: "\\alpha" },
  beta: { text: "β", latex: "\\beta" },
  gamma: { text: "γ", latex: "\\gamma" },
  delta: { text: "δ", latex: "\\delta" },
  epsilon: { text: "ε", latex: "\\epsilon" },
  zeta: { text: "ζ", latex: "\\zeta" },
  eta: { text: "η", latex: "\\eta" },
  theta: { text: "θ", latex: "\\theta" },
  iota: { text: "ι", latex: "\\iota" },
  kappa: { text: "κ", latex: "\\kappa" },
  lambda: { text: "λ", latex: "\\lambda" },
  mu: { text: "μ", latex: "\\mu" },
  nu: { text: "ν", latex: "\\nu" },
  xi: { text: "ξ", latex: "\\xi" },
  omicron: { text: "ο", latex: "o" },
  pi: { text: "π", latex: "\\pi" },
  rho: { text: "ρ", latex: "\\rho" },
  sigma: { text: "σ", latex: "\\sigma" },
  tau: { text: "τ", latex: "\\tau" },
  upsilon: { text: "υ", latex: "\\upsilon" },
  phi: { text: "φ", latex: "\\phi" },
  chi: { text: "χ", latex: "\\chi" },
  psi: { text: "ψ", latex: "\\psi" },
  omega: { text: "ω", latex: "\\omega" },
};
const GREEK_PATTERN = new RegExp(`\\b(${Object.keys(GREEK_LETTERS).sort((a, b) => b.length - a.length).join("|")})(?=\\b|[A-Z'′])`, "gi");

const sourceInput = document.getElementById("sourceInput");
const parseNotice = document.getElementById("parseNotice");
const canvasWrap = document.getElementById("canvasWrap");
const latexOutput = document.getElementById("latexOutput");
const branchStyle = document.getElementById("branchStyle");
const movementStyle = document.getElementById("movementStyle");
const showMovement = document.getElementById("showMovement");
const movementToggles = document.getElementById("movementToggles");
const saveHistory = document.getElementById("saveHistory");
const L = window.SYNTREE?.labels || {};
const buttons = {
  loadSample: document.getElementById("loadSample"),
  svg: document.getElementById("downloadSvg"),
  whitePng: document.getElementById("downloadWhitePng"),
  png: document.getElementById("downloadPng"),
  latex: document.getElementById("downloadLatex"),
  copy: document.getElementById("copyLatex"),
  zoomIn: document.getElementById("zoomIn"),
  zoomOut: document.getElementById("zoomOut"),
  zoomReset: document.getElementById("zoomReset"),
};
const helpOpen = document.getElementById("helpOpen");
const helpDialog = document.getElementById("helpDialog");
const helpClose = document.getElementById("helpClose");

let nextId = 1;
let previewZoom = 1;
let current = { tree: null, layout: null, links: [], latex: "" };
let movementPoints = {};
let movementVisibility = {};
let movementColors = {};
let customMovementColors = loadCustomMovementColors();
let branchPoints = {};
let selectedMovementId = null;
let selectedBranchId = null;
let draggingMovement = null;
let draggingBranch = null;
let measuredLabelAnchors = {};
let measuredStrikeLines = {};

const storedSource = localStorage.getItem("syntree-source");
sourceInput.value = !storedSource || storedSource === LEGACY_SAMPLE || storedSource === PREVIOUS_SAMPLE ? SAMPLE : storedSource;

if (helpOpen && helpDialog) {
  helpOpen.addEventListener("click", () => {
    if (typeof helpDialog.showModal === "function") {
      helpDialog.showModal();
    } else {
      helpDialog.setAttribute("open", "");
    }
  });
}

if (helpClose && helpDialog) {
  helpClose.addEventListener("click", () => helpDialog.close());
}

if (helpDialog) {
  helpDialog.addEventListener("click", (event) => {
    if (event.target === helpDialog) helpDialog.close();
  });
}

for (const node of [sourceInput, branchStyle, movementStyle, showMovement]) {
  node.addEventListener("input", render);
  node.addEventListener("change", render);
}

buttons.loadSample.addEventListener("click", () => {
  sourceInput.value = SAMPLE;
  render();
});
buttons.svg.addEventListener("click", downloadSvg);
buttons.whitePng.addEventListener("click", () => downloadPng({ transparent: false }));
buttons.png.addEventListener("click", () => downloadPng({ transparent: true }));
buttons.latex.addEventListener("click", () => downloadText("syntax-tree-forest.tex", current.latex, "text/x-tex;charset=utf-8"));
buttons.zoomIn.addEventListener("click", () => setPreviewZoom(previewZoom + 0.1));
buttons.zoomOut.addEventListener("click", () => setPreviewZoom(previewZoom - 0.1));
buttons.zoomReset.addEventListener("click", () => setPreviewZoom(1));
if (buttons.copy) {
  buttons.copy.addEventListener("click", async () => {
    await navigator.clipboard.writeText(current.latex);
    buttons.copy.textContent = L.copied || "Copied";
    window.setTimeout(() => { buttons.copy.textContent = L.copy || "Copy"; }, 1200);
  });
}

if (saveHistory) {
  saveHistory.addEventListener("click", saveCurrentHistory);
}

document.querySelectorAll(".history-item").forEach((button) => {
  button.addEventListener("click", () => {
    sourceInput.value = button.dataset.source || "";
    render();
  });
});

document.addEventListener("pointermove", (event) => {
  if (!draggingMovement && !draggingBranch) return;
  const svg = canvasWrap.querySelector("svg");
  if (!svg) return;
  const point = svgPoint(svg, event);
  if (!point) return;

  if (draggingMovement) {
    const points = movementPoints[draggingMovement.id];
    if (!points) return;
    movementPoints[draggingMovement.id] = {
      ...points,
      [draggingMovement.handle]: point,
    };
  }

  if (draggingBranch) {
    const points = branchPoints[draggingBranch.id];
    if (!points) return;
    branchPoints[draggingBranch.id] = {
      ...points,
      [draggingBranch.handle]: point,
    };
  }

  render();
});

document.addEventListener("pointerup", () => {
  draggingMovement = null;
  draggingBranch = null;
});

render();

function render() {
  localStorage.setItem("syntree-source", sourceInput.value);
  const parsed = parseBracketTree(sourceInput.value);
  if (parsed.error) {
    current = { tree: null, layout: null, links: [], latex: "" };
    parseNotice.className = "notice error";
    parseNotice.textContent = parsed.error;
    if (latexOutput) latexOutput.textContent = L.typesettingPlaceholder || "Typesetting code appears after a valid parse.";
    canvasWrap.innerHTML = `<div class="empty-state">${escapeHtml(L.enterExpression || "Enter a valid tree expression to show the preview.")}</div>`;
    setExportEnabled(false);
    setZoomEnabled(false);
    return;
  }

  const detectedLinks = detectMovementLinks(parsed.tree);
  const treeNodes = flattenTree(parsed.tree);
  pruneMovementVisibility(detectedLinks);
  pruneMovementColors(detectedLinks);
  renderMovementToggles(detectedLinks, treeNodes);
  const links = showMovement.checked ? detectedLinks.filter((link) => movementVisibility[link.id] !== false) : [];
  const layout = layoutTree(parsed.tree);
  pruneMovementPoints(links);
  pruneBranchPoints(layout.nodes);
  const latex = toForestLatex(parsed.tree, links, layout);
  current = { tree: parsed.tree, layout, links, latex };

  parseNotice.className = "notice success";
  parseNotice.textContent = L.foundStats
    ? L.foundStats.replace("{nodes}", String(layout.nodes.length)).replace("{links}", String(links.length))
    : `Found ${layout.nodes.length} nodes and ${links.length} movement links.`;
  if (latexOutput) latexOutput.textContent = latex;
  measuredLabelAnchors = {};
  measuredStrikeLines = {};
  const measurementSvg = renderSvg(layout, links);
  canvasWrap.replaceChildren(renderZoomedSvg(measurementSvg));
  measuredLabelAnchors = measureLabelAnchors(measurementSvg, layout.nodes);
  measuredStrikeLines = measureStrikeLines(measurementSvg, layout.nodes);
  canvasWrap.replaceChildren(renderZoomedSvg(renderSvg(layout, links)));
  setExportEnabled(true);
  setZoomEnabled(true);
}

function setExportEnabled(enabled) {
  buttons.svg.disabled = !enabled;
  buttons.whitePng.disabled = !enabled;
  buttons.png.disabled = !enabled;
  buttons.latex.disabled = !enabled;
  if (buttons.copy) buttons.copy.disabled = !enabled;
  if (saveHistory) saveHistory.disabled = !enabled;
}

function setZoomEnabled(enabled) {
  for (const button of [buttons.zoomIn, buttons.zoomOut, buttons.zoomReset]) {
    button.disabled = !enabled;
  }
  updateZoomControls();
}

function setPreviewZoom(value) {
  const previousZoom = previewZoom;
  previewZoom = Math.max(0.5, Math.min(2.5, Math.round(value * 10) / 10));
  if (previousZoom === previewZoom) return;
  updateZoomControls();
  const svg = canvasWrap.querySelector("svg");
  if (!svg) return;
  const centerX = (canvasWrap.scrollLeft + canvasWrap.clientWidth / 2) / previousZoom;
  const centerY = (canvasWrap.scrollTop + canvasWrap.clientHeight / 2) / previousZoom;
  applyPreviewZoom(svg);
  canvasWrap.scrollLeft = centerX * previewZoom - canvasWrap.clientWidth / 2;
  canvasWrap.scrollTop = centerY * previewZoom - canvasWrap.clientHeight / 2;
}

function updateZoomControls() {
  buttons.zoomReset.textContent = `${Math.round(previewZoom * 100)}%`;
  buttons.zoomOut.disabled = !current.tree || previewZoom <= 0.5;
  buttons.zoomIn.disabled = !current.tree || previewZoom >= 2.5;
  buttons.zoomReset.disabled = !current.tree;
}

function renderZoomedSvg(svg) {
  applyPreviewZoom(svg);
  const stage = document.createElement("div");
  stage.className = "canvas-stage";
  stage.style.width = `${Number(svg.getAttribute("width")) * previewZoom}px`;
  stage.style.height = `${Number(svg.getAttribute("height")) * previewZoom}px`;
  stage.appendChild(svg);
  return stage;
}

function applyPreviewZoom(svg) {
  const stage = svg.parentElement?.classList?.contains("canvas-stage") ? svg.parentElement : null;
  if (stage) {
    stage.style.width = `${Number(svg.getAttribute("width")) * previewZoom}px`;
    stage.style.height = `${Number(svg.getAttribute("height")) * previewZoom}px`;
  }
  svg.style.transform = `scale(${previewZoom})`;
  svg.style.transformOrigin = "0 0";
}

function renderMovementToggles(links, nodes) {
  if (!movementToggles) return;
  const byId = new Map(nodes.map((node) => [node.id, node]));
  movementToggles.replaceChildren();
  links.forEach((link) => {
    if (movementVisibility[link.id] === undefined) movementVisibility[link.id] = true;
    if (!movementColors[link.id]) movementColors[link.id] = DEFAULT_MOVEMENT_COLOR;
    const item = document.createElement("div");
    item.className = "movement-toggle-item";
    const label = document.createElement("label");
    label.className = "movement-toggle-row";
    const text = document.createElement("span");
    text.textContent = movementToggleText(link, byId);
    const input = document.createElement("input");
    input.type = "checkbox";
    input.checked = movementVisibility[link.id] !== false;
    input.disabled = !showMovement.checked;
    input.addEventListener("change", () => {
      movementVisibility[link.id] = input.checked;
      render();
    });
    label.append(text, input);
    item.append(label, renderMovementColorPalette(link));
    movementToggles.appendChild(item);
  });
}

function renderMovementColorPalette(link) {
  const palette = document.createElement("div");
  palette.className = "movement-color-palette";
  const colors = [...DEFAULT_MOVEMENT_COLORS, ...customMovementColors];
  colors.forEach((color, index) => {
    const isCustomSlot = index >= DEFAULT_MOVEMENT_COLORS.length;
    const slotIndex = index - DEFAULT_MOVEMENT_COLORS.length;
    const swatch = document.createElement("button");
    swatch.type = "button";
    swatch.className = "movement-color-swatch";
    swatch.disabled = !showMovement.checked;
    swatch.setAttribute("aria-label", isCustomSlot ? "Use or edit reusable movement link color" : "Set movement link color");
    if (color) {
      swatch.style.backgroundColor = color;
      if (movementColor(link.id) === color) swatch.classList.add("active");
    } else {
      swatch.classList.add("empty");
    }
    if (isCustomSlot) {
      let clickTimer = null;
      swatch.addEventListener("click", () => {
        if (clickTimer) return;
        clickTimer = window.setTimeout(() => {
          clickTimer = null;
          const storedColor = customMovementColors[slotIndex];
          if (!storedColor) return;
          movementColors[link.id] = storedColor;
          render();
        }, 190);
      });
      swatch.addEventListener("dblclick", () => {
        if (clickTimer) {
          window.clearTimeout(clickTimer);
          clickTimer = null;
        }
        openCustomMovementColorPicker(link, slotIndex);
      });
    } else {
      swatch.addEventListener("click", () => {
        movementColors[link.id] = color;
        render();
      });
    }
    palette.appendChild(swatch);
  });
  return palette;
}

function openCustomMovementColorPicker(link, slotIndex) {
  const picker = document.createElement("input");
  picker.type = "color";
  picker.value = customMovementColors[slotIndex] || movementColor(link.id);
  picker.className = "visually-hidden-color-input";
  picker.addEventListener("change", () => {
    if (!isHexColor(picker.value)) return;
    customMovementColors[slotIndex] = picker.value;
    saveCustomMovementColors();
    movementColors[link.id] = picker.value;
    picker.remove();
    render();
  }, { once: true });
  document.body.appendChild(picker);
  picker.click();
}

function movementColor(linkId) {
  return isHexColor(movementColors[linkId]) ? movementColors[linkId] : DEFAULT_MOVEMENT_COLOR;
}

function loadCustomMovementColors() {
  try {
    const parsed = JSON.parse(localStorage.getItem(MOVEMENT_COLOR_STORAGE_KEY) || "[]");
    if (!Array.isArray(parsed)) return [null, null];
    return [parsed[0], parsed[1]].map((color) => isHexColor(color) ? color : null);
  } catch {
    return [null, null];
  }
}

function saveCustomMovementColors() {
  localStorage.setItem(MOVEMENT_COLOR_STORAGE_KEY, JSON.stringify(customMovementColors));
}

function isHexColor(value) {
  return typeof value === "string" && /^#[0-9a-f]{6}$/i.test(value);
}

function movementToggleText(link, byId) {
  const target = byId.get(link.to);
  const name = target ? movementLabelName(target.label) : link.index;
  const template = L.showMovementOne || "Show movement link ({label})";
  return template.replace("{label}", name);
}

function movementLabelName(label) {
  const lines = splitLabelLines(label).map(stripStrikeMarkers);
  const visible = lines.find((line) => extractMovementIndex(line)) || lines[0] || stripStrikeMarkers(label);
  return displayText(stripMovementIndexMarker(visible));
}

function parseBracketTree(input) {
  nextId = 1;
  const tokens = tokenize(input);
  if (!tokens.length) return { tree: null, error: "Enter a bracket expression." };
  let index = 0;

  function readNode() {
    if (tokens[index] !== "[") throw new Error(`Expected "[" near token ${index + 1}.`);
    index += 1;
    const label = tokens[index];
    if (!label || label === "[" || label === "]") throw new Error('Every "[" must be followed by a node label.');
    index += 1;
    const children = [];
    while (index < tokens.length && tokens[index] !== "]") {
      if (tokens[index] === "[") {
        children.push(readNode());
      } else {
        children.push({ id: makeId(), label: String(tokens[index]), children: [] });
        index += 1;
      }
    }
    if (tokens[index] !== "]") throw new Error(`Node "${label}" is missing a closing bracket.`);
    index += 1;
    return { id: makeId(), label: String(label), children };
  }

  try {
    const tree = readNode();
    if (index !== tokens.length) throw new Error(`Unexpected content after "${tokens[index]}".`);
    return { tree, error: null };
  } catch (error) {
    return { tree: null, error: error.message || "Parsing failed." };
  }
}

function tokenize(input) {
  const normalized = input.replace(/\(/g, "[").replace(/\)/g, "]");
  const tokens = [];
  let currentToken = "";
  let quoted = false;
  let escaping = false;
  for (const char of normalized) {
    if (escaping) {
      currentToken += char;
      escaping = false;
      continue;
    }
    if (char === "\\") {
      escaping = true;
      continue;
    }
    if (char === '"') {
      quoted = !quoted;
      continue;
    }
    if (!quoted && (char === "[" || char === "]")) {
      pushToken(tokens, currentToken);
      currentToken = "";
      tokens.push(char);
      continue;
    }
    if (!quoted && /\s/.test(char)) {
      pushToken(tokens, currentToken);
      currentToken = "";
      continue;
    }
    currentToken += char;
  }
  if (escaping) currentToken += "\\";
  pushToken(tokens, currentToken);
  return tokens;
}

function pushToken(tokens, value) {
  const token = value.trim();
  if (token) tokens.push(token);
}

function makeId() {
  const id = `n${nextId}`;
  nextId += 1;
  return id;
}

function detectMovementLinks(tree) {
  const indexed = new Map();
  collectMovementCandidates(tree, null, (candidate) => {
    const index = getIndex(candidate.label);
    if (!index) return;
    const nodes = indexed.get(index) || [];
    if (!nodes.some((node) => node.id === candidate.id)) {
      indexed.set(index, [...nodes, candidate]);
    }
  });
  const links = [];
  indexed.forEach((nodes, index) => {
    for (let position = 1; position < nodes.length; position += 1) {
      const source = nodes[position];
      const target = nodes[position - 1];
      links.push({ id: `movement-${index}-${source.id}-${target.id}`, from: source.id, to: target.id, index });
    }
  });
  return links;
}

function collectMovementCandidates(node, triangleOwner, visit) {
  const owner = triangleOwner || (isTriangleNode(node) ? node : null);
  visit({ id: owner && node !== owner ? owner.id : node.id, label: node.label });
  node.children.forEach((child) => collectMovementCandidates(child, owner, visit));
}

function flattenTree(tree) {
  const nodes = [];
  (function visit(node) {
    nodes.push(node);
    node.children.forEach(visit);
  })(tree);
  return nodes;
}

function getIndex(label) {
  return extractMovementIndex(stripStrikeMarkers(label));
}

function isTrace(label) {
  return /^t(_[A-Za-z0-9]+)?$/.test(stripStrikeMarkers(label)) || /^trace(_[A-Za-z0-9]+)?$/i.test(stripStrikeMarkers(label));
}

function layoutTree(tree) {
  const levelGap = 88;
  const padding = { x: 70, y: 52 };
  const nodes = [];

  function position(node, x, depth, parent = null) {
    const childList = isTriangleNode(node) ? [] : node.children;
    node._parent = parent;
    node.x = x;
    node.y = depth * levelGap;
    node.width = Math.max(
      estimateLabelWidth(node.label),
      isTriangleNode(node) ? estimateTriangleTextWidth(getTriangleText(node)) : 0,
      64,
    );
    nodes.push(node);
    if (!childList.length) return;

    const span = branchSpanFor(node, childList, depth);
    childList.forEach((child, index) => {
      const offset = childList.length === 1 ? 0 : (index - (childList.length - 1) / 2) * span;
      position(child, x + offset, depth + 1, node);
    });
  }

  position(tree, 0, 0);
  resolveLayoutCollisions(nodes);

  let minX = Infinity;
  let maxX = -Infinity;
  let maxY = -Infinity;
  nodes.forEach((node) => {
    minX = Math.min(minX, node.x - node.width / 2);
    maxX = Math.max(maxX, node.x + node.width / 2);
    maxY = Math.max(maxY, node.y + (isTriangleNode(node) ? 112 : 28));
  });
  const shiftX = padding.x - minX;
  nodes.forEach((node) => {
    node.x += shiftX;
    node.y += padding.y;
  });

  return {
    root: tree,
    nodes,
    width: Math.max(720, maxX - minX + padding.x * 2),
    height: maxY + padding.y,
  };
}

function branchSpanFor(node, children, depth) {
  const labelRoom = children.reduce((max, child) => {
    return Math.max(max, (estimateLabelWidth(node.label) + estimateLabelWidth(child.label)) / 2 + 56);
  }, 0);
  const depthRoom = Math.max(142, 214 - depth * 12);
  return Math.max(labelRoom, depthRoom);
}

function resolveLayoutCollisions(nodes) {
  const byDepth = new Map();
  nodes.forEach((node) => {
    const depth = Math.round(node.y / 88);
    byDepth.set(depth, [...(byDepth.get(depth) || []), node]);
  });
  [...byDepth.keys()].sort((a, b) => a - b).forEach((depth) => {
    const row = byDepth.get(depth).sort((a, b) => a.x - b.x);
    for (let i = 1; i < row.length; i += 1) {
      const previous = row[i - 1];
      const current = row[i];
      const minGap = (previous.width + current.width) / 2 + 44;
      const actualGap = current.x - previous.x;
      if (actualGap < minGap) {
        offsetSubtree(current, minGap - actualGap);
      }
    }
  });
}

function offsetSubtree(node, dx) {
  node.x += dx;
  if (isTriangleNode(node)) return;
  node.children.forEach((child) => offsetSubtree(child, dx));
}

function renderSvg(layout, links) {
  const byId = new Map(layout.nodes.map((node) => [node.id, node]));
  const bottomPad = links.length ? 190 : 60;
  const svg = el("svg", {
    class: "tree-svg",
    width: layout.width,
    height: layout.height + bottomPad,
    viewBox: `0 0 ${layout.width} ${layout.height + bottomPad}`,
    role: "img",
    "aria-label": "Generated syntax tree",
  });
  svg.addEventListener("pointerdown", (event) => {
    if (event.target === svg) {
      selectedMovementId = null;
      selectedBranchId = null;
      render();
    }
  });

  const defs = el("defs");
  links.forEach((link) => {
    const color = movementColor(link.id);
    const marker = el("marker", {
      id: `arrow-${safeSvgId(link.id)}`,
      markerWidth: 11,
      markerHeight: 11,
      refX: 10,
      refY: 5.5,
      orient: "auto-start-reverse",
    });
    marker.appendChild(el("path", { d: "M 0 0 L 11 5.5 L 0 11 z", fill: color }));
    defs.appendChild(marker);
  });
  svg.appendChild(defs);

  const branchLayer = el("g", { class: "branch-layer" });
  const movementLayer = el("g", { class: "movement-layer" });
  const labelLayer = el("g", { class: "label-layer" });
  const editLayer = el("g", { class: "edit-layer" });
  const movementRanks = movementRankMap(links, byId);

  layout.nodes.forEach((node) => {
    if (isTriangleNode(node)) return;
    node.children.forEach((child) => {
      const id = branchId(node, child);
      const points = branchLinePoints(id, node, child);
      const line = el("line", {
        x1: points.start.x,
        y1: points.start.y,
        x2: points.end.x,
        y2: points.end.y,
        class: `branch ${branchStyle.value} ${selectedBranchId === id ? "selected" : ""}`,
      });
      const hitLine = el("line", {
        x1: points.start.x,
        y1: points.start.y,
        x2: points.end.x,
        y2: points.end.y,
        class: "branch-hit",
      });
      const selectBranch = (event) => {
        event.preventDefault();
        event.stopPropagation();
        selectedBranchId = id;
        selectedMovementId = null;
        render();
      };
      line.addEventListener("pointerdown", selectBranch);
      hitLine.addEventListener("pointerdown", selectBranch);
      branchLayer.appendChild(line);
      branchLayer.appendChild(hitLine);
      if (selectedBranchId === id) {
        editLayer.appendChild(renderBranchHandles(id, points));
      }
    });
  });

  links.forEach((link, index) => {
    const from = byId.get(link.from);
    const to = byId.get(link.to);
    if (!from || !to) return;
    const rank = movementRanks.get(link.id) ?? index;
    const points = movementCurvePoints(link, rank, from, to);
    const d = movementPathD(points);
    const arrowOnStart = points.start.x <= points.end.x;
    const color = movementColor(link.id);
    const path = el("path", {
      d,
      class: `movement ${movementStyle.value} ${selectedMovementId === link.id ? "selected" : ""}`,
      style: `stroke: ${color};`,
      "marker-start": arrowOnStart ? `url(#arrow-${safeSvgId(link.id)})` : "",
      "marker-end": arrowOnStart ? "" : `url(#arrow-${safeSvgId(link.id)})`,
    });
    const hitPath = el("path", {
      d,
      class: "movement-hit",
    });
    const selectMovement = (event) => {
      event.preventDefault();
      event.stopPropagation();
      selectedMovementId = link.id;
      selectedBranchId = null;
      render();
    };
    path.addEventListener("pointerdown", selectMovement);
    hitPath.addEventListener("pointerdown", selectMovement);
    movementLayer.appendChild(path);
    movementLayer.appendChild(hitPath);
    if (selectedMovementId === link.id) {
      editLayer.appendChild(renderMovementHandles(link, points));
    }
  });

  layout.nodes.forEach((node) => {
    const group = el("g", {
      transform: `translate(${labelX(node)}, ${node.y})`,
      "data-node-id": node.id,
    });
    if (isTriangleNode(node)) {
      renderTriangle(group, node);
    } else {
      renderLabel(group, node.label, node.children.length === 0, node.id);
    }
    labelLayer.appendChild(group);
  });

  svg.appendChild(branchLayer);
  svg.appendChild(movementLayer);
  svg.appendChild(labelLayer);
  svg.appendChild(editLayer);

  return svg;
}

function renderLabel(group, label, isLeaf, nodeId = "") {
  const lines = splitDisplayLabelLines(label);
  const lineGap = isLeaf ? 24 : 30;
  lines.forEach((line, index) => {
    const y = (index - (lines.length - 1) / 2) * lineGap;
    const text = el("text", {
      class: `node-label ${isLeaf ? "leaf" : "phrase"}`,
      x: 0,
      y,
      "text-anchor": "middle",
      "dominant-baseline": "middle",
      "data-line-index": index,
    });
    const meta = appendStyledLabel(text, line, isLeaf);
    group.appendChild(text);
    if (meta.struck) {
      group.appendChild(renderStrikeLine(meta, y, nodeId, index));
    }
  });
}

function labelX(node) {
  return node.x + nodeLabelOffset(node);
}

function nodeLabelOffset(node) {
  return NODE_LABEL_X_OFFSET + (isInRightSubtree(node) ? RIGHT_SUBTREE_LABEL_X_CORRECTION : 0);
}

function isInRightSubtree(node) {
  let current = node;
  let parent = current._parent;
  while (parent?._parent) {
    current = parent;
    parent = parent._parent;
  }
  return Boolean(parent && current.x > parent.x);
}

function renderTriangle(group, node) {
  renderLabel(group, node.label, false);
  const roofText = getTriangleText(node);
  const hasDecoratedSuffix = splitLabelLines(roofText).some((line) => {
    const parts = parseLabelParts(line);
    return Boolean(parts.head || parts.subscript);
  });
  const width = Math.max(76, estimateTriangleTextWidth(roofText) + (hasDecoratedSuffix ? 22 : 14));
  group.appendChild(el("path", {
    class: "branch triangle-roof",
    d: `M ${TRIANGLE_ROOF_X_OFFSET - width / 2} 62 L ${TRIANGLE_ROOF_X_OFFSET} 25 L ${TRIANGLE_ROOF_X_OFFSET + width / 2} 62 Z`,
  }));
  const text = el("text", {
    class: "node-label leaf triangle-text",
    x: TRIANGLE_ROOF_X_OFFSET - width / 2,
    y: 94,
    "text-anchor": "start",
    "dominant-baseline": "alphabetic",
  });
  appendStyledLabel(text, roofText);
  group.appendChild(text);
}

function renderMovementHandles(link, points) {
  const group = el("g", { class: "movement-handles" });
  [
    ["start", points.start],
    ["control", points.control],
    ["end", points.end],
  ].forEach(([handle, point]) => {
    const circle = el("circle", {
      class: `movement-handle ${handle}`,
      cx: point.x,
      cy: point.y,
      r: handle === "control" ? 5.5 : 5,
      tabindex: 0,
      "aria-label": `${handle} movement handle`,
    });
    circle.addEventListener("pointerdown", (event) => {
      event.preventDefault();
      event.stopPropagation();
      movementPoints[link.id] = { ...points };
      draggingMovement = { id: link.id, handle };
    });
    group.appendChild(circle);
  });
  return group;
}

function renderBranchHandles(id, points) {
  const group = el("g", { class: "branch-handles" });
  [
    ["start", points.start],
    ["end", points.end],
  ].forEach(([handle, point]) => {
    const circle = el("circle", {
      class: `branch-handle ${handle}`,
      cx: point.x,
      cy: point.y,
      r: 5.5,
      tabindex: 0,
      "aria-label": `${handle} branch handle`,
    });
    circle.addEventListener("pointerdown", (event) => {
      event.preventDefault();
      event.stopPropagation();
      branchPoints[id] = { ...points };
      selectedBranchId = id;
      selectedMovementId = null;
      draggingBranch = { id, handle };
    });
    group.appendChild(circle);
  });
  return group;
}

function appendStyledLabel(text, label, isLeaf = true) {
  const styled = parseStrikeStyle(label);
  const parts = parseLabelParts(styled.label);
  appendBaseLabel(text, parts.stem, styled.struck, parts.italicStem, parts.stemSegments);
  if (parts.head) {
    const head = el("tspan", { class: "superscript", dx: 1, dy: -8 });
    head.textContent = parts.head;
    text.appendChild(head);
  }
  if (parts.subscript) {
    const subscript = el("tspan", {
      class: "subscript",
      dx: 1,
      dy: parts.head ? 12 : 6,
    });
    subscript.textContent = displayText(parts.subscript);
    text.appendChild(subscript);
  }
  return strikeLineMeta(parts, styled.struck, isLeaf);
}

function strikeLineMeta(parts, struck, isLeaf) {
  const stemWidth = estimateStyledTextWidth(parts.stem, isLeaf);
  const suffixWidth = (parts.head ? 8 : 0) + (parts.subscript ? 8 : 0);
  const totalWidth = stemWidth + suffixWidth;
  return {
    struck,
    isLeaf,
    x1: -totalWidth / 2,
    x2: -totalWidth / 2 + stemWidth,
  };
}

function renderStrikeLine(meta, y, nodeId = "", lineIndex = 0) {
  const measured = measuredStrikeLines[strikeLineKey(nodeId, lineIndex)];
  const x1 = measured?.x1 ?? meta.x1;
  const x2 = measured?.x2 ?? meta.x2;
  const yPos = measured?.y ?? y - (meta.isLeaf ? 6.5 : 8);
  return el("line", {
    class: "label-strike-line",
    x1,
    y1: yPos,
    x2,
    y2: yPos,
  });
}

function appendBaseLabel(text, base, struck = false, italicStem = false, stemSegments = null) {
  if (stemSegments?.length) {
    stemSegments.forEach((segment) => {
      if (!segment.text) return;
      const classes = [];
      if (segment.italic) classes.push("italic-stem");
      if (segment.hollow) classes.push("hollow-stem");
      if (struck) classes.push("struck");
      const tspan = el("tspan", classes.length ? { class: classes.join(" ") } : {});
      tspan.textContent = displayText(segment.text);
      text.appendChild(tspan);
    });
    return;
  }

  if (italicStem) {
    const tspan = el("tspan", { class: `italic-stem${struck ? " struck" : ""}` });
    tspan.textContent = displayText(base);
    text.appendChild(tspan);
    return;
  }

  if (!isLowercaseCategoryLabel(base)) {
    const tspan = el("tspan", struck ? { class: "struck" } : {});
    tspan.textContent = displayText(base);
    text.appendChild(tspan);
    return;
  }

  const initial = el("tspan", { class: `initial-lowercase${struck ? " struck" : ""}` });
  initial.textContent = base[0];
  text.appendChild(initial);

  const rest = el("tspan", struck ? { class: "struck" } : {});
  rest.textContent = base.slice(1);
  text.appendChild(rest);
}

function el(name, attrs = {}) {
  const node = document.createElementNS(SVG_NS, name);
  Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, String(value)));
  return node;
}

function toForestLatex(tree, links, layout = null) {
  const forest = nodeToForest(tree, links);
  const preface = "\\documentclass{article}\n\n\\usepackage{forest}\n\\usepackage[normalem]{ulem}\n\\usepackage{pdfrender}\n\n\\begin{document}\n\n";
  const linkStyle = movementStyle.value === "dashed" ? "dashed, ->" : "->";
  const drawLinks = latexMovementLinks(links, linkStyle, layout);
  const linkOptions = links.length
    ? `,\ntikz+={%\n  ${drawLinks}\n}`
    : "";
  const header = links.length
    ? `\\begin{forest}\nfor tree={align=center, parent anchor=south, child anchor=north}${linkOptions}\n`
    : "\\begin{forest}\nfor tree={align=center}\n";
  return `${preface}${header}${forest}\n\\end{forest}\n\n\\end{document}`;
}

function latexMovementLinks(links, linkStyle, layout) {
  const ranked = rankLatexMovementLinks(links, layout);
  return ranked.map(({ link, rank }) => latexMovementLink(link, linkStyle, rank)).join("\n  ");
}

function rankLatexMovementLinks(links, layout) {
  const positions = new Map((layout?.nodes || []).map((node) => [node.id, node]));
  return links
    .map((link, order) => {
      const from = positions.get(link.from);
      const to = positions.get(link.to);
      const span = from && to ? Math.hypot(from.x - to.x, from.y - to.y) : links.length - order;
      return { link, order, span };
    })
    .sort((a, b) => b.span - a.span || a.order - b.order)
    .map((item, rank) => ({ ...item, rank }))
    .sort((a, b) => a.order - b.order);
}

function latexMovementLink(link, linkStyle, rank) {
  const from = latexName(link.from);
  const to = latexName(link.to);
  if (rank === 0) {
    return `\\draw[${linkStyle}] (${from}.south) .. controls +(-108:11.0em) and +(-168:8.5em) .. (${to}.south west);`;
  }
  if (rank === 1) {
    return `\\draw[${linkStyle}] (${from}.south) .. controls +(-100:7.0em) and +(-160:5.0em) .. (${to}.south west);`;
  }
  return `\\draw[${linkStyle}] (${from}.south west) to[out=185, in=-85, looseness=1.15] (${to}.south);`;
}

function nodeToForest(node, links) {
  const name = links.some((link) => link.from === node.id || link.to === node.id) ? `, name=${latexName(node.id)}` : "";
  if (isTriangleNode(node)) {
    const label = forestLabel(labelForLatex(visibleLabel(node.label)));
    const roof = forestLabel(labelForLatex(getTriangleText(node)));
    return `[${label}${name} [${roof}, roof]]`;
  }
  const label = forestLabel(labelForLatex(node.label));
  if (!node.children.length) return `[${label}${name}]`;
  return `[${label}${name} ${node.children.map((child) => nodeToForest(child, links)).join(" ")}]`;
}

function forestLabel(label) {
  return `{${label}}`;
}

function labelForLatex(label) {
  return splitDisplayLabelLines(label).map(labelLineForLatex).join("\\\\{}");
}

function labelLineForLatex(label) {
  const styled = parseStrikeStyle(label);
  const parts = parseLabelParts(styled.label);
  const stem = latexStem(parts);
  const visibleStem = styled.struck ? `\\sout{${stem}}` : stem;
  return `${visibleStem}${parts.head ? "$^{0}$" : ""}${parts.subscript ? `$_{${latexText(parts.subscript)}}$` : ""}`;
}

function latexStem(parts) {
  if (parts.stemSegments?.length) {
    return parts.stemSegments.map((segment) => {
      const text = latexText(segment.text);
      const styled = segment.italic ? `\\textit{${text}}` : text;
      return segment.hollow ? `\\textpdfrender{TextRenderingMode=Stroke,LineWidth=.35pt}{${styled}}` : styled;
    }).join("");
  }
  const stem = latexText(parts.stem);
  return parts.italicStem ? `\\textit{${stem}}` : stem;
}

function parseLabelParts(label) {
  const indexed = splitIndexedLabel(label);
  if (indexed.index) {
    const headMatch = indexed.base.match(/^(.*)0$/);
    return buildLabelParts(headMatch ? headMatch[1] : indexed.base, headMatch ? "0" : null, indexed.index);
  }
  const compact = indexed.base.match(/^(.*?)(0)?([1-9][0-9]*)$/);
  if (compact) {
    return buildLabelParts(compact[1], compact[2] || null, compact[3]);
  }
  const headOnly = indexed.base.match(/^(.*)0$/);
  if (headOnly) {
    return buildLabelParts(headOnly[1], "0", null);
  }
  return buildLabelParts(indexed.base, null, null);
}

function buildLabelParts(rawStem, head, subscript) {
  const segmented = parseLabelSegments(rawStem);
  return {
    stem: segmented.text,
    stemSegments: segmented.hasStyle ? segmented.segments : null,
    head,
    subscript: isHiddenMovementIndex(subscript) ? null : subscript,
    italicStem: !segmented.hasStyle && isItalicHeadStem(segmented.text, head),
  };
}

function isItalicHeadStem(stem, hasHead) {
  return Boolean(hasHead && stem === "v");
}

function splitIndexedLabel(label) {
  const visible = parseStrikeStyle(label).label;
  const match = visible.match(/^(.*)_([A-Za-z0-9]+)$/);
  if (match) return { base: match[1], index: match[2] };
  const hidden = visible.match(/^(.*)_((?:z|Z)[0-9]+)(.*)$/);
  return hidden ? { base: `${hidden[1]}${hidden[3]}`, index: hidden[2] } : { base: visible, index: null };
}

function extractMovementIndex(label) {
  const suffix = label.match(/_([A-Za-z0-9]+)$/);
  if (suffix) return suffix[1];
  const hidden = label.match(/_((?:z|Z)[0-9]+)(?=$|[^A-Za-z0-9])/);
  return hidden ? hidden[1] : null;
}

function stripMovementIndexMarker(label) {
  return label.replace(/_([A-Za-z0-9]+)$/g, "").replace(/_((?:z|Z)[0-9]+)(?=$|[^A-Za-z0-9])/g, "");
}

function isHiddenMovementIndex(index) {
  return typeof index === "string" && /^(?:z|Z)(?:[0-9]+)?$/.test(index);
}

function parseStrikeStyle(label) {
  const visible = visibleLabel(label);
  const match = visible.match(/^=(.+?)=(_[A-Za-z0-9]+)?$/) || visible.match(/^-(.+?)-(_[A-Za-z0-9]+)?$/);
  return match
    ? { label: `${match[1]}${match[2] || ""}`, struck: true }
    : { label: visible, struck: false };
}

function isLowercaseCategoryLabel(base) {
  return /^[a-z](?:P|'|′)$/.test(base);
}

function isTriangleNode(node) {
  return node.label.startsWith("^") || node.label.startsWith("△");
}

function visibleLabel(label) {
  return label.replace(/^[△^]/, "");
}

function getTriangleText(node) {
  return node.children.map((child) => visibleLabel(child.label)).join(" ");
}

function stripStrikeMarkers(label) {
  return stripItalicMarkers(parseStrikeStyle(label).label);
}

function stripItalicMarkers(label) {
  return parseLabelSegments(label).text;
}

function parseLabelSegments(value) {
  const segments = [];
  let buffer = "";
  let italic = false;
  let hollow = false;
  let sawMarker = false;

  const flush = () => {
    if (!buffer) return;
    segments.push({ text: buffer, italic, hollow });
    buffer = "";
  };

  for (const char of value) {
    if (char === "*" || char === "@") {
      flush();
      if (char === "*") italic = !italic;
      if (char === "@") hollow = !hollow;
      sawMarker = true;
      continue;
    }
    buffer += char;
  }
  flush();

  if (italic || hollow || !sawMarker) {
    return { text: value, segments: null, hasStyle: false };
  }

  const cleanSegments = segments.filter((segment) => segment.text);
  const hasStyle = cleanSegments.some((segment) => segment.italic || segment.hollow);
  return {
    text: cleanSegments.map((segment) => segment.text).join(""),
    segments: cleanSegments,
    hasStyle,
  };
}

function splitLabelLines(label) {
  return stripStrikeMarkers(label).split("|").map((line) => line.trim()).filter(Boolean);
}

function splitDisplayLabelLines(label) {
  return visibleLabel(label).split("|").map((line) => line.trim()).filter(Boolean);
}

function labelBottomOffset(node) {
  return labelBlockHeight(node) / 2 + (node.children.length === 0 ? 17 : 18);
}

function labelTopOffset(node) {
  return labelBlockHeight(node) / 2 + (node.children.length === 0 ? 20 : 18);
}

function labelBlockHeight(node) {
  const isLeaf = node.children.length === 0;
  const lines = splitLabelLines(node.label);
  const fontSize = isLeaf ? 20 : 25;
  const lineGap = isLeaf ? 24 : 30;
  const decoratedExtra = lines.some((line) => {
    const parts = parseLabelParts(line);
    return Boolean(parts.head || parts.subscript);
  }) ? 8 : 0;
  return fontSize + Math.max(0, lines.length - 1) * lineGap + decoratedExtra;
}

function measureLabelAnchors(svg, nodes) {
  const anchors = {};
  nodes.forEach((node) => {
    const group = svg.querySelector(`[data-node-id="${node.id}"]`);
    if (!group) return;
    const blockTarget = isTriangleNode(node) ? group.querySelector("text:not(.triangle-text)") : group;
    const anchorTarget = group.querySelector('text[data-line-index="0"]') || blockTarget || group;
    const blockRect = (blockTarget || group).getBoundingClientRect();
    const anchorRect = anchorTarget.getBoundingClientRect();
    if (!blockRect.width && !blockRect.height) return;
    const topLeft = svgClientPoint(svg, blockRect.left, blockRect.top);
    const bottomRight = svgClientPoint(svg, blockRect.right, blockRect.bottom);
    const anchorLeft = svgClientPoint(svg, anchorRect.left, anchorRect.top);
    const anchorRight = svgClientPoint(svg, anchorRect.right, anchorRect.bottom);
    if (!topLeft || !bottomRight) return;
    anchors[node.id] = {
      centerX: anchorLeft && anchorRight ? (anchorLeft.x + anchorRight.x) / 2 : (topLeft.x + bottomRight.x) / 2,
      topY: topLeft.y,
      bottomY: bottomRight.y,
    };
  });
  return anchors;
}

function measureStrikeLines(svg, nodes) {
  const lines = {};
  nodes.forEach((node) => {
    const group = svg.querySelector(`[data-node-id="${node.id}"]`);
    if (!group) return;
    group.querySelectorAll("text[data-line-index]").forEach((text) => {
      const struckParts = [...text.querySelectorAll(".struck")];
      if (!struckParts.length) return;
      const rects = struckParts.map((part) => part.getBoundingClientRect()).filter((rect) => rect.width || rect.height);
      if (!rects.length) return;
      const left = Math.min(...rects.map((rect) => rect.left));
      const right = Math.max(...rects.map((rect) => rect.right));
      const top = Math.min(...rects.map((rect) => rect.top));
      const bottom = Math.max(...rects.map((rect) => rect.bottom));
      const strikeY = top + (bottom - top) * 0.55;
      const start = svgClientPoint(svg, left, strikeY);
      const end = svgClientPoint(svg, right, strikeY);
      if (!start || !end) return;
      const key = strikeLineKey(node.id, text.dataset.lineIndex || "0");
      lines[key] = {
        x1: start.x - labelX(node),
        x2: end.x - labelX(node),
        y: start.y - node.y,
      };
    });
  });
  return lines;
}

function strikeLineKey(nodeId, lineIndex) {
  return `${nodeId}:${lineIndex}`;
}

function labelAnchorX(node) {
  return measuredLabelAnchors[node.id]?.centerX ?? labelX(node);
}

function labelTopY(node) {
  return measuredLabelAnchors[node.id]?.topY ?? node.y - labelTopOffset(node);
}

function labelBottomY(node) {
  return measuredLabelAnchors[node.id]?.bottomY ?? node.y + labelBottomOffset(node);
}

function movementStartAnchor(node) {
  if (isTriangleNode(node)) {
    return { x: labelX(node) + Math.max(24, node.width / 2 - 20), y: node.y + 106 };
  }
  return { x: labelAnchorX(node), y: labelBottomY(node) + 8 };
}

function movementEndAnchor(node) {
  return { x: labelAnchorX(node), y: labelBottomY(node) + 8 };
}

function branchId(parent, child) {
  return `${parent.id}->${child.id}`;
}

function branchLinePoints(id, parent, child) {
  if (branchPoints[id]) return branchPoints[id];
  return {
    start: {
      x: labelAnchorX(parent),
      y: labelBottomY(parent) + BRANCH_LABEL_GAP,
    },
    end: {
      x: labelAnchorX(child),
      y: labelTopY(child) - BRANCH_LABEL_GAP,
    },
  };
}

function movementCurvePoints(link, rank, from, to) {
  if (movementPoints[link.id]) return movementPoints[link.id];
  const start = movementStartAnchor(from);
  const end = movementEndAnchor(to);
  const control = defaultMovementControl(start, end, rank);
  return {
    start,
    control,
    end,
  };
}

function movementPathD(points) {
  return `M ${points.start.x} ${points.start.y} Q ${points.control.x} ${points.control.y}, ${points.end.x} ${points.end.y}`;
}

function defaultMovementControl(start, end, rank) {
  const distance = Math.abs(start.x - end.x);
  const baseY = Math.max(start.y, end.y);
  if (rank === 0) {
    return {
      x: (start.x + end.x) / 2 - Math.min(90, distance * 0.08),
      y: baseY + Math.max(190, Math.min(260, distance * 0.24 + 120)),
    };
  }
  if (rank === 1) {
    return {
      x: (start.x + end.x) / 2 - Math.min(60, distance * 0.08),
      y: baseY + Math.max(110, Math.min(170, distance * 0.16 + 72)),
    };
  }
  return {
    x: (start.x + end.x) / 2 - Math.max(26, Math.min(48, distance * 0.12)),
    y: Math.max(start.y, end.y) + Math.max(46, Math.min(72, distance * 0.12 + 34)),
  };
}

function movementRankMap(links, positions) {
  return new Map(
    links
      .map((link, order) => {
        const from = positions.get(link.from);
        const to = positions.get(link.to);
        const span = from && to ? Math.hypot(from.x - to.x, from.y - to.y) : links.length - order;
        return { id: link.id, order, span };
      })
      .sort((a, b) => b.span - a.span || a.order - b.order)
      .map((item, rank) => [item.id, rank]),
  );
}

function svgPoint(svg, event) {
  return svgClientPoint(svg, event.clientX, event.clientY);
}

function svgClientPoint(svg, clientX, clientY) {
  const matrix = svg.getScreenCTM();
  if (!matrix) return null;
  const point = svg.createSVGPoint();
  point.x = clientX;
  point.y = clientY;
  const transformed = point.matrixTransform(matrix.inverse());
  return { x: transformed.x, y: transformed.y };
}

function pruneMovementPoints(links) {
  const valid = new Set(links.map((link) => link.id));
  movementPoints = Object.fromEntries(Object.entries(movementPoints).filter(([id]) => valid.has(id)));
  if (selectedMovementId && !valid.has(selectedMovementId)) selectedMovementId = null;
}

function pruneMovementVisibility(links) {
  const valid = new Set(links.map((link) => link.id));
  movementVisibility = Object.fromEntries(Object.entries(movementVisibility).filter(([id]) => valid.has(id)));
}

function pruneMovementColors(links) {
  const valid = new Set(links.map((link) => link.id));
  movementColors = Object.fromEntries(Object.entries(movementColors).filter(([id]) => valid.has(id)));
}

function pruneBranchPoints(nodes) {
  const valid = new Set();
  nodes.forEach((node) => {
    if (isTriangleNode(node)) return;
    node.children.forEach((child) => valid.add(branchId(node, child)));
  });
  branchPoints = Object.fromEntries(Object.entries(branchPoints).filter(([id]) => valid.has(id)));
  if (selectedBranchId && !valid.has(selectedBranchId)) selectedBranchId = null;
}

function estimateLabelWidth(label) {
  return Math.max(42, ...splitLabelLines(label).map((line) => {
    const parts = parseLabelParts(line.trim());
    const indexWidth = parts.subscript ? 10 : 0;
    const headWidth = parts.head ? 8 : 0;
    return displayText(parts.stem).length * 10 + indexWidth + headWidth + 16;
  }));
}

function estimatePlainTextWidth(label) {
  return Math.max(82, label.length * 9 + 28);
}

function estimateTriangleTextWidth(label) {
  return Math.max(64, ...splitLabelLines(label).map((line) => {
    const parts = parseLabelParts(line.trim());
    const suffixWidth = (parts.head ? 7 : 0) + (parts.subscript ? 8 : 0);
    return estimateVisualTextWidth(parts.stem) + suffixWidth;
  }));
}

function estimateVisualTextWidth(label) {
  return [...displayText(label)].reduce((width, char) => {
    if (/\s/.test(char)) return width + 4.5;
    if (/[ilI.,'′]/.test(char)) return width + 4.5;
    if (/[A-Z]/.test(char)) return width + 9.5;
    if (/[mwMW]/.test(char)) return width + 11;
    return width + 7.4;
  }, 0);
}

function estimateStyledTextWidth(label, isLeaf) {
  const unit = isLeaf ? 9.5 : 12;
  return Math.max(isLeaf ? 26 : 34, displayText(label).length * unit);
}

function latexName(id) {
  return id.replace(/[^A-Za-z0-9-]/g, "-");
}

function safeSvgId(id) {
  return id.replace(/[^A-Za-z0-9_-]/g, "-");
}

function escapeLatex(value) {
  return value
    .replace(/\\/g, "\\textbackslash{}")
    .replace(/&/g, "\\&")
    .replace(/%/g, "\\%")
    .replace(/\$/g, "\\$")
    .replace(/#/g, "\\#")
    .replace(/_/g, "\\_")
    .replace(/{/g, "\\{")
    .replace(/}/g, "\\}")
    .replace(/~/g, "\\textasciitilde{}")
    .replace(/\^/g, "\\textasciicircum{}");
}

function displayText(value) {
  return replaceGreekNames(value, (entry) => entry.text);
}

function latexText(value) {
  GREEK_PATTERN.lastIndex = 0;
  let output = "";
  let lastIndex = 0;
  for (const match of value.matchAll(GREEK_PATTERN)) {
    const index = match.index ?? 0;
    output += escapeLatex(value.slice(lastIndex, index));
    output += `\\ensuremath{${GREEK_LETTERS[match[1].toLowerCase()].latex}}`;
    lastIndex = index + match[1].length;
  }
  output += escapeLatex(value.slice(lastIndex));
  return output;
}

function replaceGreekNames(value, replacer) {
  GREEK_PATTERN.lastIndex = 0;
  return value.replace(GREEK_PATTERN, (match) => replacer(GREEK_LETTERS[match.toLowerCase()]));
}

function serializeSvg(svg) {
  const clone = svg.cloneNode(true);
  clone.style.transform = "";
  clone.style.transformOrigin = "";
  clone.querySelectorAll(".movement-handles, .branch-handles, .movement-hit, .branch-hit").forEach((node) => node.remove());
  clone.setAttribute("xmlns", SVG_NS);
  clone.setAttribute("version", "1.1");
  inlineExportStyles(clone);
  return `<?xml version="1.0" encoding="UTF-8"?>\n${new XMLSerializer().serializeToString(clone)}`;
}

function inlineExportStyles(svg) {
  const defs = svg.querySelector("defs") || svg.insertBefore(el("defs"), svg.firstChild);
  const style = el("style", { type: "text/css" });
  style.textContent = `
    .tree-svg { background: transparent; }
    .branch,
    .movement,
    .triangle-roof {
      fill: none;
      stroke: #0f172a;
      stroke-width: 1.55px;
      stroke-linecap: round;
    }
    .branch.dashed,
    .movement.dashed {
      stroke-dasharray: 9 7;
    }
    .label-strike-line {
      stroke: #050505;
      stroke-width: 1.45px;
      stroke-linecap: round;
    }
    .node-label {
      fill: #050505;
      font-family: "Times New Roman", Times, serif;
      font-weight: 400;
      paint-order: stroke;
      stroke: rgba(255, 255, 255, 0.95);
      stroke-linejoin: round;
      stroke-width: 4px;
    }
    .node-label.phrase { font-size: 25px; }
    .node-label.leaf { font-size: 20px; }
    .node-label .subscript {
      font-size: 58%;
      font-style: italic;
    }
    .node-label .superscript { font-size: 58%; }
    .node-label .italic-stem,
    .node-label .initial-lowercase {
      font-style: italic;
    }
    .node-label .hollow-stem {
      fill: #ffffff;
      paint-order: normal;
      stroke: #050505;
      stroke-width: 0.8px;
    }
  `;
  defs.insertBefore(style, defs.firstChild);
}

function downloadSvg() {
  const svg = canvasWrap.querySelector("svg");
  if (!svg) return;
  downloadText("syntax-tree.svg", serializeSvg(svg), "image/svg+xml;charset=utf-8");
}

async function downloadPng({ transparent = true } = {}) {
  const svg = canvasWrap.querySelector("svg");
  if (!svg) return;
  const blob = new Blob([serializeSvg(svg)], { type: "image/svg+xml;charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const image = new Image();
  image.decoding = "async";
  await new Promise((resolve, reject) => {
    image.onload = resolve;
    image.onerror = reject;
    image.src = url;
  });
  const canvas = document.createElement("canvas");
  const width = Number(svg.getAttribute("width"));
  const height = Number(svg.getAttribute("height"));
  canvas.width = width * 2;
  canvas.height = height * 2;
  const context = canvas.getContext("2d");
  context.scale(2, 2);
  context.clearRect(0, 0, width, height);
  if (!transparent) {
    context.fillStyle = "#ffffff";
    context.fillRect(0, 0, width, height);
  }
  context.drawImage(image, 0, 0, width, height);
  URL.revokeObjectURL(url);
  canvas.toBlob((png) => {
    if (png) downloadBlob(transparent ? "syntax-tree-transparent.png" : "syntax-tree.png", png);
  }, "image/png");
}

function downloadText(filename, text, type) {
  downloadBlob(filename, new Blob([text], { type }));
}

function downloadBlob(filename, blob) {
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = filename;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  URL.revokeObjectURL(url);
}

async function saveCurrentHistory() {
  if (!current.tree || !window.SYNTREE?.loggedIn) return;
  saveHistory.disabled = true;
  saveHistory.textContent = L.saving || "Saving...";
  try {
    const response = await fetch(window.SYNTREE.saveUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": window.SYNTREE.csrf,
      },
      body: JSON.stringify({
        source: sourceInput.value,
        latex: current.latex,
        node_count: current.layout.nodes.length,
        movement_count: current.links.length,
      }),
    });
    const data = await response.json();
    if (!data.ok) throw new Error(data.error || "Save failed.");
    saveHistory.textContent = L.saved || "Saved";
    window.setTimeout(() => window.location.reload(), 450);
  } catch (error) {
    saveHistory.textContent = error.message || "Save failed";
    window.setTimeout(() => {
      saveHistory.textContent = L.saveAccount || "Save to account";
      saveHistory.disabled = false;
    }, 1800);
  }
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (char) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;",
  }[char]));
}
