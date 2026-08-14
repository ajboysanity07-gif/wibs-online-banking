export const SELF_EMPLOYED_EMPLOYMENT_TYPE = 'Self Employed';
export const PENSIONER_EMPLOYMENT_TYPE = 'Pensioner';

function normalizeEmploymentType(value: string | null | undefined): string {
    return (value ?? '')
        .trim()
        .toLowerCase()
        .replace(/[\s-]+/g, ' ');
}

export function isSelfEmployedType(value: string | null | undefined): boolean {
    return (
        normalizeEmploymentType(value) ===
        normalizeEmploymentType(SELF_EMPLOYED_EMPLOYMENT_TYPE)
    );
}

export function isPensionerType(value: string | null | undefined): boolean {
    return (
        normalizeEmploymentType(value) ===
        normalizeEmploymentType(PENSIONER_EMPLOYMENT_TYPE)
    );
}
