import { ChevronUp, Loader2, MoreHorizontal } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

export type LoanRequestBarAction = {
    key: string;
    label: string;
    onSelect: () => void;
    isProcessing?: boolean;
    /** Client-side gate: keeps the button visible but inert, with the reason. */
    blockedReason?: string | null;
};

export type LoanRequestBarDirectAction = LoanRequestBarAction & {
    tone: 'ghost' | 'outline' | 'destructive';
};

type Props = {
    /** Shown as buttons on desktop, listed under "More" on mobile. */
    direct: LoanRequestBarDirectAction[];
    /** Forward action (Recommend approval / Approve / ...). */
    primary?: LoanRequestBarAction;
    /** Always under "More". */
    overflow: LoanRequestBarAction[];
};

const destructiveOutline =
    'border-destructive text-destructive hover:bg-destructive/10 hover:text-destructive';

const blockedClassName = 'cursor-not-allowed opacity-[.45]';

function ActionLabel({ action }: { action: LoanRequestBarAction }) {
    return (
        <>
            {action.isProcessing ? (
                <Loader2 className="size-4 animate-spin" />
            ) : null}
            {action.label}
        </>
    );
}

const run = (action: LoanRequestBarAction) => {
    if (action.blockedReason || action.isProcessing) {
        return;
    }

    action.onSelect();
};

export function LoanRequestActionBar({ direct, primary, overflow }: Props) {
    const [isMoreOpen, setIsMoreOpen] = useState(false);
    const mobileMore = [...direct, ...overflow];

    if (direct.length === 0 && !primary && overflow.length === 0) {
        return null;
    }

    return (
        <>
            <div className="hidden flex-wrap items-center gap-2 sm:flex">
                {direct.map((action) => (
                    <Button
                        key={action.key}
                        type="button"
                        variant={action.tone === 'ghost' ? 'ghost' : 'outline'}
                        className={cn(
                            'min-h-11 font-semibold lg:min-h-9',
                            action.tone === 'ghost' && 'text-muted-foreground',
                            action.tone === 'destructive' && destructiveOutline,
                        )}
                        disabled={action.isProcessing}
                        onClick={() => run(action)}
                    >
                        <ActionLabel action={action} />
                    </Button>
                ))}
                {overflow.length > 0 ? (
                    <DropdownMenu modal={false}>
                        <DropdownMenuTrigger asChild>
                            <Button
                                type="button"
                                variant="outline"
                                className="min-h-11 font-semibold lg:min-h-9"
                            >
                                <MoreHorizontal />
                                More
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {overflow.map((action) => (
                                <DropdownMenuItem
                                    key={action.key}
                                    className="min-h-11 lg:min-h-8"
                                    disabled={action.isProcessing}
                                    onSelect={() => run(action)}
                                >
                                    <ActionLabel action={action} />
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                ) : null}
                {primary ? (
                    <Button
                        type="button"
                        className={cn(
                            'min-h-11 px-4 font-bold lg:min-h-9',
                            primary.blockedReason && blockedClassName,
                        )}
                        aria-disabled={Boolean(primary.blockedReason)}
                        title={primary.blockedReason ?? undefined}
                        disabled={primary.isProcessing}
                        onClick={() => run(primary)}
                    >
                        <ActionLabel action={primary} />
                    </Button>
                ) : null}
            </div>

            <div className="fixed inset-x-0 bottom-0 z-20 flex flex-col gap-2 border-t border-border bg-card px-3 pt-2.5 pb-[max(0.625rem,env(safe-area-inset-bottom))] shadow-[0_-4px_16px_color-mix(in_oklab,var(--color-foreground)_20%,transparent)] sm:hidden">
                {isMoreOpen ? (
                    <div className="flex flex-col gap-1.5">
                        {mobileMore.map((action) => {
                            const isDestructive =
                                'tone' in action &&
                                action.tone === 'destructive';

                            return (
                                <Button
                                    key={action.key}
                                    type="button"
                                    variant="outline"
                                    className={cn(
                                        'h-12 w-full text-[15px] font-semibold',
                                        isDestructive && destructiveOutline,
                                    )}
                                    disabled={action.isProcessing}
                                    onClick={() => {
                                        setIsMoreOpen(false);
                                        run(action);
                                    }}
                                >
                                    <ActionLabel action={action} />
                                </Button>
                            );
                        })}
                    </div>
                ) : null}
                <div className="flex gap-2">
                    {mobileMore.length > 0 ? (
                        <Button
                            type="button"
                            variant="outline"
                            className={cn(
                                'h-12 px-5 text-[15px] font-semibold',
                                !primary && 'flex-1',
                            )}
                            aria-expanded={isMoreOpen}
                            onClick={() => setIsMoreOpen((open) => !open)}
                        >
                            More
                            <ChevronUp
                                className={cn(
                                    'transition-transform motion-reduce:transition-none',
                                    !isMoreOpen && 'rotate-180',
                                )}
                            />
                        </Button>
                    ) : null}
                    {primary ? (
                        <Button
                            type="button"
                            className={cn(
                                'h-12 flex-1 text-[15px] font-bold',
                                primary.blockedReason && blockedClassName,
                            )}
                            aria-disabled={Boolean(primary.blockedReason)}
                            disabled={primary.isProcessing}
                            onClick={() => run(primary)}
                        >
                            <ActionLabel action={primary} />
                        </Button>
                    ) : null}
                </div>
            </div>
        </>
    );
}
