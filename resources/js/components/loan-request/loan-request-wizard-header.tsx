import { Loader2, X } from 'lucide-react';
import { useState } from 'react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';

type Props = {
    accountNo: string;
    lastSaved: string | null;
    /** Ask before leaving (unsaved edits, or a saved draft that could be discarded). */
    confirmExit: boolean;
    canDiscard: boolean;
    isSaving: boolean;
    isDiscarding: boolean;
    onExit: () => void;
    onSaveAndExit: () => void;
    onDiscard: () => void;
};

/** Solid brand bar for the focused loan wizard. */
export function LoanRequestWizardHeader({
    accountNo,
    lastSaved,
    confirmExit,
    canDiscard,
    isSaving,
    isDiscarding,
    onExit,
    onSaveAndExit,
    onDiscard,
}: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);

    return (
        <header className="sticky top-0 z-30 border-b border-sidebar-border bg-sidebar text-sidebar-foreground">
            <div className="mx-auto flex h-16 w-full max-w-[1040px] items-center gap-3 px-4 md:px-7">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Exit application"
                    className="shrink-0 rounded-full border border-sidebar-foreground/50 hover:bg-sidebar-foreground/15 hover:text-sidebar-foreground"
                    onClick={() =>
                        confirmExit ? setDialogOpen(true) : onExit()
                    }
                >
                    <X className="size-5" />
                </Button>
                <div className="min-w-0 flex-1 text-center">
                    <h1 className="truncate text-base font-bold">
                        Apply for a loan
                    </h1>
                    <p className="truncate text-xs text-sidebar-foreground/80">
                        Account No: {accountNo}
                        {' · '}
                        {lastSaved
                            ? `Last saved ${lastSaved}`
                            : 'Not saved yet'}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="accent"
                    className="shrink-0 font-bold"
                    disabled={isSaving}
                    onClick={onSaveAndExit}
                >
                    {isSaving ? <Loader2 className="animate-spin" /> : null}
                    Save &amp; exit
                </Button>
            </div>

            <AlertDialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <AlertDialogContent className="data-[size=default]:sm:max-w-2xl">
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Leave this application?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Save your progress to continue later, or leave
                            without saving.
                            {canDiscard
                                ? ' Discarding permanently deletes your saved draft.'
                                : ''}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter className="sm:flex-wrap">
                        <AlertDialogCancel>Keep editing</AlertDialogCancel>
                        {canDiscard ? (
                            <AlertDialogAction
                                variant="destructive"
                                disabled={isDiscarding}
                                onClick={onDiscard}
                            >
                                Discard draft
                            </AlertDialogAction>
                        ) : null}
                        <AlertDialogAction variant="outline" onClick={onExit}>
                            Exit without saving
                        </AlertDialogAction>
                        <AlertDialogAction onClick={onSaveAndExit}>
                            Save &amp; exit
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </header>
    );
}
