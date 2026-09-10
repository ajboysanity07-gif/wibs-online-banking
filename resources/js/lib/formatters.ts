const currencyFormatter = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    currencyDisplay: 'code',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

export const formatCurrency = (value?: number | null): string => {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return '--';
    }

    return currencyFormatter.format(value);
};

export const formatDate = (value?: string | null): string => {
    if (!value) {
        return '--';
    }

    return new Date(value).toLocaleDateString();
};

export const formatDateTime = (value?: string | null): string => {
    if (!value) {
        return '--';
    }

    return new Date(value).toLocaleString();
};

export const toDateInputValue = (value?: string | null): string => {
    if (!value) {
        return '';
    }

    return value.slice(0, 10);
};

export const calculateAge = (birthdate?: string | null): number | null => {
    if (!birthdate) {
        return null;
    }

    const [year, month, day] = birthdate.slice(0, 10).split('-').map(Number);

    if (!year || !month || !day) {
        return null;
    }

    const today = new Date();
    let age = today.getFullYear() - year;
    const hasHadBirthday =
        today.getMonth() + 1 > month ||
        (today.getMonth() + 1 === month && today.getDate() >= day);

    if (!hasHadBirthday) {
        age -= 1;
    }

    return age < 0 ? null : age;
};

export const formatDisplayText = (value?: string | null): string => {
    const trimmed = value?.trim() ?? '';

    if (trimmed === '') {
        return '';
    }

    if (!/[A-Za-z]/.test(trimmed)) {
        return trimmed;
    }

    if (trimmed !== trimmed.toUpperCase()) {
        return trimmed;
    }

    return trimmed
        .toLowerCase()
        .replace(/\b([a-z])/g, (match) => match.toUpperCase());
};

const normalizeLocationParts = (
    parts: Array<string | null | undefined>,
): string[] =>
    parts.map((value) => value?.trim() ?? '').filter((value) => value !== '');

const normalizeForMatch = (value: string): string =>
    value.toLowerCase().trim().replace(/\s+/g, ' ');

const significantWords = (value: string): string[] =>
    (value.match(/[a-z0-9]+/g) ?? []).filter((word) => word.length >= 3);

const levenshteinDistance = (left: string, right: string): number => {
    const rows = left.length + 1;
    const cols = right.length + 1;
    const distances: number[][] = Array.from({ length: rows }, () =>
        new Array<number>(cols).fill(0),
    );

    for (let i = 0; i < rows; i += 1) {
        distances[i][0] = i;
    }

    for (let j = 0; j < cols; j += 1) {
        distances[0][j] = j;
    }

    for (let i = 1; i < rows; i += 1) {
        for (let j = 1; j < cols; j += 1) {
            const cost = left[i - 1] === right[j - 1] ? 0 : 1;
            distances[i][j] = Math.min(
                distances[i - 1][j] + 1,
                distances[i][j - 1] + 1,
                distances[i - 1][j - 1] + cost,
            );
        }
    }

    return distances[rows - 1][cols - 1];
};

// Mirrors LocationComposer::isRedundantPart() on the backend so address
// values that mix a pre-composed legacy string (e.g. address1 already
// containing the barangay/city/province) with the now-separate structured
// fields don't render as a duplicated, comma-noisy mess.
const isRedundantPart = (part: string, joined: string): boolean => {
    if (joined === '' || part === '') {
        return false;
    }

    if (joined.includes(part)) {
        return true;
    }

    const partWords = significantWords(part);

    if (partWords.length < 2) {
        return false;
    }

    const joinedWords = significantWords(joined);
    const tail = joinedWords.slice(-partWords.length);

    if (tail.length !== partWords.length) {
        return false;
    }

    return partWords.every(
        (word, index) => levenshteinDistance(word, tail[index]) <= 2,
    );
};

const composeUniqueLocationParts = (
    parts: Array<string | null | undefined>,
): string => {
    const normalizedParts = normalizeLocationParts(parts);

    const kept: string[] = [];
    let joined = '';

    for (const part of normalizedParts) {
        const normalized = normalizeForMatch(part);

        if (normalized !== '' && isRedundantPart(normalized, joined)) {
            continue;
        }

        kept.push(part);
        joined = joined !== '' ? `${joined} ${normalized},` : `${normalized},`;
    }

    return kept.join(', ');
};

export const composeAddress = (
    address1?: string | null,
    address2?: string | null,
    address3?: string | null,
    barangay?: string | null,
): string =>
    composeUniqueLocationParts([address1, barangay, address2, address3]);

export const composeBirthplace = (
    city?: string | null,
    province?: string | null,
): string => normalizeLocationParts([city, province]).join(', ');

export const formatHousingStatus = (value: string): string => {
    const trimmed = value.trim();

    if (trimmed === '') {
        return '--';
    }

    const upper = trimmed.toUpperCase();

    if (upper === 'OWNED') {
        return 'Owned';
    }

    if (upper === 'RENT' || upper === 'RENTAL') {
        return 'Rent';
    }

    return trimmed;
};

export const formatCivilStatus = (value: string): string => {
    const trimmed = value.trim();

    if (trimmed === '') {
        return '--';
    }

    const upper = trimmed.toUpperCase();

    if (upper === 'SINGLE') {
        return 'Single';
    }

    if (upper === 'MARRIED') {
        return 'Married';
    }

    if (upper === 'SEPARATED') {
        return 'Separated';
    }

    if (upper === 'WIDOWED') {
        return 'Widowed';
    }

    return trimmed;
};

export const formatPayday = (value: string): string => {
    const trimmed = value.trim();

    if (trimmed === '') {
        return '--';
    }

    const valid = [
        'Daily',
        'Due date',
        'Monthly',
        'Quincenal',
        'Semi-annual',
        'Weekly',
        'Yearly',
    ];
    if (valid.includes(trimmed)) {
        return trimmed;
    }

    const upper = trimmed.toUpperCase();
    const compact = upper.replace(/[^0-9A-Z]/g, '');

    if (upper === 'WEEKLY' || compact === 'BIWEEKLY') return 'Weekly';
    if (upper === 'MONTHLY') return 'Monthly';
    if (
        compact === '15' ||
        compact === '30' ||
        (upper.includes('15') && upper.includes('30')) ||
        compact === 'SEMIMONTHLY'
    )
        return 'Quincenal';
    if (upper.includes('LUMP')) return 'Due date';
    if (upper === 'DAILY') return 'Daily';
    if (upper === 'SEMIANNUAL' || compact === 'SEMIA') return 'Semi-annual';
    if (upper === 'YEARLY' || upper === 'ANNUAL') return 'Yearly';

    return trimmed;
};
