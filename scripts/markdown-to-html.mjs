import fs from 'fs/promises';
import path from 'path';
import puppeteer from 'puppeteer';

const [inputPath, outputPath] = process.argv.slice(2);
if (!inputPath || !outputPath) {
  console.error('Usage: node scripts/markdown-to-html.mjs <input.md> <output.html>');
  process.exit(1);
}

const escapeHtml = (text) => text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const convertMarkdownToHtml = (markdown) => {
  const lines = markdown.split(/\r?\n/);
  const formattedLines = [];
  let inCodeBlock = false;
  let codeBuffer = [];
  let listType = null;
  let paragraphBuffer = [];

  const flushParagraphBuffer = () => {
    if (paragraphBuffer.length === 0) return;
    formattedLines.push(`<p>${paragraphBuffer.join(' ')}</p>`);
    paragraphBuffer = [];
  };

  for (const rawLine of lines) {
    const line = rawLine;
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
      formattedLines.push(`<h${level}>${escapeHtml(headingMatch[2].trim())}</h${level}>`);
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
      const content = escapeHtml((orderedMatch?.[2] ?? listMatch?.[2] ?? '').trim());
      if (!listType) {
        listType = type;
        formattedLines.push(`<${type}>`);
      }
      if (listType !== type) {
        formattedLines.push(`</${listType}>`);
        listType = type;
        formattedLines.push(`<${type}>`);
      }
      formattedLines.push(`<li>${content}</li>`);
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

    paragraphBuffer.push(escapeHtml(line));
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
  await fs.writeFile(absoluteOutput, html, 'utf8');
  console.log(`HTML generated: ${absoluteOutput}`);
};

run().catch((error) => {
  console.error(error);
  process.exit(1);
});
