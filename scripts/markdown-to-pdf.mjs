import fs from 'fs/promises';
import path from 'path';
import puppeteer from 'puppeteer';

const [inputPath, outputPath] = process.argv.slice(2);

if (!inputPath || !outputPath) {
  console.error('Usage: node scripts/markdown-to-pdf.mjs <input.md> <output.pdf>');
  process.exit(1);
}

const escapeHtml = (text) => text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

const convertMarkdownToHtml = (markdown) => {
  const lines = markdown.split(/\r?\n/);
  const htmlLines = [];
  let inCodeBlock = false;
  let codeBuffer = [];
  let listType = null;

  const flushParagraph = (paragraph) => {
    if (!paragraph.trim()) return '';
    return `<p>${paragraph.trim()}</p>`;
  };

  const flushList = (type) => {
    if (!type) return '';
    const tag = type === 'ol' ? 'ol' : 'ul';
    const items = type === 'ol' ? htmlLines.splice(0, htmlLines.length) : htmlLines.splice(0, htmlLines.length);
    return `<${tag}>${items.join('')}</${tag}>`;
  };

  const formattedLines = [];
  let paragraphBuffer = [];
  const flushParagraphBuffer = () => {
    if (paragraphBuffer.length === 0) return;
    formattedLines.push(`<p>${paragraphBuffer.join(' ')}</p>`);
    paragraphBuffer = [];
  };

  for (const rawLine of lines) {
    let line = rawLine;

    if (inCodeBlock) {
      if (line.trim().startsWith('```')) {
        inCodeBlock = false;
        formattedLines.push(`<pre><code>${escapeHtml(codeBuffer.join('\n'))}</code></pre>`);
        codeBuffer = [];
        continue;
      }
      codeBuffer.push(line);
      continue;
    }

    if (line.trim().startsWith('```')) {
      flushParagraphBuffer();
      inCodeBlock = true;
      continue;
    }

    const headingMatch = line.match(/^(#{1,6})\s+(.*)$/);
    if (headingMatch) {
      flushParagraphBuffer();
      const level = Math.min(6, headingMatch[1].length);
      const text = headingMatch[2].trim();
      formattedLines.push(`<h${level}>${text}</h${level}>`);
      continue;
    }

    const hrMatch = line.match(/^([-*_])(?:\s*\1){2,}\s*$/);
    if (hrMatch) {
      flushParagraphBuffer();
      formattedLines.push('<hr/>');
      continue;
    }

    const listMatch = line.match(/^\s*([-*+])\s+(.*)$/);
    const orderedMatch = line.match(/^\s*(\d+)\.\s+(.*)$/);
    if (listMatch || orderedMatch) {
      flushParagraphBuffer();
      const type = orderedMatch ? 'ol' : 'ul';
      const content = escapeHtml((orderedMatch?.[2] ?? listMatch?.[2] ?? '').trim())
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.*?)\*/g, '<em>$1</em>')
        .replace(/`([^`]+)`/g, '<code>$1</code>');
      formattedLines.push(`<li>${content}</li>`);
      if (listType && listType !== type) {
        formattedLines.push(`</${listType}>`);
        listType = type;
      }
      if (!listType) {
        listType = type;
        formattedLines.unshift(`<${listType}>`);
      }
      continue;
    }

    if (listType) {
      formattedLines.push(`</${listType}>`);
      listType = null;
    }

    if (line.trim() === '') {
      flushParagraphBuffer();
      continue;
    }

    const inline = escapeHtml(line)
      .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
      .replace(/\*(.*?)\*/g, '<em>$1</em>')
      .replace(/`([^`]+)`/g, '<code>$1</code>');
    paragraphBuffer.push(inline);
  }

  flushParagraphBuffer();
  if (listType) {
    formattedLines.push(`</${listType}>`);
  }

  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>User Manual</title>
  <style>
    body { font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin: 40px; color: #111827; line-height: 1.6; }
    h1, h2, h3, h4, h5, h6 { color: #111827; margin-top: 1.8em; margin-bottom: 0.5em; }
    h1 { font-size: 2.1rem; }
    h2 { font-size: 1.75rem; }
    h3 { font-size: 1.45rem; }
    p { margin: 0.85em 0; }
    ul, ol { margin: 0.75em 0 0.75em 1.5em; }
    li { margin: 0.35em 0; }
    pre { background: #f3f4f6; padding: 1em; overflow-x: auto; border-radius: 0.5em; }
    code { background: #f3f4f6; padding: 0.15em 0.3em; border-radius: 0.35em; }
    hr { border: none; border-top: 1px solid #d1d5db; margin: 2em 0; }
  </style>
</head>
<body>
${formattedLines.join('\n')}
</body>
</html>`;
};

const run = async () => {
  const absoluteInput = path.resolve(inputPath);
  const absoluteOutput = path.resolve(outputPath);
  const markdown = await fs.readFile(absoluteInput, 'utf8');
  const html = convertMarkdownToHtml(markdown);

  const browser = await puppeteer.launch({ args: ['--no-sandbox', '--disable-setuid-sandbox'] });
  const page = await browser.newPage();
  await page.goto(`data:text/html,${encodeURIComponent(html)}`, { waitUntil: 'networkidle0' });
  await page.addStyleTag({ content: 'body { background-color: white; color: black; }' });
  const bodyText = await page.evaluate(() => document.body.innerText.trim());
  if (!bodyText) {
    throw new Error('Rendered page body text is empty');
  }
  console.log(`Rendered page body length: ${bodyText.length}`);
  await page.pdf({ path: absoluteOutput, format: 'A4', printBackground: true, margin: { top: '20mm', bottom: '20mm', left: '20mm', right: '20mm' } });
  await browser.close();
  console.log(`PDF generated: ${absoluteOutput}`);
};

run().catch((error) => {
  console.error(error);
  process.exit(1);
});
