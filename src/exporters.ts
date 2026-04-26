export function downloadText(filename: string, text: string, mime = 'text/plain;charset=utf-8') {
  const blob = new Blob([text], { type: mime });
  downloadBlob(filename, blob);
}

export function downloadSvg(filename: string, svgElement: SVGSVGElement) {
  const text = serializeSvg(svgElement);
  downloadText(filename, text, 'image/svg+xml;charset=utf-8');
}

export async function downloadTransparentPng(filename: string, svgElement: SVGSVGElement) {
  const text = serializeSvg(svgElement);
  const blob = new Blob([text], { type: 'image/svg+xml;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const image = new Image();
  image.decoding = 'async';

  await new Promise<void>((resolve, reject) => {
    image.onload = () => resolve();
    image.onerror = () => reject(new Error('Vector export could not be converted to PNG.'));
    image.src = url;
  });

  const bounds = getExportBounds(svgElement);
  const width = bounds.width;
  const height = bounds.height;
  const canvas = document.createElement('canvas');
  canvas.width = width * 2;
  canvas.height = height * 2;
  const context = canvas.getContext('2d');
  if (!context) throw new Error('Could not create a canvas.');
  context.scale(2, 2);
  context.clearRect(0, 0, width, height);
  context.drawImage(image, 0, 0, width, height);
  URL.revokeObjectURL(url);

  await new Promise<void>((resolve, reject) => {
    canvas.toBlob((pngBlob) => {
      if (!pngBlob) {
        reject(new Error('PNG export failed.'));
        return;
      }
      downloadBlob(filename, pngBlob);
      resolve();
    }, 'image/png');
  });
}

function serializeSvg(svgElement: SVGSVGElement): string {
  const clone = svgElement.cloneNode(true) as SVGSVGElement;
  const bounds = getExportBounds(svgElement);
  clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
  clone.setAttribute('width', String(bounds.width));
  clone.setAttribute('height', String(bounds.height));
  clone.setAttribute('viewBox', `${bounds.x} ${bounds.y} ${bounds.width} ${bounds.height}`);
  clone.setAttribute('version', '1.1');
  clone.setAttribute('style', 'background: transparent; overflow: visible;');
  return `<?xml version="1.0" encoding="UTF-8"?>\n${new XMLSerializer().serializeToString(clone)}`;
}

function getExportBounds(svgElement: SVGSVGElement): {
  x: number;
  y: number;
  width: number;
  height: number;
} {
  const padding = 18;
  try {
    const bbox = svgElement.getBBox();
    return {
      x: Math.floor(bbox.x - padding),
      y: Math.floor(bbox.y - padding),
      width: Math.ceil(bbox.width + padding * 2),
      height: Math.ceil(bbox.height + padding * 2),
    };
  } catch {
    const viewBox = svgElement.viewBox.baseVal;
    return {
      x: 0,
      y: 0,
      width: Math.ceil(viewBox.width || svgElement.clientWidth || 1200),
      height: Math.ceil(viewBox.height || svgElement.clientHeight || 800),
    };
  }
}

function downloadBlob(filename: string, blob: Blob) {
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = filename;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  URL.revokeObjectURL(url);
}
