const SAMPLE = "[CP PRN|Where_i [C' C0|is_z2+phi|\\[+EPP\\]|\\[+WH\\] [TP PRN|it [T' T0|=*is*=_z2 [vP v0|phi+thought_z1 [VP V0|=thought=_z1 [CP PRN|*where*_i [C' C0|that [^TP @he will go *where*@_i ]]]]]]]]]";
const LEGACY_SAMPLE = "[CP Which-book_i [C' C0|did [TP John_j [T' [T0|\\[+PST\\]] [vP -John-_j [v' read_k [VP -read-_k t_i]]]]]]]";
const PREVIOUS_SAMPLE = "[CP Which-book_i [C' C0|did [TP John_j [T' [T0|\\[+PST\\]] [vP =John=_j [v' read_k [VP =read=_k t_i]]]]]]]";
const SVG_NS = "http://www.w3.org/2000/svg";
const NODE_LABEL_X_OFFSET = -22;
const RIGHT_SUBTREE_LABEL_X_CORRECTION = 22;
const BRANCH_LABEL_GAP = 8;
const TRIANGLE_ROOF_X_OFFSET = 0;
const TRIANGLE_ROOF_TOP_Y = 25;
const TRIANGLE_ROOF_BASE_Y = 62;
const TRIANGLE_TEXT_BASELINE_GAP = 32;
const MIN_TRIANGLE_HALF_WIDTH = 24;
const UNIFORM_BRANCH_LEVEL_GAP = 88;
const UNIFORM_BRANCH_MIN_SPAN = 150;
const UNIFORM_BRANCH_MAX_DEPTH_SPAN = 160;
const UNIFORM_BRANCH_MIN_DEPTH_SPAN = 124;
const MOVEMENT_COLOR_STORAGE_KEY = "syntree-movement-custom-colors";
const DEFAULT_MOVEMENT_COLOR = "#0f172a";
const DEFAULT_MOVEMENT_COLORS = [DEFAULT_MOVEMENT_COLOR, "#1d4ed8", "#dc2626"];
const DEFAULT_ANNOTATION_COLORS = [...DEFAULT_MOVEMENT_COLORS, "#16a34a", "#eab308"];
const DEFAULT_FREE_CURVE_STYLE = "solid";
const DEFAULT_FREE_CURVE_WEIGHT = "regular";
const CATEGORY_LABEL_STEMS = new Set([
  "A", "Adj", "AdjP", "Adv", "AdvP", "Agr", "AgrP", "AP", "Asp", "AspP",
  "Aux", "AuxP", "C", "CP", "D", "Deg", "DegP", "DP", "I", "IP",
  "Mod", "ModP", "N", "Neg", "NegP", "NP", "Num", "NumP", "P", "PP",
  "Pred", "PredP", "PRN", "Spec", "T", "TP", "V", "Voice", "VoiceP", "VP",
  "v", "vP", "XP", "YP", "ZP",
]);
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
const generationCounter = document.getElementById("generationCounter");
const latexOutput = document.getElementById("latexOutput");
const branchStyle = document.getElementById("branchStyle");
const uniformBranchAngles = document.getElementById("uniformBranchAngles");
const movementStyle = document.getElementById("movementStyle");
const showMovement = document.getElementById("showMovement");
const movementToggles = document.getElementById("movementToggles");
const annotationTextInput = document.getElementById("annotationTextInput");
const annotationColorPalette = document.getElementById("annotationColorPalette");
const freeCurveStyle = document.getElementById("freeCurveStyle");
const freeCurveWeight = document.getElementById("freeCurveWeight");
const hiddenBranchesPanel = document.getElementById("hiddenBranchesPanel");
const hiddenBranchesList = document.getElementById("hiddenBranchesList");
const restoreAllBranches = document.getElementById("restoreAllBranches");
const saveHistory = document.getElementById("saveHistory");
const L = window.SYNTREE?.labels || {};
const buttons = {
  loadSample: document.getElementById("loadSample"),
  svg: document.getElementById("downloadSvg"),
  whitePng: document.getElementById("downloadWhitePng"),
  png: document.getElementById("downloadPng"),
  forestLatex: document.getElementById("downloadForestLatex"),
  tikzLatex: document.getElementById("downloadTikzLatex"),
  undoInput: document.getElementById("undoInput"),
  redoInput: document.getElementById("redoInput"),
  copy: document.getElementById("copyLatex"),
  addAnnotation: document.getElementById("addAnnotation"),
  addSegmentCurve: document.getElementById("addSegmentCurve"),
  deleteSelectedExtra: document.getElementById("deleteSelectedExtra"),
  zoomIn: document.getElementById("zoomIn"),
  zoomOut: document.getElementById("zoomOut"),
  zoomReset: document.getElementById("zoomReset"),
};
const helpOpen = document.getElementById("helpOpen");
const helpDialog = document.getElementById("helpDialog");
const helpClose = document.getElementById("helpClose");

let nextId = 1;
let previewZoom = 1;
let current = { tree: null, layout: null, links: [], latex: "", forestLatex: "", tikzLatex: "" };
let movementPoints = {};
let movementVisibility = {};
let movementColors = {};
let movementStyles = {};
let customMovementColors = loadCustomMovementColors();
let branchPoints = {};
let hiddenBranches = {};
let labelOffsets = {};
let trianglePoints = {};
let freeAnnotations = [];
let freeCurves = [];
let selectedMovementId = null;
let selectedBranchId = null;
let selectedLabelId = null;
let selectedTriangleId = null;
let selectedAnnotationId = null;
let selectedFreeCurveId = null;
let draggingMovement = null;
let draggingBranch = null;
let draggingLabel = null;
let draggingTriangle = null;
let draggingAnnotation = null;
let draggingFreeCurve = null;
let measuredLabelAnchors = {};
let measuredStrikeLines = {};
let nextAnnotationId = 1;
let nextFreeCurveId = 1;
let displayedGenerationCount = Math.max(0, Number(generationCounter?.dataset.count || 0));
let selectedAnnotationColor = DEFAULT_MOVEMENT_COLOR;
let inputHistory = [SAMPLE];
let inputHistoryIndex = 0;
let inputHistoryTimer = null;

sourceInput.value = SAMPLE;

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

sourceInput.addEventListener("input", () => {
  scheduleInputHistory(sourceInput.value);
  render();
});

for (const node of [branchStyle, uniformBranchAngles, movementStyle, showMovement]) {
  node.addEventListener("input", render);
  node.addEventListener("change", render);
}

buttons.loadSample.addEventListener("click", () => {
  setInputSource(SAMPLE);
});
buttons.svg.addEventListener("click", downloadSvg);
buttons.whitePng.addEventListener("click", () => downloadPng({ transparent: false }));
buttons.png.addEventListener("click", () => downloadPng({ transparent: true }));
buttons.forestLatex.addEventListener("click", () => downloadText("syntax-tree-forest.tex", current.forestLatex, "text/x-tex;charset=utf-8"));
buttons.tikzLatex.addEventListener("click", () => downloadText("syntax-tree-visual-tikz.tex", current.tikzLatex, "text/x-tex;charset=utf-8"));
buttons.zoomIn.addEventListener("click", () => setPreviewZoom(previewZoom + 0.1));
buttons.zoomOut.addEventListener("click", () => setPreviewZoom(previewZoom - 0.1));
buttons.zoomReset.addEventListener("click", () => setPreviewZoom(1));
buttons.undoInput.addEventListener("click", undoInputChange);
buttons.redoInput.addEventListener("click", redoInputChange);
if (buttons.copy) {
  buttons.copy.addEventListener("click", async () => {
    await navigator.clipboard.writeText(current.latex);
    buttons.copy.textContent = L.copied || "Copied";
    window.setTimeout(() => { buttons.copy.textContent = L.copy || "Copy"; }, 1200);
  });
}

if (buttons.addAnnotation) {
  buttons.addAnnotation.addEventListener("click", addFreeAnnotation);
}

if (buttons.addSegmentCurve) {
  buttons.addSegmentCurve.addEventListener("click", addFreeCurve);
}

if (buttons.deleteSelectedExtra) {
  buttons.deleteSelectedExtra.addEventListener("click", deleteSelectedExtra);
}

if (annotationTextInput) {
  annotationTextInput.addEventListener("input", updateSelectedAnnotationText);
}

if (restoreAllBranches) {
  restoreAllBranches.addEventListener("click", () => {
    hiddenBranches = {};
    render();
  });
}

for (const node of [freeCurveStyle, freeCurveWeight]) {
  if (!node) continue;
  node.addEventListener("change", updateSelectedFreeCurveStyle);
}

if (saveHistory) {
  saveHistory.addEventListener("click", saveCurrentHistory);
}

document.querySelectorAll(".history-item").forEach((button) => {
  button.addEventListener("click", () => {
    setInputSource(button.dataset.source || "");
  });
});

canvasWrap.addEventListener("wheel", (event) => {
  if (!current.tree || !event.ctrlKey) return;
  event.preventDefault();
  const factor = Math.exp(-event.deltaY * 0.01);
  setPreviewZoomAt(previewZoom * factor, event.clientX, event.clientY);
}, { passive: false });

document.addEventListener("pointermove", (event) => {
  if (!draggingMovement && !draggingBranch && !draggingLabel && !draggingTriangle && !draggingAnnotation && !draggingFreeCurve) return;
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

  if (draggingLabel) {
    const dx = point.x - draggingLabel.last.x;
    const dy = point.y - draggingLabel.last.y;
    moveLabelOffset(draggingLabel.id, dx, dy);
    draggingLabel.last = point;
  }

  if (draggingTriangle) {
    if (draggingTriangle.handle === "body") {
      const dx = point.x - draggingTriangle.last.x;
      const dy = point.y - draggingTriangle.last.y;
      moveLabelOffset(draggingTriangle.id, dx, dy);
      draggingTriangle.last = point;
    } else {
      const node = current.layout?.nodes.find((item) => item.id === draggingTriangle.id);
      if (!node) return;
      const shape = triangleShapeFor(node);
      trianglePoints[draggingTriangle.id] = updateTrianglePoint(shape, draggingTriangle.handle, {
        x: point.x - labelX(node),
        y: point.y - labelY(node),
      });
    }
  }

  if (draggingAnnotation) {
    const annotation = freeAnnotations.find((item) => item.id === draggingAnnotation.id);
    if (!annotation) return;
    annotation.x += point.x - draggingAnnotation.last.x;
    annotation.y += point.y - draggingAnnotation.last.y;
    draggingAnnotation.last = point;
  }

  if (draggingFreeCurve) {
    const curve = freeCurves.find((item) => item.id === draggingFreeCurve.id);
    if (!curve) return;
    if (draggingFreeCurve.handle === "body") {
      const dx = point.x - draggingFreeCurve.last.x;
      const dy = point.y - draggingFreeCurve.last.y;
      curve.points = curve.points.map((item) => ({ x: item.x + dx, y: item.y + dy }));
      draggingFreeCurve.last = point;
    } else {
      curve.points[draggingFreeCurve.handle] = point;
    }
  }

  render();
});

document.addEventListener("pointerup", () => {
  draggingMovement = null;
  draggingBranch = null;
  draggingLabel = null;
  draggingTriangle = null;
  draggingAnnotation = null;
  draggingFreeCurve = null;
});

function scheduleInputHistory(value) {
  if (inputHistoryTimer) window.clearTimeout(inputHistoryTimer);
  inputHistoryTimer = window.setTimeout(() => {
    inputHistoryTimer = null;
    commitInputHistory(value);
  }, 350);
  updateInputHistoryControls(value);
}

function commitInputHistory(value = sourceInput.value) {
  if (inputHistoryTimer) {
    window.clearTimeout(inputHistoryTimer);
    inputHistoryTimer = null;
  }
  if (inputHistory[inputHistoryIndex] === value) {
    updateInputHistoryControls();
    return;
  }
  inputHistory = inputHistory.slice(0, inputHistoryIndex + 1);
  inputHistory.push(value);
  if (inputHistory.length > 100) inputHistory.shift();
  inputHistoryIndex = inputHistory.length - 1;
  updateInputHistoryControls();
}

function setInputSource(value) {
  commitInputHistory(sourceInput.value);
  sourceInput.value = value;
  commitInputHistory(value);
  render();
}

function undoInputChange() {
  commitInputHistory(sourceInput.value);
  if (inputHistoryIndex <= 0) return;
  inputHistoryIndex -= 1;
  sourceInput.value = inputHistory[inputHistoryIndex];
  updateInputHistoryControls();
  render();
  sourceInput.focus();
}

function redoInputChange() {
  if (inputHistoryTimer) commitInputHistory(sourceInput.value);
  if (inputHistoryIndex >= inputHistory.length - 1) return;
  inputHistoryIndex += 1;
  sourceInput.value = inputHistory[inputHistoryIndex];
  updateInputHistoryControls();
  render();
  sourceInput.focus();
}

function updateInputHistoryControls(pendingValue = sourceInput.value) {
  buttons.undoInput.disabled = inputHistoryIndex <= 0 && pendingValue === inputHistory[inputHistoryIndex];
  buttons.redoInput.disabled = inputHistoryIndex >= inputHistory.length - 1;
}

render();

function render() {
  localStorage.setItem("syntree-source", sourceInput.value);
  const parsed = parseBracketTree(sourceInput.value);
  if (parsed.error) {
    current = { tree: null, layout: null, links: [], latex: "", forestLatex: "", tikzLatex: "" };
    parseNotice.className = "notice error";
    parseNotice.textContent = parsed.error;
    if (latexOutput) latexOutput.textContent = L.typesettingPlaceholder || "Typesetting code appears after a valid parse.";
    canvasWrap.innerHTML = `<div class="empty-state">${escapeHtml(L.enterExpression || "Enter a valid tree expression to show the preview.")}</div>`;
    if (hiddenBranchesPanel) hiddenBranchesPanel.hidden = true;
    renderAnnotationColorPalette();
    setExportEnabled(false);
    setZoomEnabled(false);
    return;
  }

  const detectedLinks = detectMovementLinks(parsed.tree);
  const treeNodes = flattenTree(parsed.tree);
  pruneMovementVisibility(detectedLinks);
  pruneMovementColors(detectedLinks);
  pruneMovementStyles(detectedLinks);
  renderMovementToggles(detectedLinks, treeNodes);
  const links = showMovement.checked ? detectedLinks.filter((link) => movementVisibility[link.id] !== false) : [];
  const layout = layoutTree(parsed.tree);
  pruneMovementPoints(links);
  pruneBranchPoints(layout.nodes);
  pruneHiddenBranches(layout.nodes);
  pruneLabelOffsets(layout.nodes);
  pruneTrianglePoints(layout.nodes);
  renderHiddenBranchControls(layout.nodes);
  const forestLatex = toForestLatex(parsed.tree, links, layout);
  current = { tree: parsed.tree, layout, links, latex: forestLatex, forestLatex, tikzLatex: "" };
  renderAnnotationColorPalette();

  parseNotice.className = "notice success";
  parseNotice.textContent = L.foundStats
    ? L.foundStats.replace("{nodes}", String(layout.nodes.length)).replace("{links}", String(links.length))
    : `Found ${layout.nodes.length} nodes and ${links.length} movement links.`;
  if (latexOutput) latexOutput.textContent = forestLatex;
  measuredLabelAnchors = {};
  measuredStrikeLines = {};
  const measurementSvg = renderSvg(layout, links);
  canvasWrap.replaceChildren(renderZoomedSvg(measurementSvg));
  measuredLabelAnchors = measureLabelAnchors(measurementSvg, layout.nodes);
  measuredStrikeLines = measureStrikeLines(measurementSvg, layout.nodes);
  canvasWrap.replaceChildren(renderZoomedSvg(renderSvg(layout, links)));
  current.tikzLatex = toVisualTikzLatex(parsed.tree, links, layout);
  setExportEnabled(true);
  setZoomEnabled(true);
  updateFreeToolState();
}

function setExportEnabled(enabled) {
  buttons.svg.disabled = !enabled;
  buttons.whitePng.disabled = !enabled;
  buttons.png.disabled = !enabled;
  buttons.forestLatex.disabled = !enabled;
  buttons.tikzLatex.disabled = !enabled;
  if (buttons.copy) buttons.copy.disabled = !enabled;
  if (saveHistory) saveHistory.disabled = !enabled;
  if (buttons.addAnnotation) buttons.addAnnotation.disabled = !enabled;
  if (buttons.addSegmentCurve) buttons.addSegmentCurve.disabled = !enabled;
  if (annotationTextInput) annotationTextInput.disabled = !enabled;
  updateFreeToolState();
}

function setZoomEnabled(enabled) {
  for (const button of [buttons.zoomIn, buttons.zoomOut, buttons.zoomReset]) {
    button.disabled = !enabled;
  }
  updateZoomControls();
}

function setPreviewZoom(value) {
  const rect = canvasWrap.getBoundingClientRect();
  setPreviewZoomAt(value, rect.left + canvasWrap.clientWidth / 2, rect.top + canvasWrap.clientHeight / 2);
}

function setPreviewZoomAt(value, clientX, clientY) {
  const previousZoom = previewZoom;
  previewZoom = Math.max(0.5, Math.min(2.5, Math.round(value * 100) / 100));
  if (previousZoom === previewZoom) return;
  updateZoomControls();
  const svg = canvasWrap.querySelector("svg");
  if (!svg) return;
  const rect = canvasWrap.getBoundingClientRect();
  const offsetX = clientX - rect.left;
  const offsetY = clientY - rect.top;
  const contentX = (canvasWrap.scrollLeft + offsetX) / previousZoom;
  const contentY = (canvasWrap.scrollTop + offsetY) / previousZoom;
  applyPreviewZoom(svg);
  canvasWrap.scrollLeft = contentX * previewZoom - offsetX;
  canvasWrap.scrollTop = contentY * previewZoom - offsetY;
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
    const styleRow = document.createElement("label");
    styleRow.className = "movement-style-row";
    const styleSelect = document.createElement("select");
    styleSelect.setAttribute("aria-label", `${movementToggleText(link, byId)}: ${L.movementStyle || "Movement style"}`);
    [["solid", L.solid || "Solid"], ["dashed", L.dashed || "Dashed"]].forEach(([value, textValue]) => {
      const option = document.createElement("option");
      option.value = value;
      option.textContent = textValue;
      styleSelect.appendChild(option);
    });
    styleSelect.value = movementStyleFor(link.id);
    styleSelect.disabled = !showMovement.checked;
    styleSelect.addEventListener("change", () => {
      movementStyles[link.id] = styleSelect.value;
      render();
    });
    styleRow.appendChild(styleSelect);
    item.append(label, styleRow, renderMovementColorPalette(link));
    movementToggles.appendChild(item);
  });
}

function movementStyleFor(linkId) {
  return movementStyles[linkId] === "dashed" || movementStyles[linkId] === "solid"
    ? movementStyles[linkId]
    : movementStyle.value;
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
  if (isEmptyTerminalLabel(label)) {
    const index = getIndex(label) || "";
    const template = L.emptyMovementPosition || "empty position ({index})";
    return template.replace("{index}", index);
  }
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
    if (tokens[index] === "]") {
      index += 1;
      return { id: makeId(), label: "@empty", children: [] };
    }
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
    return { id: makeId(), label: normalizeEmptyNodeLabel(label, children), children };
  }

  try {
    const tree = readNode();
    if (index !== tokens.length) throw new Error(`Unexpected content after "${tokens[index]}".`);
    return { tree, error: null };
  } catch (error) {
    return { tree: null, error: error.message || "Parsing failed." };
  }
}

function normalizeEmptyNodeLabel(label, children = []) {
  const value = String(label);
  if (!children.length && /^_[A-Za-z0-9]+$/.test(value)) return `@empty${value}`;
  return value;
}

function tokenize(input) {
  const tokens = [];
  let currentToken = "";
  let quoted = false;
  let escaping = false;
  for (const char of input) {
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
    const bracketChar = !quoted && char === "(" ? "[" : (!quoted && char === ")" ? "]" : char);
    if (!quoted && (bracketChar === "[" || bracketChar === "]")) {
      pushToken(tokens, currentToken);
      currentToken = "";
      tokens.push(bracketChar);
      continue;
    }
    if (!quoted && /\s/.test(bracketChar)) {
      pushToken(tokens, currentToken);
      currentToken = "";
      continue;
    }
    currentToken += bracketChar;
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
  if (isInvisibleEmptyNode(node)) return;
  const owner = triangleOwner || (isTriangleNode(node) ? node : null);
  visit({ id: owner && node !== owner ? owner.id : node.id, label: node.label });
  visibleChildren(node).forEach((child) => collectMovementCandidates(child, owner, visit));
}

function flattenTree(tree) {
  const nodes = [];
  (function visit(node) {
    if (isInvisibleEmptyNode(node)) return;
    nodes.push(node);
    visibleChildren(node).forEach(visit);
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
  const uniformAngles = Boolean(uniformBranchAngles?.checked);
  const levelGap = uniformAngles ? UNIFORM_BRANCH_LEVEL_GAP : 88;
  const padding = { x: 70, y: 52 };
  const nodes = [];
  const uniformSpan = uniformAngles ? uniformBranchSpanForTree(tree) : null;

  function position(node, x, depth, parent = null) {
    const childList = isTriangleNode(node) ? [] : visibleChildren(node);
    node._parent = parent;
    node.x = x;
    node.y = depth * levelGap;
    node.width = isEmptyTerminalLabel(node.label)
      ? 0
      : Math.max(
        estimateLabelWidth(node.label),
        isTriangleNode(node) ? estimateTriangleTextWidth(getTriangleText(node)) : 0,
        64,
      );
    nodes.push(node);
    if (!childList.length) return;

    const span = uniformSpan ?? branchSpanFor(node, childList, depth);
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

function uniformBranchSpanForTree(tree) {
  let span = UNIFORM_BRANCH_MIN_SPAN;
  (function visit(node, depth = 0) {
    const childList = isTriangleNode(node) ? [] : visibleChildren(node);
    if (childList.length > 1) {
      span = Math.max(span, uniformBranchSpanFor(node, childList, depth));
    }
    childList.forEach((child) => visit(child, depth + 1));
  })(tree);
  return span;
}

function uniformBranchSpanFor(node, children, depth) {
  const labelRoom = children.reduce((max, child) => {
    return Math.max(max, (estimateLabelWidth(node.label) + estimateLabelWidth(child.label)) / 2 + 44);
  }, 0);
  const depthRoom = Math.max(UNIFORM_BRANCH_MIN_DEPTH_SPAN, UNIFORM_BRANCH_MAX_DEPTH_SPAN - depth * 8);
  return Math.max(labelRoom, depthRoom);
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
  nodes.filter((node) => !isEmptyTerminalLabel(node.label)).forEach((node) => {
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
  visibleChildren(node).forEach((child) => offsetSubtree(child, dx));
}

function renderSvg(layout, links) {
  const byId = new Map(layout.nodes.map((node) => [node.id, node]));
  const bottomPad = links.length ? 190 : 60;
  const extraBounds = freeExtrasBounds();
  const svgWidth = Math.max(layout.width, extraBounds.maxX + 80);
  const svgHeight = Math.max(layout.height + bottomPad, extraBounds.maxY + 80);
  const svg = el("svg", {
    class: "tree-svg",
    width: svgWidth,
    height: svgHeight,
    viewBox: `0 0 ${svgWidth} ${svgHeight}`,
    role: "img",
    "aria-label": "Generated syntax tree",
  });
  svg.addEventListener("pointerdown", (event) => {
    if (event.target === svg) {
      clearSelection();
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
  const freeCurveLayer = el("g", { class: "free-curve-layer" });
  const labelLayer = el("g", { class: "label-layer" });
  const annotationLayer = el("g", { class: "annotation-layer" });
  const editLayer = el("g", { class: "edit-layer" });
  const movementRanks = movementRankMap(links, byId);

  layout.nodes.forEach((node) => {
    if (isTriangleNode(node)) return;
    visibleChildren(node).forEach((child) => {
      const id = branchId(node, child);
      if (hiddenBranches[id]) return;
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
        clearSelection();
        selectedBranchId = id;
        render();
      };
      line.addEventListener("pointerdown", selectBranch);
      hitLine.addEventListener("pointerdown", selectBranch);
      branchLayer.appendChild(line);
      branchLayer.appendChild(hitLine);
      if (selectedBranchId === id) {
        editLayer.appendChild(renderBranchHandles(id, points));
        editLayer.appendChild(renderBranchHideMenu(id, points));
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
      class: `movement ${movementStyleFor(link.id)} ${selectedMovementId === link.id ? "selected" : ""}`,
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
      clearSelection();
      selectedMovementId = link.id;
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

  freeCurves.forEach((curve) => {
    const d = freeCurvePathD(curveControlPoints(curve));
    if (!d) return;
    const classes = [
      "free-curve",
      curve.style === "dashed" ? "dashed" : "solid",
      curve.weight === "bold" ? "bold" : "regular",
      selectedFreeCurveId === curve.id ? "selected" : "",
    ].filter(Boolean).join(" ");
    const path = el("path", { d, class: classes });
    const hitPath = el("path", { d, class: "free-curve-hit" });
    const selectCurve = (event) => {
      event.preventDefault();
      event.stopPropagation();
      clearSelection();
      selectedFreeCurveId = curve.id;
      draggingFreeCurve = { id: curve.id, handle: "body", last: svgPoint(svg, event) || curve.points[0] };
      syncFreeCurveControls(curve);
      render();
    };
    const openCurveMenu = (event) => {
      event.preventDefault();
      event.stopPropagation();
      clearSelection();
      selectedFreeCurveId = curve.id;
      syncFreeCurveControls(curve);
      render();
    };
    path.addEventListener("pointerdown", selectCurve);
    hitPath.addEventListener("pointerdown", selectCurve);
    path.addEventListener("contextmenu", openCurveMenu);
    hitPath.addEventListener("contextmenu", openCurveMenu);
    freeCurveLayer.appendChild(path);
    freeCurveLayer.appendChild(hitPath);
    if (selectedFreeCurveId === curve.id) {
      editLayer.appendChild(renderFreeCurveHandles(curve));
      editLayer.appendChild(renderExtraDeleteMenu(freeCurveMenuAnchor(curve)));
    }
  });

  layout.nodes.forEach((node) => {
    const group = el("g", {
      class: `node-group ${selectedLabelId === node.id ? "label-selected" : ""} ${selectedTriangleId === node.id ? "triangle-selected" : ""}`,
      transform: `translate(${labelX(node)}, ${labelY(node)})`,
      "data-node-id": node.id,
    });
    if (isTriangleNode(node)) {
      renderTriangle(group, node);
      attachTriangleDrag(group, node);
    } else {
      renderLabel(group, node.label, visibleChildren(node).length === 0, node.id);
      attachLabelDrag(group, node);
    }
    labelLayer.appendChild(group);
    if (selectedLabelId === node.id && !isTriangleNode(node)) {
      editLayer.appendChild(renderLabelSelection(node));
    }
    if (selectedTriangleId === node.id && isTriangleNode(node)) {
      editLayer.appendChild(renderTriangleHandles(node));
    }
  });

  freeAnnotations.forEach((annotation) => {
    const group = renderFreeAnnotation(annotation);
    annotationLayer.appendChild(group);
    if (selectedAnnotationId === annotation.id) {
      editLayer.appendChild(renderAnnotationSelection(annotation));
      editLayer.appendChild(renderExtraDeleteMenu(annotationMenuAnchor(annotation)));
    }
  });

  svg.appendChild(branchLayer);
  svg.appendChild(movementLayer);
  svg.appendChild(freeCurveLayer);
  svg.appendChild(labelLayer);
  svg.appendChild(annotationLayer);
  svg.appendChild(editLayer);

  return svg;
}

function renderLabel(group, label, isLeaf, nodeId = "") {
  if (isLeaf && isEmptyTerminalLabel(label)) {
    group.appendChild(el("circle", {
      class: "empty-terminal-hit",
      cx: 0,
      cy: 0,
      r: 12,
    }));
    return;
  }
  const lines = splitDisplayLabelLines(label);
  const leafStyle = usesLeafTextStyle(label, isLeaf);
  const lineGap = leafStyle ? 24 : 30;
  lines.forEach((line, index) => {
    const y = (index - (lines.length - 1) / 2) * lineGap;
    const text = el("text", {
      class: `node-label ${leafStyle ? "leaf" : "phrase"}`,
      x: 0,
      y,
      "text-anchor": "middle",
      "dominant-baseline": "middle",
      "data-line-index": index,
    });
    const meta = appendStyledLabel(text, line, leafStyle);
    group.appendChild(text);
    if (meta.struck) {
      group.appendChild(renderStrikeLine(meta, y, nodeId, index));
    }
  });
}

function attachLabelDrag(group, node) {
  group.addEventListener("pointerdown", (event) => {
    event.preventDefault();
    event.stopPropagation();
    const point = svgPoint(group.ownerSVGElement, event);
    if (!point) return;
    clearSelection();
    selectedLabelId = node.id;
    draggingLabel = { id: node.id, last: point };
    render();
  });
}

function attachTriangleDrag(group, node) {
  group.addEventListener("pointerdown", (event) => {
    event.preventDefault();
    event.stopPropagation();
    const point = svgPoint(group.ownerSVGElement, event);
    if (!point) return;
    clearSelection();
    selectedTriangleId = node.id;
    draggingTriangle = { id: node.id, handle: "body", last: point };
    render();
  });
}

function labelX(node) {
  return node.x + nodeLabelOffset(node) + labelOffset(node).x;
}

function labelY(node) {
  return node.y + labelOffset(node).y;
}

function labelOffset(node) {
  return labelOffsets[node.id] || { x: 0, y: 0 };
}

function moveLabelOffset(nodeId, dx, dy) {
  const offset = labelOffsets[nodeId] || { x: 0, y: 0 };
  labelOffsets[nodeId] = {
    x: offset.x + dx,
    y: offset.y + dy,
  };
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
  const shape = triangleShapeFor(node);
  const textPoint = triangleTextPoint(shape);
  const roofPath = `M ${shape.left.x} ${shape.left.y} L ${shape.top.x} ${shape.top.y} L ${shape.right.x} ${shape.right.y} Z`;
  group.appendChild(el("path", {
    class: "triangle-hit",
    d: roofPath,
  }));
  group.appendChild(el("path", {
    class: "branch triangle-roof",
    d: roofPath,
  }));
  const text = el("text", {
    class: "node-label leaf triangle-text",
    x: textPoint.x,
    y: textPoint.y,
    "text-anchor": "middle",
    "dominant-baseline": "alphabetic",
  });
  appendStyledLabel(text, roofText);
  group.appendChild(text);
}

function triangleShapeFor(node) {
  return trianglePoints[node.id] || defaultTriangleShape(node);
}

function updateTrianglePoint(shape, handle, point) {
  const next = cloneTriangleShape(shape);
  if (handle === "left" || handle === "right") {
    const halfWidth = Math.max(MIN_TRIANGLE_HALF_WIDTH, Math.abs(point.x - next.top.x));
    next.left = { x: next.top.x - halfWidth, y: point.y };
    next.right = { x: next.top.x + halfWidth, y: point.y };
    return next;
  }
  const halfWidth = Math.max(MIN_TRIANGLE_HALF_WIDTH, Math.abs(next.right.x - next.left.x) / 2);
  next.top = { x: point.x, y: point.y };
  next.left = { x: point.x - halfWidth, y: next.left.y };
  next.right = { x: point.x + halfWidth, y: next.right.y };
  return next;
}

function cloneTriangleShape(shape) {
  return {
    top: { ...shape.top },
    left: { ...shape.left },
    right: { ...shape.right },
  };
}

function defaultTriangleShape(node) {
  const roofText = getTriangleText(node);
  const hasDecoratedSuffix = splitLabelLines(roofText).some((line) => {
    const parts = parseLabelParts(line);
    return Boolean(parts.head || parts.subscript);
  });
  const width = Math.max(76, estimateTriangleTextWidth(roofText) + (hasDecoratedSuffix ? 22 : 14));
  return {
    top: { x: TRIANGLE_ROOF_X_OFFSET, y: TRIANGLE_ROOF_TOP_Y },
    left: { x: TRIANGLE_ROOF_X_OFFSET - width / 2, y: TRIANGLE_ROOF_BASE_Y },
    right: { x: TRIANGLE_ROOF_X_OFFSET + width / 2, y: TRIANGLE_ROOF_BASE_Y },
  };
}

function triangleTextPoint(shape) {
  return {
    x: (shape.left.x + shape.right.x) / 2,
    y: Math.max(shape.left.y, shape.right.y) + TRIANGLE_TEXT_BASELINE_GAP,
  };
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
      clearSelection();
      selectedMovementId = link.id;
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
      clearSelection();
      selectedBranchId = id;
      draggingBranch = { id, handle };
    });
    group.appendChild(circle);
  });
  return group;
}

function renderBranchHideMenu(id, points) {
  const label = L.hideBranch || "Hide branch";
  const width = Math.max(76, estimateVisualTextWidth(label) + 24);
  const anchor = {
    x: (points.start.x + points.end.x) / 2 + 12,
    y: Math.max(12, (points.start.y + points.end.y) / 2 - 34),
  };
  const group = el("g", {
    class: "extra-delete-menu branch-hide-menu",
    transform: `translate(${anchor.x}, ${anchor.y})`,
    tabindex: 0,
    role: "button",
    "aria-label": label,
  });
  group.appendChild(el("rect", {
    class: "extra-delete-menu-bg",
    x: 0,
    y: 0,
    width,
    height: 28,
    rx: 6,
  }));
  const text = el("text", {
    class: "extra-delete-menu-text",
    x: width / 2,
    y: 18,
    "text-anchor": "middle",
  });
  text.textContent = label;
  group.appendChild(text);
  const hide = (event) => {
    event.preventDefault();
    event.stopPropagation();
    hiddenBranches[id] = true;
    selectedBranchId = null;
    render();
  };
  group.addEventListener("pointerdown", (event) => {
    event.preventDefault();
    event.stopPropagation();
  });
  group.addEventListener("click", hide);
  group.addEventListener("keydown", (event) => {
    if (event.key === "Enter" || event.key === " ") hide(event);
  });
  return group;
}

function renderLabelSelection(node) {
  const box = measuredLabelAnchors[node.id];
  const fallbackWidth = estimateLabelWidth(node.label);
  const fallbackHeight = labelBlockHeight(node);
  const x = box ? box.leftX - 8 : labelX(node) - fallbackWidth / 2 - 8;
  const y = box ? box.topY - 6 : labelY(node) - fallbackHeight / 2 - 6;
  const width = box ? box.rightX - box.leftX + 16 : fallbackWidth + 16;
  const height = box ? box.bottomY - box.topY + 12 : fallbackHeight + 12;
  const group = el("g", { class: "label-selection" });
  group.appendChild(el("rect", {
    class: "label-selection-box",
    x,
    y,
    width,
    height,
    rx: 4,
  }));
  return group;
}

function renderTriangleHandles(node) {
  const shape = triangleShapeFor(node);
  const group = el("g", { class: "triangle-handles" });
  [
    ["left", shape.left],
    ["top", shape.top],
    ["right", shape.right],
  ].forEach(([handle, point]) => {
    const circle = el("circle", {
      class: `triangle-handle ${handle}`,
      cx: labelX(node) + point.x,
      cy: labelY(node) + point.y,
      r: 5.5,
      tabindex: 0,
      "aria-label": `${handle} triangle handle`,
    });
    circle.addEventListener("pointerdown", (event) => {
      event.preventDefault();
      event.stopPropagation();
      trianglePoints[node.id] = cloneTriangleShape(shape);
      clearSelection();
      selectedTriangleId = node.id;
      draggingTriangle = { id: node.id, handle };
    });
    group.appendChild(circle);
  });
  return group;
}

function addFreeAnnotation() {
  if (!current.tree) return;
  const text = annotationTextInput?.value.trim() || L.defaultAnnotationText || "(note)";
  const point = visibleCanvasTopRight();
  const annotation = {
    id: `annotation-${nextAnnotationId++}`,
    x: point.x - 90,
    y: point.y + 12,
    text: text.trim(),
    color: selectedAnnotationColor,
  };
  freeAnnotations.push(annotation);
  clearSelection();
  selectedAnnotationId = annotation.id;
  syncAnnotationInput(annotation);
  render();
}

function addFreeCurve() {
  if (!current.tree) return;
  const anchor = visibleCanvasTopRight();
  const curve = {
    id: `free-curve-${nextFreeCurveId++}`,
    style: freeCurveStyle?.value || DEFAULT_FREE_CURVE_STYLE,
    weight: freeCurveWeight?.value || DEFAULT_FREE_CURVE_WEIGHT,
    points: [
      { x: anchor.x - 145, y: anchor.y + 138 },
      { x: anchor.x - 132, y: anchor.y + 18 },
      { x: anchor.x - 22, y: anchor.y + 6 },
    ],
  };
  freeCurves.push(curve);
  clearSelection();
  selectedFreeCurveId = curve.id;
  syncFreeCurveControls(curve);
  render();
}

function deleteSelectedExtra() {
  if (selectedAnnotationId) {
    freeAnnotations = freeAnnotations.filter((item) => item.id !== selectedAnnotationId);
    selectedAnnotationId = null;
    render();
    return;
  }
  if (selectedFreeCurveId) {
    freeCurves = freeCurves.filter((item) => item.id !== selectedFreeCurveId);
    selectedFreeCurveId = null;
    render();
  }
}

function updateFreeToolState() {
  if (buttons.deleteSelectedExtra) {
    buttons.deleteSelectedExtra.disabled = !current.tree || (!selectedAnnotationId && !selectedFreeCurveId);
  }
  if (annotationTextInput) {
    annotationTextInput.disabled = !current.tree;
  }
  const selectedCurve = selectedFreeCurveId ? freeCurves.find((curve) => curve.id === selectedFreeCurveId) : null;
  if (freeCurveStyle) freeCurveStyle.disabled = !current.tree || !selectedCurve;
  if (freeCurveWeight) freeCurveWeight.disabled = !current.tree || !selectedCurve;
  if (selectedCurve) syncFreeCurveControls(selectedCurve);
}

function updateSelectedAnnotationText() {
  const annotation = selectedAnnotationId ? freeAnnotations.find((item) => item.id === selectedAnnotationId) : null;
  if (!annotation || !annotationTextInput) return;
  annotation.text = annotationTextInput.value.trim() || L.defaultAnnotationText || "(note)";
  render();
}

function syncAnnotationInput(annotation) {
  if (annotationTextInput) annotationTextInput.value = annotation.text || "";
  selectedAnnotationColor = isHexColor(annotation.color) ? annotation.color : DEFAULT_MOVEMENT_COLOR;
  renderAnnotationColorPalette();
}

function renderAnnotationColorPalette() {
  if (!annotationColorPalette) return;
  annotationColorPalette.replaceChildren();
  const selected = selectedAnnotationId ? freeAnnotations.find((item) => item.id === selectedAnnotationId) : null;
  const activeColor = selected && isHexColor(selected.color) ? selected.color : selectedAnnotationColor;
  [...DEFAULT_ANNOTATION_COLORS, ...customMovementColors].forEach((color, index) => {
    const swatch = document.createElement("button");
    swatch.type = "button";
    swatch.className = "movement-color-swatch";
    swatch.disabled = !current.tree;
    swatch.setAttribute("aria-label", L.annotationColor || "Annotation color");
    if (color) {
      swatch.style.backgroundColor = color;
      if (activeColor === color) swatch.classList.add("active");
      swatch.addEventListener("click", () => setAnnotationColor(color));
    } else {
      swatch.classList.add("empty");
      swatch.addEventListener("click", () => openAnnotationColorPicker(index - DEFAULT_ANNOTATION_COLORS.length));
    }
    annotationColorPalette.appendChild(swatch);
  });
}

function setAnnotationColor(color) {
  if (!isHexColor(color)) return;
  selectedAnnotationColor = color;
  const annotation = selectedAnnotationId ? freeAnnotations.find((item) => item.id === selectedAnnotationId) : null;
  if (annotation) annotation.color = color;
  render();
}

function openAnnotationColorPicker(slotIndex) {
  const picker = document.createElement("input");
  picker.type = "color";
  picker.value = selectedAnnotationColor;
  picker.className = "visually-hidden-color-input";
  picker.addEventListener("change", () => {
    if (!isHexColor(picker.value)) return;
    customMovementColors[slotIndex] = picker.value;
    saveCustomMovementColors();
    setAnnotationColor(picker.value);
    picker.remove();
  }, { once: true });
  document.body.appendChild(picker);
  picker.click();
}

function updateSelectedFreeCurveStyle() {
  const curve = selectedFreeCurveId ? freeCurves.find((item) => item.id === selectedFreeCurveId) : null;
  if (!curve) return;
  curve.style = freeCurveStyle?.value || DEFAULT_FREE_CURVE_STYLE;
  curve.weight = freeCurveWeight?.value || DEFAULT_FREE_CURVE_WEIGHT;
  render();
}

function syncFreeCurveControls(curve) {
  if (freeCurveStyle) freeCurveStyle.value = curve.style || DEFAULT_FREE_CURVE_STYLE;
  if (freeCurveWeight) freeCurveWeight.value = curve.weight || DEFAULT_FREE_CURVE_WEIGHT;
}

function visibleCanvasCenter() {
  const svg = canvasWrap.querySelector("svg");
  if (!svg) {
    return {
      x: current.layout ? current.layout.width / 2 : 360,
      y: current.layout ? Math.min(220, current.layout.height / 2) : 220,
    };
  }
  const rect = canvasWrap.getBoundingClientRect();
  return svgClientPoint(svg, rect.left + canvasWrap.clientWidth / 2, rect.top + canvasWrap.clientHeight / 2) || {
    x: Number(svg.getAttribute("width")) / 2,
    y: Number(svg.getAttribute("height")) / 2,
  };
}

function visibleCanvasTopRight() {
  const svg = canvasWrap.querySelector("svg");
  if (!svg) {
    return {
      x: current.layout ? Math.max(260, current.layout.width - 220) : 520,
      y: 120,
    };
  }
  const rect = canvasWrap.getBoundingClientRect();
  return svgClientPoint(svg, rect.left + canvasWrap.clientWidth - 120, rect.top + 84) || {
    x: Number(svg.getAttribute("width")) - 220,
    y: 120,
  };
}

function renderFreeAnnotation(annotation) {
  const group = el("g", {
    class: `free-annotation ${selectedAnnotationId === annotation.id ? "selected" : ""}`,
    transform: `translate(${annotation.x}, ${annotation.y})`,
  });
  const lines = annotation.text.split(/\n/).map((line) => line.trim()).filter(Boolean);
  const text = el("text", {
    class: "free-annotation-text",
    style: `fill: ${isHexColor(annotation.color) ? annotation.color : DEFAULT_MOVEMENT_COLOR};`,
    x: 0,
    y: 0,
    "text-anchor": "middle",
    "dominant-baseline": "middle",
  });
  const lineGap = 23;
  lines.forEach((line, index) => {
    const tspan = el("tspan", {
      x: 0,
      dy: index === 0 ? (1 - lines.length) * lineGap / 2 : lineGap,
    });
    tspan.textContent = displayText(line);
    text.appendChild(tspan);
  });
  group.appendChild(text);
  group.addEventListener("pointerdown", (event) => {
    event.preventDefault();
    event.stopPropagation();
    const point = svgPoint(group.ownerSVGElement, event);
    if (!point) return;
    clearSelection();
    selectedAnnotationId = annotation.id;
    syncAnnotationInput(annotation);
    draggingAnnotation = { id: annotation.id, last: point };
    render();
  });
  group.addEventListener("dblclick", (event) => {
    event.preventDefault();
    event.stopPropagation();
    editFreeAnnotation(annotation.id);
  });
  group.addEventListener("contextmenu", (event) => {
    event.preventDefault();
    event.stopPropagation();
    clearSelection();
    selectedAnnotationId = annotation.id;
    syncAnnotationInput(annotation);
    render();
  });
  return group;
}

function editFreeAnnotation(id) {
  const annotation = freeAnnotations.find((item) => item.id === id);
  if (!annotation) return;
  clearSelection();
  selectedAnnotationId = id;
  syncAnnotationInput(annotation);
  annotationTextInput?.focus();
  annotationTextInput?.select();
  render();
}

function renderAnnotationSelection(annotation) {
  const lines = annotation.text.split(/\n/).map((line) => line.trim()).filter(Boolean);
  const width = Math.max(54, ...lines.map((line) => estimateVisualTextWidth(line) + 20));
  const height = Math.max(24, lines.length * 23 + 12);
  const group = el("g", { class: "annotation-selection" });
  group.appendChild(el("rect", {
    class: "annotation-selection-box",
    x: annotation.x - width / 2,
    y: annotation.y - height / 2,
    width,
    height,
    rx: 4,
  }));
  return group;
}

function annotationBounds(annotation) {
  const lines = annotation.text.split(/\n/).map((line) => line.trim()).filter(Boolean);
  const width = Math.max(54, ...lines.map((line) => estimateVisualTextWidth(line) + 20));
  const height = Math.max(24, lines.length * 23 + 12);
  return {
    minX: annotation.x - width / 2,
    maxX: annotation.x + width / 2,
    minY: annotation.y - height / 2,
    maxY: annotation.y + height / 2,
  };
}

function annotationMenuAnchor(annotation) {
  const bounds = annotationBounds(annotation);
  return {
    x: bounds.maxX + 12,
    y: Math.max(18, bounds.minY - 6),
  };
}

function curveBounds(curve) {
  const points = curveControlPoints(curve);
  return points.reduce((bounds, point) => ({
    minX: Math.min(bounds.minX, point.x),
    maxX: Math.max(bounds.maxX, point.x),
    minY: Math.min(bounds.minY, point.y),
    maxY: Math.max(bounds.maxY, point.y),
  }), {
    minX: points[0]?.x ?? 0,
    maxX: points[0]?.x ?? 0,
    minY: points[0]?.y ?? 0,
    maxY: points[0]?.y ?? 0,
  });
}

function freeCurveMenuAnchor(curve) {
  const bounds = curveBounds(curve);
  return {
    x: bounds.maxX + 12,
    y: Math.max(18, bounds.minY - 6),
  };
}

function renderExtraDeleteMenu(anchor) {
  const label = L.deleteExtra || "Delete";
  const width = Math.max(58, estimateVisualTextWidth(label) + 24);
  const group = el("g", {
    class: "extra-delete-menu",
    transform: `translate(${anchor.x}, ${anchor.y})`,
    tabindex: 0,
    role: "button",
    "aria-label": label,
  });
  group.appendChild(el("rect", {
    class: "extra-delete-menu-bg",
    x: 0,
    y: 0,
    width,
    height: 28,
    rx: 6,
  }));
  const text = el("text", {
    class: "extra-delete-menu-text",
    x: width / 2,
    y: 18,
    "text-anchor": "middle",
  });
  text.textContent = label;
  group.appendChild(text);
  group.addEventListener("pointerdown", (event) => {
    event.preventDefault();
    event.stopPropagation();
  });
  group.addEventListener("click", (event) => {
    event.preventDefault();
    event.stopPropagation();
    deleteSelectedExtra();
  });
  group.addEventListener("keydown", (event) => {
    if (event.key !== "Enter" && event.key !== " ") return;
    event.preventDefault();
    event.stopPropagation();
    deleteSelectedExtra();
  });
  return group;
}

function freeCurvePathD(points) {
  if (!Array.isArray(points) || points.length < 3) return "";
  return `M ${points[0].x} ${points[0].y} Q ${points[1].x} ${points[1].y}, ${points[2].x} ${points[2].y}`;
}

function curveControlPoints(curve) {
  if (!Array.isArray(curve.points)) curve.points = [];
  if (curve.points.length >= 4) {
    curve.points = [curve.points[0], curve.points[1], curve.points[curve.points.length - 1]];
  }
  while (curve.points.length < 3) {
    const fallback = curve.points[curve.points.length - 1] || { x: 260, y: 180 };
    curve.points.push({ x: fallback.x + 80, y: fallback.y });
  }
  return curve.points.slice(0, 3);
}

function renderFreeCurveHandles(curve) {
  const group = el("g", { class: "free-curve-handles" });
  curveControlPoints(curve).forEach((point, index) => {
    const circle = el("circle", {
      class: "free-curve-handle",
      cx: point.x,
      cy: point.y,
      r: 5.5,
      tabindex: 0,
      "aria-label": `curve point ${index + 1}`,
    });
    circle.addEventListener("pointerdown", (event) => {
      event.preventDefault();
      event.stopPropagation();
      clearSelection();
      selectedFreeCurveId = curve.id;
      syncFreeCurveControls(curve);
      draggingFreeCurve = { id: curve.id, handle: index };
    });
    group.appendChild(circle);
  });
  return group;
}

function freeExtrasBounds() {
  let maxX = 0;
  let maxY = 0;
  freeAnnotations.forEach((annotation) => {
    const lines = annotation.text.split(/\n/).map((line) => line.trim()).filter(Boolean);
    const width = Math.max(54, ...lines.map((line) => estimateVisualTextWidth(line) + 20));
    const height = Math.max(24, lines.length * 23 + 12);
    maxX = Math.max(maxX, annotation.x + width / 2);
    maxY = Math.max(maxY, annotation.y + height / 2);
  });
  freeCurves.forEach((curve) => {
    curve.points.forEach((point) => {
      maxX = Math.max(maxX, point.x);
      maxY = Math.max(maxY, point.y);
    });
  });
  return { maxX, maxY };
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
  const preface = "\\documentclass{article}\n\n\\usepackage{forest}\n\\useforestlibrary{linguistics}\n\\usepackage{xcolor}\n\\usepackage{iftex}\n\\ifXeTeX\n\\usepackage[fontset=fandol]{ctex}\n\\fi\n\\usepackage[normalem]{ulem}\n\\usepackage{pdfrender}\n\n\\begin{document}\n\n";
  const drawLinks = latexMovementLinks(links, layout);
  const linkOptions = links.length
    ? `,\ntikz+={%\n  ${drawLinks}\n}`
    : "";
  const header = links.length
    ? `\\begin{forest}\nfor tree={align=center, parent anchor=south, child anchor=north}${linkOptions}\n`
    : "\\begin{forest}\nfor tree={align=center}\n";
  return `${preface}${header}${forest}\n\\end{forest}\n\n\\end{document}`;
}

function latexMovementLinks(links, layout) {
  const ranked = rankLatexMovementLinks(links, layout);
  return ranked.map(({ link, rank }) => latexMovementLink(link, movementLatexStyle(link), rank)).join("\n  ");
}

function movementLatexStyle(link) {
  const dash = movementStyleFor(link.id) === "dashed" ? "dashed, " : "";
  return `${dash}${latexColorOption(movementColor(link.id))}, ->`;
}

function latexColorOption(color) {
  const match = String(color).match(/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i);
  if (!match) return "draw=black";
  const [, red, green, blue] = match;
  return `draw={rgb,255:red,${parseInt(red, 16)};green,${parseInt(green, 16)};blue,${parseInt(blue, 16)}}`;
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

function nodeToForest(node, links, parent = null) {
  const name = links.some((link) => link.from === node.id || link.to === node.id) ? `, name=${latexName(node.id)}` : "";
  const edge = parent && hiddenBranches[branchId(parent, node)] ? ", no edge" : "";
  if (isInvisibleEmptyNode(node)) return "";
  if (isTriangleNode(node)) {
    const label = forestLabel(labelForLatex(visibleLabel(node.label)));
    const roof = forestLabel(labelForLatex(getTriangleText(node)));
    return `[${label}${name}${edge} [${roof}, roof]]`;
  }
  const label = forestLabel(labelForLatex(node.label));
  const childForest = visibleChildren(node).map((child) => nodeToForest(child, links, node)).filter(Boolean);
  if (!childForest.length) return `[${label}${name}${edge}]`;
  return `[${label}${name}${edge} ${childForest.join(" ")}]`;
}

function toVisualTikzLatex(tree, links, layout) {
  const lines = [
    "\\documentclass{article}",
    "\\usepackage{tikz}",
    "\\usepackage{xcolor}",
    "\\usepackage{iftex}",
    "\\ifXeTeX",
    "\\usepackage[fontset=fandol]{ctex}",
    "\\fi",
    "\\usepackage[normalem]{ulem}",
    "\\usepackage{pdfrender}",
    "\\pagestyle{empty}",
    "\\begin{document}",
    "\\begin{tikzpicture}[x=0.02cm,y=-0.02cm,line cap=round,line join=round]",
  ];
  const labelLines = [];

  layout.nodes.forEach((node) => {
    if (isTriangleNode(node)) return;
    visibleChildren(node).forEach((child) => {
      const id = branchId(node, child);
      if (hiddenBranches[id]) return;
      const points = branchLinePoints(id, node, child);
      const dashed = branchStyle.value === "dashed" ? ", dashed" : "";
      lines.push(`  \\draw[line width=0.46pt${dashed}] ${tikzPoint(points.start)} -- ${tikzPoint(points.end)};`);
    });
  });

  layout.nodes.forEach((node) => {
    const x = labelX(node);
    const y = labelY(node);
    if (isTriangleNode(node)) {
      const label = labelForLatex(visibleLabel(node.label));
      if (label) labelLines.push(tikzTextNode(x, y, label, 18));
      const shape = triangleShapeFor(node);
      const left = { x: x + shape.left.x, y: y + shape.left.y };
      const top = { x: x + shape.top.x, y: y + shape.top.y };
      const right = { x: x + shape.right.x, y: y + shape.right.y };
      labelLines.push(`  \\draw[line width=0.46pt] ${tikzPoint(left)} -- ${tikzPoint(top)} -- ${tikzPoint(right)} -- cycle;`);
      const textPoint = triangleTextPoint(shape);
      const roofText = labelForLatex(getTriangleText(node));
      if (roofText) labelLines.push(tikzTextNode(x + textPoint.x, y + textPoint.y, roofText, 14));
      return;
    }
    if (isEmptyTerminalLabel(node.label)) return;
    const label = labelForLatex(node.label);
    const fontSize = usesLeafTextStyle(node.label, visibleChildren(node).length === 0) ? 14 : 18;
    if (label) labelLines.push(tikzTextNode(x, y, label, fontSize));
  });

  const byId = new Map(layout.nodes.map((node) => [node.id, node]));
  const ranks = movementRankMap(links, byId);
  links.forEach((link, order) => {
    const from = byId.get(link.from);
    const to = byId.get(link.to);
    if (!from || !to) return;
    const points = movementCurvePoints(link, ranks.get(link.id) ?? order, from, to);
    const arrow = points.start.x <= points.end.x ? "<-" : "->";
    const dashed = movementStyleFor(link.id) === "dashed" ? ", dashed" : "";
    lines.push(`  \\draw[${arrow}, line width=0.46pt${dashed}, ${latexColorOption(movementColor(link.id))}] ${tikzPoint(points.start)} .. controls ${tikzPoint(points.control)} .. ${tikzPoint(points.end)};`);
  });

  freeCurves.forEach((curve) => {
    const points = curveControlPoints(curve);
    const dashed = curve.style === "dashed" ? ", dashed" : "";
    const width = curve.weight === "bold" ? "1.25pt" : "0.54pt";
    lines.push(`  \\draw[line width=${width}${dashed}] ${tikzPoint(points[0])} .. controls ${tikzPoint(points[1])} .. ${tikzPoint(points[2])};`);
  });

  lines.push(...labelLines);

  freeAnnotations.forEach((annotation) => {
    const text = annotation.text.split(/\n/).map((line) => latexText(line.trim())).filter(Boolean).join("\\\\{}");
    if (!text) return;
    const color = isHexColor(annotation.color) ? annotation.color : DEFAULT_MOVEMENT_COLOR;
    const colorSpec = latexColorOption(color).replace(/^draw=/, "");
    lines.push(`  \\node[align=center, inner sep=0pt, font=\\fontsize{14}{17}\\selectfont, text=${colorSpec}] at (${tikzNumber(annotation.x)},${tikzNumber(annotation.y)}) {${text}};`);
  });

  lines.push("\\end{tikzpicture}", "\\end{document}");
  return lines.join("\n");
}

function tikzTextNode(x, y, label, fontSize) {
  const baseline = Math.round(fontSize * 1.25);
  return `  \\node[align=center, inner sep=0pt, font=\\fontsize{${fontSize}}{${baseline}}\\selectfont] at (${tikzNumber(x)},${tikzNumber(y)}) {${label}};`;
}

function tikzPoint(point) {
  return `(${tikzNumber(point.x)},${tikzNumber(point.y)})`;
}

function tikzNumber(value) {
  return Number(value.toFixed(2));
}

function forestLabel(label) {
  return `{${label}}`;
}

function labelForLatex(label) {
  if (isEmptyTerminalLabel(label)) return "";
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

function usesLeafTextStyle(label, isLeaf) {
  return Boolean(isLeaf && !isCategoryLabel(label));
}

function isCategoryLabel(label) {
  const lines = splitLabelLines(label);
  if (lines.length !== 1) return false;
  const parts = parseLabelParts(lines[0]);
  const stem = displayText(parts.stem).replace(/′/g, "'");
  const base = stem.replace(/'$/, "");
  if (CATEGORY_LABEL_STEMS.has(stem) || CATEGORY_LABEL_STEMS.has(base)) return true;
  return /^[A-Z][A-Za-z]*P$/.test(base) || /^[a-z]P$/.test(base);
}

function isTriangleNode(node) {
  return node.label.startsWith("^") || node.label.startsWith("△");
}

function visibleChildren(node) {
  return node.children.filter((child) => !isInvisibleEmptyNode(child));
}

function isInvisibleEmptyNode(node) {
  return node && isSilentTerminalLabel(node.label) && !node.children.length;
}

function isEmptyTerminalLabel(label) {
  return stripMovementIndexMarker(visibleLabel(String(label))).trim().toLowerCase() === "@empty";
}

function isSilentTerminalLabel(label) {
  return stripMovementIndexMarker(visibleLabel(String(label))).trim().toLowerCase() === "@silent";
}

function visibleLabel(label) {
  return label.replace(/^[△^]/, "");
}

function getTriangleText(node) {
  return visibleChildren(node).map((child) => visibleLabel(child.label)).join(" ");
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
  if (isEmptyTerminalLabel(label) || isSilentTerminalLabel(label)) return [];
  return stripStrikeMarkers(label).split("|").map((line) => line.trim()).filter((line) => line && line.toLowerCase() !== "@empty" && line.toLowerCase() !== "@silent");
}

function splitDisplayLabelLines(label) {
  if (isEmptyTerminalLabel(label) || isSilentTerminalLabel(label)) return [];
  return visibleLabel(label).split("|").map((line) => line.trim()).filter((line) => line && line.toLowerCase() !== "@empty" && line.toLowerCase() !== "@silent");
}

function labelBottomOffset(node) {
  const leafStyle = usesLeafTextStyle(node.label, visibleChildren(node).length === 0);
  return labelBlockHeight(node) / 2 + (leafStyle ? 17 : 18);
}

function labelTopOffset(node) {
  const leafStyle = usesLeafTextStyle(node.label, visibleChildren(node).length === 0);
  return labelBlockHeight(node) / 2 + (leafStyle ? 20 : 18);
}

function labelBlockHeight(node) {
  const isLeaf = visibleChildren(node).length === 0;
  const leafStyle = usesLeafTextStyle(node.label, isLeaf);
  const lines = splitLabelLines(node.label);
  const fontSize = leafStyle ? 20 : 25;
  const lineGap = leafStyle ? 24 : 30;
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
      leftX: topLeft.x,
      rightX: bottomRight.x,
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
        y: start.y - labelY(node),
      };
    });
  });
  return lines;
}

function strikeLineKey(nodeId, lineIndex) {
  return `${nodeId}:${lineIndex}`;
}

function labelAnchorX(node) {
  if (isEmptyTerminalLabel(node.label)) return labelX(node);
  return measuredLabelAnchors[node.id]?.centerX ?? labelX(node);
}

function labelTopY(node) {
  if (isEmptyTerminalLabel(node.label)) return labelY(node);
  return measuredLabelAnchors[node.id]?.topY ?? labelY(node) - labelTopOffset(node);
}

function labelBottomY(node) {
  if (isEmptyTerminalLabel(node.label)) return labelY(node);
  return measuredLabelAnchors[node.id]?.bottomY ?? labelY(node) + labelBottomOffset(node);
}

function movementStartAnchor(node) {
  if (isTriangleNode(node)) {
    const shape = triangleShapeFor(node);
    const textPoint = triangleTextPoint(shape);
    return {
      x: labelX(node) + (shape.left.x + shape.right.x) / 2,
      y: labelY(node) + textPoint.y + 8,
    };
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
  const forceVertical = isUnaryBranch(parent, child);
  const x = forceVertical ? verticalBranchX(parent, child) : null;
  const start = {
    x: forceVertical ? x : labelAnchorX(parent),
    y: labelBottomY(parent) + BRANCH_LABEL_GAP,
  };
  const end = mirroredBranchEndPoint(parent, child, start) || {
    x: forceVertical ? x : labelAnchorX(child),
    y: labelTopY(child) - BRANCH_LABEL_GAP,
  };
  return {
    start,
    end,
  };
}

function mirroredBranchEndPoint(parent, child, start) {
  if (!isEmptyTerminalLabel(child.label)) return null;
  const childList = visibleChildren(parent);
  if (childList.length !== 2) return null;
  const siblings = visibleChildren(parent).filter((node) => node.id !== child.id && !isEmptyTerminalLabel(node.label));
  if (siblings.length !== 1) return null;
  const sibling = siblings[0];
  const siblingEnd = {
    x: labelAnchorX(sibling),
    y: labelTopY(sibling) - BRANCH_LABEL_GAP,
  };
  const horizontal = Math.abs(siblingEnd.x - start.x);
  const side = labelAnchorX(child) < start.x ? -1 : 1;
  if (!horizontal) return null;
  return {
    x: start.x + side * horizontal,
    y: siblingEnd.y,
  };
}

function isUnaryBranch(parent, child) {
  const childList = visibleChildren(parent);
  return childList.length === 1 && childList[0]?.id === child.id;
}

function verticalBranchX(parent, child) {
  const parentX = labelAnchorX(parent);
  const childX = labelAnchorX(child);
  if (Math.abs(parentX - childX) < 0.01) return parentX;
  return (parentX + childX) / 2;
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

function pruneMovementStyles(links) {
  const valid = new Set(links.map((link) => link.id));
  movementStyles = Object.fromEntries(Object.entries(movementStyles).filter(([id]) => valid.has(id)));
}

function pruneBranchPoints(nodes) {
  const valid = new Set();
  nodes.forEach((node) => {
    if (isTriangleNode(node)) return;
    visibleChildren(node).forEach((child) => valid.add(branchId(node, child)));
  });
  branchPoints = Object.fromEntries(Object.entries(branchPoints).filter(([id]) => valid.has(id)));
  if (selectedBranchId && !valid.has(selectedBranchId)) selectedBranchId = null;
}

function pruneHiddenBranches(nodes) {
  const valid = new Set();
  nodes.forEach((node) => {
    if (isTriangleNode(node)) return;
    visibleChildren(node).forEach((child) => valid.add(branchId(node, child)));
  });
  hiddenBranches = Object.fromEntries(Object.entries(hiddenBranches).filter(([id]) => valid.has(id)));
}

function renderHiddenBranchControls(nodes) {
  if (!hiddenBranchesPanel || !hiddenBranchesList) return;
  const byId = new Map(nodes.map((node) => [node.id, node]));
  const entries = Object.keys(hiddenBranches).filter((id) => hiddenBranches[id]);
  hiddenBranchesPanel.hidden = entries.length === 0;
  hiddenBranchesList.replaceChildren();
  entries.forEach((id) => {
    const [parentId, childId] = id.split("->");
    const parent = byId.get(parentId);
    const child = byId.get(childId);
    if (!parent || !child) return;
    const item = document.createElement("div");
    item.className = "hidden-branch-item";
    const name = document.createElement("span");
    name.textContent = `${branchControlName(parent)} → ${branchControlName(child)}`;
    const restore = document.createElement("button");
    restore.type = "button";
    restore.textContent = L.restoreBranch || "Restore";
    restore.addEventListener("click", () => {
      delete hiddenBranches[id];
      render();
    });
    item.append(name, restore);
    hiddenBranchesList.appendChild(item);
  });
}

function branchControlName(node) {
  if (isEmptyTerminalLabel(node.label)) return `∅${getIndex(node.label) ? `_${getIndex(node.label)}` : ""}`;
  return splitLabelLines(node.label).map((line) => displayText(stripMovementIndexMarker(line))).filter(Boolean).join(" / ") || "∅";
}

function pruneLabelOffsets(nodes) {
  const valid = new Set(nodes.map((node) => node.id));
  labelOffsets = Object.fromEntries(Object.entries(labelOffsets).filter(([id]) => valid.has(id)));
  if (selectedLabelId && !valid.has(selectedLabelId)) selectedLabelId = null;
}

function pruneTrianglePoints(nodes) {
  const valid = new Set(nodes.filter(isTriangleNode).map((node) => node.id));
  trianglePoints = Object.fromEntries(Object.entries(trianglePoints).filter(([id]) => valid.has(id)));
  if (selectedTriangleId && !valid.has(selectedTriangleId)) selectedTriangleId = null;
}

function clearSelection() {
  selectedMovementId = null;
  selectedBranchId = null;
  selectedLabelId = null;
  selectedTriangleId = null;
  selectedAnnotationId = null;
  selectedFreeCurveId = null;
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
  clone.querySelectorAll(".movement-handles, .branch-handles, .triangle-handles, .free-curve-handles, .label-selection, .annotation-selection, .extra-delete-menu, .movement-hit, .branch-hit, .triangle-hit, .free-curve-hit, .empty-terminal-hit").forEach((node) => node.remove());
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
    .free-curve {
      fill: none;
      stroke: #0f172a;
      stroke-width: 1.8px;
      stroke-linecap: round;
      stroke-linejoin: round;
    }
    .free-curve.bold { stroke-width: 4.2px; }
    .free-curve.dashed { stroke-dasharray: 9 7; }
    .free-annotation-text {
      fill: #050505;
      font-family: "Times New Roman", Times, serif;
      font-size: 20px;
      font-weight: 400;
      paint-order: stroke;
      stroke: rgba(255, 255, 255, 0.95);
      stroke-linejoin: round;
      stroke-width: 4px;
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
  recordGenerationDownload();
}

async function recordGenerationDownload() {
  if (!window.SYNTREE?.countUrl) return;
  try {
    const response = await fetch(window.SYNTREE.countUrl, {
      method: "POST",
      headers: {
        "X-CSRF-Token": window.SYNTREE.csrf,
      },
      keepalive: true,
    });
    if (!response.ok) return;
    const data = await response.json();
    const count = Math.max(0, Number(data.count));
    if (!Number.isFinite(count) || count < displayedGenerationCount) return;
    displayedGenerationCount = count;
    if (generationCounter) {
      generationCounter.dataset.count = String(count);
      const template = L.generatedTrees || "{count} trees generated";
      generationCounter.textContent = template.replace("{count}", count.toLocaleString());
    }
  } catch {
    // The export still succeeds if the optional counter request is unavailable.
  }
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
