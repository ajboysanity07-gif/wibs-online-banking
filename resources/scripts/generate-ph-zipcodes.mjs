import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';

const ROOT_DIR = process.cwd();
const ADDRESS_PATH = path.join(
    ROOT_DIR,
    'resources',
    'data',
    'ph-address.json',
);
const ZIPCODES_PATH = path.join(
    ROOT_DIR,
    'node_modules',
    'zipcodes-ph',
    'build',
    'zipcodes.json',
);
const DATA_PATH = path.join(ROOT_DIR, 'resources', 'data', 'ph-zipcodes.json');
const FIXTURE_PATH = path.join(
    ROOT_DIR,
    'tests',
    'Fixtures',
    'ph-zipcodes.json',
);

const ABBREVIATIONS = {
    '\\bsta\\.?\\b': 'santa',
    '\\bsto\\.?\\b': 'santo',
    '\\bst\\.?\\b': 'santo',
    '\\bgen\\.?\\b': 'general',
    '\\bmt\\.?\\b': 'mount',
    '\\bgov\\.?\\b': 'governor',
};

const writeJson = async (filePath, payload) => {
    await mkdir(path.dirname(filePath), { recursive: true });
    await writeFile(filePath, `${JSON.stringify(payload, null, 2)}\n`, 'utf8');
};

const normalizeName = (name) => {
    let normalized = String(name || '').toLowerCase();
    normalized = normalized.replace(/\([^)]*\)/g, ' ');
    normalized = normalized.replace(/^city of\s+/, '');
    normalized = normalized.replace(/\s+city$/, '');
    normalized = normalized.replace(/old\s*:\s*/g, ' ');

    for (const [pattern, replacement] of Object.entries(ABBREVIATIONS)) {
        normalized = normalized.replace(new RegExp(pattern, 'g'), replacement);
    }

    normalized = normalized.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    normalized = normalized.replace(/['’]/g, '');

    return normalized
        .replace(/[^a-z0-9]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
};

const loadJson = async (filePath) =>
    JSON.parse(await readFile(filePath, 'utf8'));

const loadZipcodes = async () => {
    const raw = await loadJson(ZIPCODES_PATH);
    const entries = [];

    for (const [zip, value] of Object.entries(raw)) {
        const isShared = Array.isArray(value);
        const places = isShared ? value : [value];

        for (const place of places) {
            const normalized = normalizeName(place);

            if (normalized === '') {
                continue;
            }

            entries.push({
                zip,
                place,
                normalized,
                isShared,
                isPostOffice: /\b(cpo|p\.?\s?o\.?|post office)\b/i.test(place),
            });
        }
    }

    return entries;
};

const collectMatches = (entries, base) => {
    const cleanExact = [];
    const postOffice = [];
    const contains = [];

    for (const entry of entries) {
        if (!entry.isShared && entry.normalized === base) {
            cleanExact.push(entry);

            continue;
        }

        if (!entry.normalized.includes(base)) {
            continue;
        }

        if (entry.isPostOffice) {
            postOffice.push(entry);
        } else {
            contains.push(entry);
        }
    }

    return { cleanExact, postOffice, contains };
};

const pickZip = (groups) => {
    const pickLowest = (candidates) => {
        if (candidates.length === 0) {
            return null;
        }

        return candidates
            .map((entry) => Number(entry.zip))
            .sort((a, b) => a - b)[0]
            .toString();
    };

    const preferred =
        groups.postOffice.length > 0
            ? groups.postOffice
            : groups.cleanExact.length > 0
              ? groups.cleanExact
              : groups.contains;

    return pickLowest(preferred);
};

const buildZipcodes = (addresses, entries) => {
    const zipcodes = {};

    for (const locality of addresses.localities ?? []) {
        const code = String(locality.code || '');
        const name = String(locality.name || '');

        if (code === '' || name === '') {
            continue;
        }

        const isCity = String(locality.type || '').toLowerCase() === 'city';
        const base = normalizeName(name);

        if (base === '') {
            continue;
        }

        const groups = collectMatches(entries, base);
        const prioritized = isCity
            ? {
                  postOffice: groups.postOffice,
                  cleanExact: groups.cleanExact,
                  contains: groups.contains,
              }
            : {
                  cleanExact: groups.cleanExact,
                  postOffice: groups.postOffice,
                  contains: groups.contains,
              };
        const zip = pickZip(prioritized);

        if (zip !== null) {
            zipcodes[code] = zip;
        }
    }

    return zipcodes;
};

const FIXTURE_LOCALITY_NAMES = [
    'Barobo',
    'Cebu City',
    'Quezon City',
    'Batac City',
    'Lianga',
];

const buildFixture = (zipcodes, addresses) => {
    const byName = new Map();

    for (const locality of addresses.localities ?? []) {
        byName.set(locality.name, String(locality.code || ''));
    }

    const fixture = {};

    for (const name of FIXTURE_LOCALITY_NAMES) {
        const code = byName.get(name);

        if (code && zipcodes[code]) {
            fixture[code] = zipcodes[code];
        }
    }

    return fixture;
};

const addresses = await loadJson(ADDRESS_PATH);
const entries = await loadZipcodes();
const zipcodes = buildZipcodes(addresses, entries);
const fixture = buildFixture(zipcodes, addresses);

await writeJson(DATA_PATH, zipcodes);
await writeJson(FIXTURE_PATH, fixture);

console.log(
    `Generated ${DATA_PATH} (${Object.keys(zipcodes).length} localities)`,
);
console.log(`Generated ${FIXTURE_PATH}`);
