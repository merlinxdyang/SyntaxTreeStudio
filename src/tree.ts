export type SyntaxNode = {
  id: string;
  label: string;
  children: SyntaxNode[];
};

export type ParseResult = {
  tree: SyntaxNode | null;
  error: string | null;
};

type Token = '[' | ']' | string;

let nextId = 1;

export function parseBracketTree(input: string): ParseResult {
  nextId = 1;
  const tokens = tokenize(input);
  if (tokens.length === 0) {
    return { tree: null, error: 'Enter a bracket expression.' };
  }

  let index = 0;

  function readNode(): SyntaxNode {
    if (tokens[index] !== '[') {
      throw new Error(`Expected "[" near token ${index + 1}.`);
    }
    index += 1;

    const label = tokens[index];
    if (!label || label === '[' || label === ']') {
      throw new Error('Every "[" must be followed by a node label.');
    }
    index += 1;

    const children: SyntaxNode[] = [];
    while (index < tokens.length && tokens[index] !== ']') {
      if (tokens[index] === '[') {
        children.push(readNode());
      } else {
        children.push({
          id: makeId(),
          label: String(tokens[index]),
          children: [],
        });
        index += 1;
      }
    }

    if (tokens[index] !== ']') {
      throw new Error(`Node "${label}" is missing a closing bracket.`);
    }
    index += 1;

    return {
      id: makeId(),
      label: String(label),
      children,
    };
  }

  try {
    const tree = readNode();
    if (index !== tokens.length) {
      throw new Error(`Unexpected content after "${tokens[index]}".`);
    }
    return { tree, error: null };
  } catch (error) {
    return { tree: null, error: error instanceof Error ? error.message : 'Parsing failed.' };
  }
}

export function detectMovementLinks(tree: SyntaxNode | null): MovementLink[] {
  if (!tree) return [];
  const indexed = new Map<string, MovementCandidate[]>();
  collectMovementCandidates(tree, null, (candidate) => {
    const index = getIndex(candidate.label);
    if (!index) return;
    indexed.set(index, [...(indexed.get(index) ?? []), candidate]);
  });

  const links: MovementLink[] = [];
  indexed.forEach((nodes, index) => {
    const target = nodes.find((node) => !isTrace(node.label)) ?? nodes[0];
    nodes
      .filter((node) => node.id !== target.id && isTrace(node.label))
      .forEach((trace) => {
        links.push({ id: `movement-${index}-${trace.id}`, from: trace.id, to: target.id, index });
      });
  });
  return links;
}

type MovementCandidate = {
  id: string;
  label: string;
};

export type MovementLink = {
  id: string;
  from: string;
  to: string;
  index: string;
};

export function toForestLatex(tree: SyntaxNode | null, links: MovementLink[]): string {
  if (!tree) return '';
  const forest = nodeToForest(tree, links);
  const hasLinks = links.length > 0;
  const header = hasLinks
    ? '\\begin{forest}\nfor tree={align=center, parent anchor=south, child anchor=north}\n'
    : '\\begin{forest}\nfor tree={align=center}\n';
  const drawLinks = links
    .map((link) => {
      return `\\draw[dashed, ->] (${latexName(link.from)}) to[out=south, in=south] (${latexName(link.to)});`;
    })
    .join('\n');

  return `${header}${forest}${drawLinks ? `\n${drawLinks}` : ''}\n\\end{forest}`;
}

function nodeToForest(node: SyntaxNode, links: MovementLink[]): string {
  if (isTriangleNode(node)) {
    const name = links.some((link) => link.from === node.id || link.to === node.id)
      ? `, name=${latexName(node.id)}`
      : '';
    const label = escapeLatex(labelForLatex(getVisibleLabel(node.label)));
    const roof = escapeLatex(node.children.map((child) => labelForLatex(child.label)).join(' '));
    return `[${label}${name} [${roof}, roof]]`;
  }

  const name = links.some((link) => link.from === node.id || link.to === node.id)
    ? `, name=${latexName(node.id)}`
    : '';
  const label = escapeLatex(labelForLatex(node.label));
  if (node.children.length === 0) {
    return `[${label}${name}]`;
  }
  return `[${label}${name} ${node.children.map((child) => nodeToForest(child, links)).join(' ')}]`;
}

export function splitIndexedLabel(label: string): { base: string; index: string | null } {
  const visible = getVisibleLabel(label);
  const match = visible.match(/^(.*)_([A-Za-z0-9]+)$/);
  if (!match) return { base: visible, index: null };
  return { base: match[1], index: match[2] };
}

export type LabelParts = {
  stem: string;
  head: string | null;
  subscript: string | null;
};

export function parseLabelParts(label: string): LabelParts {
  const indexed = splitIndexedLabel(label);
  if (indexed.index) {
    const headMatch = indexed.base.match(/^(.*)0$/);
    return {
      stem: headMatch?.[1] ?? indexed.base,
      head: headMatch ? '0' : null,
      subscript: indexed.index,
    };
  }

  const compact = indexed.base.match(/^(.*?)(0)?([1-9][0-9]*)$/);
  if (compact) {
    return {
      stem: compact[1],
      head: compact[2] ?? null,
      subscript: compact[3],
    };
  }

  const headOnly = indexed.base.match(/^(.*)0$/);
  if (headOnly) {
    return { stem: headOnly[1], head: '0', subscript: null };
  }

  return { stem: indexed.base, head: null, subscript: null };
}

export function isTriangleNode(node: SyntaxNode): boolean {
  return node.label.startsWith('△') || node.label.startsWith('^');
}

export function getVisibleLabel(label: string): string {
  return label.replace(/^[△^]/, '');
}

export function getTriangleText(node: SyntaxNode): string {
  return node.children.map((child) => getVisibleLabel(child.label)).join(' ');
}

function labelForLatex(label: string): string {
  const parts = parseLabelParts(label);
  return `${parts.stem}${parts.head ? '$^{0}$' : ''}${parts.subscript ? `$_{${parts.subscript}}$` : ''}`;
}

function tokenize(input: string): Token[] {
  const normalized = input.replace(/\(/g, '[').replace(/\)/g, ']');
  const matches = normalized.match(/\[|\]|"[^"]*"|[^\s\[\]]+/g);
  return matches?.map((token) => token.replace(/^"|"$/g, '')) ?? [];
}

function makeId(): string {
  const id = `n${nextId}`;
  nextId += 1;
  return id;
}

function walk(node: SyntaxNode, visit: (node: SyntaxNode) => void) {
  visit(node);
  node.children.forEach((child) => walk(child, visit));
}

function collectMovementCandidates(
  node: SyntaxNode,
  triangleOwner: SyntaxNode | null,
  visit: (candidate: MovementCandidate) => void,
) {
  const owner = triangleOwner ?? (isTriangleNode(node) ? node : null);
  visit({
    id: owner && node !== owner ? owner.id : node.id,
    label: node.label,
  });
  node.children.forEach((child) => collectMovementCandidates(child, owner, visit));
}

function getIndex(label: string): string | null {
  const match = label.match(/_([A-Za-z0-9]+)$/);
  return match?.[1] ?? null;
}

function isTrace(label: string): boolean {
  return /^t(_[A-Za-z0-9]+)?$/.test(label) || /^trace(_[A-Za-z0-9]+)?$/i.test(label);
}

function latexName(id: string): string {
  return id.replace(/[^A-Za-z0-9-]/g, '-');
}

function escapeLatex(value: string): string {
  return value
    .replace(/\\/g, '\\textbackslash{}')
    .replace(/&/g, '\\&')
    .replace(/%/g, '\\%')
    .replace(/\$/g, '\\$')
    .replace(/#/g, '\\#')
    .replace(/_/g, '\\_')
    .replace(/{/g, '\\{')
    .replace(/}/g, '\\}')
    .replace(/~/g, '\\textasciitilde{}')
    .replace(/\^/g, '\\textasciicircum{}')
    .replace(/\\\$_\\\{([^}]+)\\\}\\\$/g, '$_{$1}$');
}
