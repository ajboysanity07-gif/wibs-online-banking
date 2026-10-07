import { Link } from '@inertiajs/react';
import { FileText, Plus, ShieldCheck } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import LoanRequestController from '@/actions/App/Http/Controllers/Client/LoanRequestController';
import { loans as clientLoans } from '@/routes/client';
import { security as securitySettings } from '@/routes/settings';

const actions: Array<{ label: string; href: string; icon: LucideIcon }> = [
    {
        label: 'New loan request',
        href: LoanRequestController.create().url,
        icon: Plus,
    },
    {
        label: 'Statement of account',
        href: clientLoans().url,
        icon: FileText,
    },
    {
        label: 'Security settings',
        href: securitySettings().url,
        icon: ShieldCheck,
    },
];

export function MemberQuickActions() {
    return (
        <section aria-labelledby="quick-actions-heading" className="space-y-3">
            <h2 id="quick-actions-heading" className="text-lg font-semibold">
                Quick actions
            </h2>
            <div className="grid grid-cols-2 gap-3 md:grid-cols-3">
                {actions.map(({ label, href, icon: Icon }) => (
                    <Link
                        key={label}
                        href={href}
                        className="flex min-h-24 flex-col gap-2.5 rounded-xl border border-border bg-card p-4 transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <span
                            aria-hidden="true"
                            className="flex size-10 items-center justify-center rounded-lg border border-border bg-muted text-primary"
                        >
                            <Icon className="size-[18px]" />
                        </span>
                        <span className="text-sm font-semibold">{label}</span>
                    </Link>
                ))}
            </div>
        </section>
    );
}
