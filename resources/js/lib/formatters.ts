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
): string => normalizeLocationParts([address1, address2, address3]).join(', ');

export const composeBirthplace = (
    city?: string | null,
    province?: string | null,
): string => normalizeLocationParts([city, province]).join(', ');
