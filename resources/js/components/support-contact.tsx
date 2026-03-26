import { useBranding } from '@/hooks/use-branding';
import { cn } from '@/lib/utils';

type SupportContactProps = {
    className?: string;
    label?: string;
    variant?: 'inline' | 'stacked';
};

type SupportContactItem = {
    label: string;
    href?: string;
};

export default function SupportContact({
    className,
    label = 'Support',
    variant = 'stacked',
}: SupportContactProps) {
    const { supportContactName, supportEmail, supportPhone } = useBranding();
    const items = [
        supportContactName ? { label: supportContactName } : null,
        supportEmail
            ? { label: supportEmail, href: `mailto:${supportEmail}` }
            : null,
        supportPhone
            ? { label: supportPhone, href: `tel:${supportPhone}` }
            : null,
    ].filter(Boolean) as SupportContactItem[];

    if (items.length === 0) {
        return null;
    }

    const content = items.map((item, index) => {
        const body = item.href ? (
            <a
                href={item.href}
                className="underline-offset-2 hover:text-foreground hover:underline"
            >
                {item.label}
            </a>
        ) : (
            <span>{item.label}</span>
        );

        if (variant === 'inline') {
            return (
                <span
                    key={`${item.label}-${index}`}
                    className="flex items-center gap-2"
                >
                    {index > 0 ? (
                        <span aria-hidden className="text-muted-foreground/70">
                            |
                        </span>
                    ) : null}
                    {body}
                </span>
            );
        }

        return <span key={`${item.label}-${index}`}>{body}</span>;
    });

    const baseClassName =
        variant === 'inline'
            ? 'flex flex-wrap items-center gap-2 text-xs text-muted-foreground'
            : 'flex flex-col gap-1 text-xs text-muted-foreground';

    return (
        <div className={cn(baseClassName, className)}>
            <span className="font-semibold text-foreground">
                {label}
                {variant === 'inline' ? ':' : ''}
            </span>
            {content}
        </div>
    );
}
