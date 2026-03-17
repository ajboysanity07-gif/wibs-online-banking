import { usePage } from '@inertiajs/react';
import type { Branding } from '@/types';

export function useBranding(): Branding {
    return usePage().props.branding as Branding;
}
