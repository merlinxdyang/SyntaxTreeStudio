import { hierarchy, tree as d3Tree } from 'd3-hierarchy';
import { getTriangleText, getVisibleLabel, isTriangleNode } from './tree';
import type { SyntaxNode } from './tree';

export type PositionedNode = Omit<SyntaxNode, 'children'> & {
  x: number;
  y: number;
  width: number;
  labelWidth: number;
  triangleText?: string;
  children: PositionedNode[];
};

export type TreeLayout = {
  root: PositionedNode;
  nodes: PositionedNode[];
  minX: number;
  maxX: number;
  minY: number;
  maxY: number;
  width: number;
  height: number;
};

const LEVEL_GAP = 82;
const BASE_NODE_GAP = 48;
const PADDING_X = 54;
const PADDING_Y = 42;

export function layoutTree(tree: SyntaxNode): TreeLayout {
  const rootHierarchy = hierarchy(tree, (node) => (isTriangleNode(node) ? null : node.children));
  const layout = d3Tree<SyntaxNode>()
    .nodeSize([BASE_NODE_GAP, LEVEL_GAP])
    .separation((a, b) => {
      const siblingFactor = a.parent === b.parent ? 1 : 1.18;
      return ((estimateLabelWidth(a.data.label) + estimateLabelWidth(b.data.label)) / 2 + 30) /
        BASE_NODE_GAP * siblingFactor;
    });

  const laidOut = layout(rootHierarchy);
  const positionedById = new Map<string, PositionedNode>();

  laidOut.each((node) => {
    const labelWidth = estimateLabelWidth(node.data.label);
    const width = isTriangleNode(node.data)
      ? Math.max(labelWidth, estimatePlainTextWidth(getTriangleText(node.data)) + 28)
      : labelWidth;

    positionedById.set(node.data.id, {
      id: node.data.id,
      label: node.data.label,
      x: node.x,
      y: node.y + PADDING_Y,
      width,
      labelWidth,
      triangleText: isTriangleNode(node.data) ? getTriangleText(node.data) : undefined,
      children: [],
    });
  });

  laidOut.each((node) => {
    const positioned = positionedById.get(node.data.id);
    if (!positioned) return;
    positioned.children = (node.children ?? [])
      .map((child) => positionedById.get(child.data.id))
      .filter((child): child is PositionedNode => Boolean(child));
  });

  const root = positionedById.get(tree.id);
  if (!root) {
    throw new Error('Tree layout failed.');
  }

  const nodes = Array.from(positionedById.values());
  let minX = Infinity;
  let maxX = -Infinity;
  let minY = Infinity;
  let maxY = -Infinity;

  nodes.forEach((node) => {
    const half = node.width / 2;
    const bottom = isTriangleNode(node) ? node.y + 94 : node.y + 18;
    minX = Math.min(minX, node.x - half);
    maxX = Math.max(maxX, node.x + half);
    minY = Math.min(minY, node.y - 18);
    maxY = Math.max(maxY, bottom);
  });

  const shiftX = PADDING_X - minX;
  nodes.forEach((node) => {
    node.x += shiftX;
  });

  return {
    root,
    nodes,
    minX: PADDING_X,
    maxX: maxX + shiftX,
    minY,
    maxY,
    width: Math.max(360, maxX - minX + PADDING_X * 2),
    height: maxY + PADDING_Y * 2,
  };
}

function estimateLabelWidth(label: string): number {
  const visible = getVisibleLabel(label);
  const base = visible.replace(/_([A-Za-z0-9]+)$/g, '');
  const indexWidth = visible.length - base.length > 0 ? 10 : 0;
  return Math.max(30, base.length * 10 + indexWidth + 12);
}

function estimatePlainTextWidth(label: string): number {
  return Math.max(82, label.length * 9 + 28);
}
