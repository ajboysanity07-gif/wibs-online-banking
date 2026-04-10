import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { decode } from '@toon-format/toon';
import {
  getAllProvinces,
  getAllRegions,
} from '@aivangogh/ph-address';
import pako from 'pako';

const ROOT_DIR = process.cwd();
const DATA_PATH = path.join(ROOT_DIR, 'resources', 'data', 'ph-address.json');
const FIXTURE_PATH = path.join(
  ROOT_DIR,
  'tests',
  'Fixtures',
  'ph-address.json',
);

const writeJson = async (filePath, payload) => {
  await mkdir(path.dirname(filePath), { recursive: true });
  await writeFile(filePath, `${JSON.stringify(payload, null, 2)}\n`, 'utf8');
};

const normalizeRegions = (regions) => {
  const regionMap = {};
  const regionCodes = new Set();

  for (const region of regions) {
    if (!region || !region.psgcCode) {
      continue;
    }

    const name = region.name || '';
    const designation = (region.designation || '').trim();
    const label = designation ? `${name} (${designation})` : name;
    const code = String(region.psgcCode);

    regionMap[code] = label;
    regionCodes.add(code);
  }

  return { regionMap, regionCodes };
};

const normalizeProvinces = (provinces) => {
  const provinceMap = {};
  const provinceRegions = new Map();

  for (const province of provinces) {
    if (!province || !province.psgcCode) {
      continue;
    }

    const code = String(province.psgcCode);
    provinceMap[code] = province.name || '';

    if (province.regionCode) {
      provinceRegions.set(code, String(province.regionCode));
    }
  }

  return { provinceMap, provinceRegions };
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

const regionCodeFrom = (code) => {
  if (!code || code.length < 2) {
    return null;
  }

  return `${code.slice(0, 2)}00000000`;
};

const buildLocalities = (municipalities, provinceRegions, regionCodes) => {
  const localities = [];
  const seen = new Set();

  const addLocality = (entry) => {
    if (!entry || !entry.psgcCode) {
      return;
    }

    const code = String(entry.psgcCode);

    if (seen.has(code)) {
      return;
    }

    const name = entry.name || '';
    const type = /\bCity\b/i.test(name) ? 'City' : 'Mun';
    const provinceCode = entry.provinceCode ? String(entry.provinceCode) : null;
    let regionCode =
      provinceCode && provinceRegions.has(provinceCode)
        ? provinceRegions.get(provinceCode)
        : null;

    if (!regionCode && provinceCode && regionCodes.has(provinceCode)) {
      regionCode = provinceCode;
    }

    if (!regionCode) {
      regionCode = regionCodeFrom(code);
    }

    const locality = {
      code,
      name,
      type,
    };

    if (provinceCode) {
      locality.province_code = provinceCode;
    }

    if (regionCode) {
      locality.region_code = regionCode;
    }

    localities.push(locality);
    seen.add(code);
  };

  for (const entry of municipalities) {
    addLocality(entry);
  }

  localities.sort((a, b) => {
    const nameOrder = a.name.localeCompare(b.name);

    if (nameOrder !== 0) {
      return nameOrder;
    }

    return a.code.localeCompare(b.code);
  });

  return localities;
};

const buildDataset = async () => {
  const regions = getAllRegions();
  const provinces = getAllProvinces();
  const municipalities = await loadMunicipalities();
  const { regionMap, regionCodes } = normalizeRegions(regions);
  const { provinceMap, provinceRegions } = normalizeProvinces(provinces);
  const localities = buildLocalities(
    municipalities,
    provinceRegions,
    regionCodes,
  );

  return {
    regions: regionMap,
    provinces: provinceMap,
    localities,
  };
};

const buildFixture = (dataset) => {
  const fixtureProvinceCodes = [
    '0102800000',
    '0702200000',
    '1102300000',
    '1102400000',
    '1102500000',
    '1108200000',
    '1108600000',
    '1204700000',
  ];
  const fixtureLocalityTargets = [
    { name: 'Adams', provinceCode: '0102800000' },
    { name: 'Batac City', provinceCode: '0102800000' },
    { name: 'Carmen', provinceCode: '0702200000' },
    { name: 'Carmen', provinceCode: '1102300000' },
    { name: 'Carmen', provinceCode: '1204700000' },
    { name: 'Davao City', provinceCode: '1130700000' },
  ];

  const localityLookup = new Map();

  for (const locality of dataset.localities) {
    const provinceCode = locality.province_code ?? '';
    localityLookup.set(`${locality.name}::${provinceCode}`, locality);
  }

  const fixtureLocalities = [];
  const missing = [];

  for (const target of fixtureLocalityTargets) {
    const key = `${target.name}::${target.provinceCode}`;
    const locality = localityLookup.get(key);

    if (!locality) {
      missing.push(key);

      continue;
    }

    fixtureLocalities.push(locality);
  }

  if (missing.length > 0) {
    throw new Error(`Fixture localities not found: ${missing.join(', ')}`);
  }

  const fixtureProvinces = {};

  for (const code of fixtureProvinceCodes) {
    if (dataset.provinces[code]) {
      fixtureProvinces[code] = dataset.provinces[code];
    }
  }

  const fixtureRegionCodes = new Set();

  for (const locality of fixtureLocalities) {
    if (locality.region_code) {
      fixtureRegionCodes.add(locality.region_code);
    }
  }

  const fixtureRegions = {};

  for (const code of fixtureRegionCodes) {
    if (dataset.regions[code]) {
      fixtureRegions[code] = dataset.regions[code];
    }
  }

  fixtureLocalities.sort((a, b) => a.code.localeCompare(b.code));

  return {
    regions: fixtureRegions,
    provinces: fixtureProvinces,
    localities: fixtureLocalities,
  };
};

const dataset = await buildDataset();
const fixture = buildFixture(dataset);

await writeJson(DATA_PATH, dataset);
await writeJson(FIXTURE_PATH, fixture);

console.log(`Generated ${DATA_PATH}`);
console.log(`Generated ${FIXTURE_PATH}`);
