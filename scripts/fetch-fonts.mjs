/**
 * Download and self-host the webfonts.
 *
 *     npm run fonts
 *
 * Google Fonts is deliberately not used at runtime. It adds a third-party DNS
 * lookup plus a TLS handshake before the first byte of CSS arrives, and the
 * Ethiopic face is large - both of which hurt badly on mobile data in Addis
 * Ababa. Self-hosting removes an entire round trip from first paint.
 *
 * The script is idempotent and never fails the build: if the machine is
 * offline, it warns and exits 0 so `npm install` still completes. The app
 * falls back to the system font stack until the files are present.
 */

import { mkdir, writeFile, access } from 'node:fs/promises';
import { constants } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const outDir = resolve(here, '..', 'public', 'dist', 'fonts');

/**
 * A modern browser UA is required: the Google Fonts CSS endpoint serves
 * legacy TTF to unrecognised clients, and we specifically want woff2.
 */
const UA =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 ' +
  '(KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

const FONTS = [
  {
    file: 'inter-latin.woff2',
    css: 'https://fonts.googleapis.com/css2?family=Inter:wght@400..800&display=swap',
    // Latin subset only - Inter's Cyrillic and Greek ranges are dead weight here.
    match: /unicode-range:\s*U\+0000-00FF/i,
    label: 'Inter (Latin)',
  },
  {
    // Display face. Inter Tight is narrower than Inter at the same size, which
    // is what lets a headline hold its line count once it is translated into
    // Amharic or Afaan Oromo - both of which run longer than the English.
    file: 'inter-tight-latin.woff2',
    css: 'https://fonts.googleapis.com/css2?family=Inter+Tight:wght@500..800&display=swap',
    match: /unicode-range:\s*U\+0000-00FF/i,
    label: 'Inter Tight (Latin, display)',
  },
  {
    // Carries the eyebrow/label role, where the wide tracking needs a face
    // designed for it.
    file: 'plex-mono-latin.woff2',
    css: 'https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500&display=swap',
    match: /unicode-range:\s*U\+0000-00FF/i,
    label: 'IBM Plex Mono (Latin, labels)',
  },
  {
    file: 'noto-ethiopic.woff2',
    css: 'https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400..700&display=swap',
    // Ethiopic has a single subset; take the first face returned.
    match: null,
    label: 'Noto Sans Ethiopic (Ge\'ez)',
  },
];

const exists = async (path) => {
  try {
    await access(path, constants.F_OK);
    return true;
  } catch {
    return false;
  }
};

/**
 * Pull the woff2 URL out of a Google Fonts CSS response.
 *
 * The response is a series of @font-face blocks, one per unicode subset.
 * When `match` is given we want the block whose unicode-range matches;
 * otherwise the first woff2 in the file.
 */
const extractWoff2 = (css, match) => {
  const blocks = css.split('@font-face').filter(Boolean);

  for (const block of blocks) {
    if (match && !match.test(block)) continue;

    const url = block.match(/url\((https:\/\/[^)]+\.woff2)\)/);
    if (url) return url[1];
  }

  // Nothing matched the subset filter - fall back to any woff2 present.
  const any = css.match(/url\((https:\/\/[^)]+\.woff2)\)/);
  return any ? any[1] : null;
};

const download = async (font) => {
  const target = resolve(outDir, font.file);

  if (await exists(target)) {
    console.log(`  ✓ ${font.label} — already present`);
    return true;
  }

  const cssResponse = await fetch(font.css, { headers: { 'User-Agent': UA } });

  if (!cssResponse.ok) {
    throw new Error(`CSS request failed (HTTP ${cssResponse.status})`);
  }

  const woff2Url = extractWoff2(await cssResponse.text(), font.match);

  if (!woff2Url) {
    throw new Error('No woff2 URL found in the CSS response');
  }

  const fontResponse = await fetch(woff2Url, { headers: { 'User-Agent': UA } });

  if (!fontResponse.ok) {
    throw new Error(`Font download failed (HTTP ${fontResponse.status})`);
  }

  const bytes = Buffer.from(await fontResponse.arrayBuffer());
  await writeFile(target, bytes);

  console.log(`  ✓ ${font.label} — ${(bytes.length / 1024).toFixed(0)} KB`);
  return true;
};

const main = async () => {
  console.log('\nFetching self-hosted fonts...\n');

  await mkdir(outDir, { recursive: true });

  let failures = 0;

  for (const font of FONTS) {
    try {
      await download(font);
    } catch (error) {
      failures++;
      console.warn(`  ! ${font.label} — ${error.message}`);
    }
  }

  if (failures > 0) {
    console.warn(
      '\nSome fonts could not be downloaded. The site still works and falls back\n' +
        'to system fonts. Re-run `npm run fonts` when you are online.\n',
    );
  } else {
    console.log('\nAll fonts are in place.\n');
  }

  // Never fail the install over a font download.
  process.exit(0);
};

main();
