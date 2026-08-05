import { mkdir, writeFile, readFile } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { getBarangaysByMunicipality } from '@aivangogh/ph-address';
import { decode } from '@toon-format/toon';
import pako from 'pako';

const ROOT_DIR = process.cwd();
const DATA_PATH = path.join(ROOT_DIR, 'resources', 'data', 'ph-barangays.json');
const FIXTURE_PATH = path.join(
  ROOT_DIR,
  'tests',
  'Fixtures',
  'ph-barangays.json',
);

const writeJson = async (filePath, payload) => {
  await mkdir(path.dirname(filePath), { recursive: true });
  await writeFile(filePath, `${JSON.stringify(payload, null, 2)}\n`, 'utf8');
};

const MUNICIPALITIES_PATTERN = /\bmunicipalities\s*=\s*"([^"]+)"/;

const loadMunicipalities = async () => {
  const sourcePath = path.join(
    ROOT_DIR,
    'node_modules',
    '@aivangogh',
    'ph-address',
    'dist',
    'index.js',
  );
  const contents = await readFile(sourcePath, 'utf8');
  const match = contents.match(MUNICIPALITIES_PATTERN);

  if (!match) {
    throw new Error(
      'Unable to locate municipalities dataset in @aivangogh/ph-address.',
    );
  }

  const compressed = match[1];
  const decompressed = pako.inflate(Buffer.from(compressed, 'base64'), {
    to: 'string',
  });

  return decode(decompressed);
};

const buildBarangays = (municipalities) => {
  const barangays = {};

  for (const municipality of municipalities) {
    if (!municipality || !municipality.psgcCode) {
      continue;
    }

    const code = String(municipality.psgcCode);
    const list = getBarangaysByMunicipality(code) || [];

    if (list.length === 0) {
      continue;
    }

    barangays[code] = list
      .map((barangay) => ({
        code: String(barangay.psgcCode),
        name: barangay.name,
      }))
      .sort((a, b) => a.code.localeCompare(b.code));
  }

  return barangays;
};

const FIXTURE_MUNICIPALITY_CODES = [
  '0102801000', // Adams, Ilocos Norte
  '0102805000', // Batac City
  '0702215000', // Carmen, Cebu
  '1102303000', // Carmen, Davao del Norte
  '1130700000', // Davao City
  '1204702000', // Carmen, Cotabato
];

const buildFixture = (barangays) => {
  const fixture = {};

  for (const code of FIXTURE_MUNICIPALITY_CODES) {
    if (barangays[code]) {
      fixture[code] = barangays[code];
    }
  }

  if (Object.keys(fixture).length !== FIXTURE_MUNICIPALITY_CODES.length) {
    throw new Error(
      `Fixture municipalities not found: ${FIXTURE_MUNICIPALITY_CODES.filter(
        (code) => !fixture[code],
      ).join(', ')}`,
    );
  }

  return fixture;
};

const municipalities = await loadMunicipalities();
const barangays = buildBarangays(municipalities);
const fixture = buildFixture(barangays);

await writeJson(DATA_PATH, barangays);
await writeJson(FIXTURE_PATH, fixture);

console.log(
  `Generated ${DATA_PATH} (${Object.keys(barangays).length} municipalities)`,
);
console.log(`Generated ${FIXTURE_PATH}`);
