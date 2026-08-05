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

export const composeAddress = (
    address1?: string | null,
    address2?: string | null,
    address3?: string | null,
    barangay?: string | null,
): string =>
    normalizeLocationParts([address1, barangay, address2, address3]).join(', ');

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

    const upper = trimmed.toUpperCase();
    const compact = upper.replace(/[^0-9A-Z]/g, '');

    if (upper === 'WEEKLY') {
        return 'Weekly';
    }

    if (upper === 'MONTHLY') {
        return 'Monthly';
    }

    if (compact === 'BIWEEKLY') {
        return 'Bi-Weekly';
    }

    if (compact === '15') {
        return '15th';
    }

    if (compact === '30') {
        return '30th';
    }

    if (upper.includes('15') && upper.includes('30')) {
        return '15th & 30th';
    }

    return trimmed;
};
