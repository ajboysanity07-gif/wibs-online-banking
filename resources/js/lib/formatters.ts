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
