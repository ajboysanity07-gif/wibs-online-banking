import { cn } from '@/lib/utils';

export type LoanRequestSectionNavItem = {
    key: string;
    label: string;
};

type Props = {
    sections: LoanRequestSectionNavItem[];
    activeSection: string;
    onSectionChange: (key: string) => void;
};

export function LoanRequestSectionNav({
    sections,
    activeSection,
    onSectionChange,
}: Props) {
    return (
        <>
            <nav
                className="hidden flex-col gap-0.5 lg:flex"
                aria-label="Loan request sections"
            >
                {sections.map((section) => {
                    const isActive = section.key === activeSection;

                    return (
                        <button
                            key={section.key}
                            type="button"
                            className={cn(
                                'flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-[13px] font-medium transition-colors',
                                isActive
                                    ? 'bg-primary/10 text-primary'
                                    : 'hover:bg-muted/50',
                            )}
                            onClick={() => onSectionChange(section.key)}
                            aria-current={isActive ? 'step' : undefined}
                        >
                            {section.label}
                        </button>
                    );
                })}
            </nav>

            <nav
                className="flex gap-2 overflow-x-auto pb-2 lg:hidden"
                aria-label="Loan request sections"
            >
                {sections.map((section) => {
                    const isActive = section.key === activeSection;

                    return (
                        <button
                            key={section.key}
                            type="button"
                            className={cn(
                                'shrink-0 rounded-full px-3 py-1.5 text-sm font-medium transition-colors',
                                isActive
                                    ? 'bg-primary/10 text-primary'
                                    : 'hover:bg-muted/50',
                            )}
                            onClick={() => onSectionChange(section.key)}
                            aria-current={isActive ? 'step' : undefined}
                        >
                            {section.label}
                        </button>
                    );
                })}
            </nav>
        </>
    );
}
