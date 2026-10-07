import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';

export function DetailsLink({
    href,
    label,
    children = 'Details',
}: {
    href: string;
    label: string;
    children?: ReactNode;
}) {
    return (
        <Link
            href={href}
            aria-label={label}
            className="inline-flex min-h-10 items-center gap-1 rounded-[8px] px-2.5 py-1.5 text-[13.5px] font-semibold whitespace-nowrap text-primary hover:bg-primary/10 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
        >
            {children}
            <ChevronRight aria-hidden="true" className="h-4 w-4" />
        </Link>
    );
}
