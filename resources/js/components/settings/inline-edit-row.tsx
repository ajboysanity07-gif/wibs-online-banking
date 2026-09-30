import { Loader2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { createContext, useContext, useEffect, useRef, useState } from 'react';
import { flushSync } from 'react-dom';
import { RowEditButton } from '@/components/settings/settings-panel';
import { Button } from '@/components/ui/button';

const OPEN_EVENT = 'inline-row:open';
const CLOSE_ALL_EVENT = 'inline-row:close-all';

type InlineEditContextValue = {
    // false = every row is always open (onboarding keeps today's full form).
    rowMode: boolean;
    processing: boolean;
    anyOpen: boolean;
    setOpenCount: (update: (count: number) => number) => void;
    onDiscard: () => void;
};

const InlineEditContext = createContext<InlineEditContextValue>({
    rowMode: false,
    processing: false,
    anyOpen: false,
    setOpenCount: () => {},
    onDiscard: () => {},
});

export function InlineEditProvider({
    rowMode,
    processing,
    onDiscard,
    children,
}: {
    rowMode: boolean;
    processing: boolean;
    onDiscard: () => void;
    children: ReactNode;
}) {
    const [openCount, setOpenCount] = useState(0);

    useEffect(() => {
        const reset = () => setOpenCount(0);
        window.addEventListener(CLOSE_ALL_EVENT, reset);

        return () => window.removeEventListener(CLOSE_ALL_EVENT, reset);
    }, []);

    return (
        <InlineEditContext.Provider
            value={{
                rowMode,
                processing,
                anyOpen: openCount > 0,
                setOpenCount,
                onDiscard,
            }}
        >
            {children}
        </InlineEditContext.Provider>
    );
}

/** Called after a successful save: every open row returns to its value. */
export const closeInlineRows = () => {
    window.dispatchEvent(new Event(CLOSE_ALL_EVENT));
};

/** Opens the row that owns a field (no-op when it is already open). */
export const openInlineRowFor = (element: Element | null) => {
    element?.closest('[data-inline-row]')?.dispatchEvent(new Event(OPEN_EVENT));
};

const editableSelector =
    'input:not([type=hidden]):not([type=file]):not([type=checkbox]):not([type=radio]), textarea, button[role=combobox]';

// The editors stay mounted (hidden) while a row is closed, so the value shown
// is read straight from the same inputs that get submitted.
const readDisplay = (root: HTMLElement | null): string => {
    if (!root) {
        return '';
    }

    const entries = Array.from(
        root.querySelectorAll<HTMLElement>(editableSelector),
    ).map((element) => {
        const value =
            element instanceof HTMLButtonElement
                ? element.hasAttribute('data-placeholder')
                    ? ''
                    : (element.textContent ?? '').trim()
                : (element as HTMLInputElement).value.trim();
        const label = element.id
            ? root
                  .querySelector(`label[for="${CSS.escape(element.id)}"]`)
                  ?.textContent?.trim()
            : undefined;

        return { label, value };
    });
    const filled = entries.filter((entry) => entry.value !== '');

    if (entries.length <= 1) {
        return filled[0]?.value ?? '';
    }

    return filled
        .map((entry) =>
            entry.label ? `${entry.label}: ${entry.value}` : entry.value,
        )
        .join('\n');
};

export function InlineEditRow({
    label,
    children,
    readOnly = false,
    group = false,
    value: valueOverride,
}: {
    label: string;
    // The existing field markup (label, input, error) -- rendered unchanged.
    children: ReactNode;
    // Shown as a value only; never gets an Edit button.
    readOnly?: boolean;
    // Several fields edited together; keeps their own labels.
    group?: boolean;
    value?: string;
}) {
    const { rowMode, processing, anyOpen, setOpenCount, onDiscard } =
        useContext(InlineEditContext);
    const rootRef = useRef<HTMLDivElement>(null);
    const editorRef = useRef<HTMLDivElement>(null);
    const editingRef = useRef(false);
    const [editing, setEditing] = useState(false);
    const [display, setDisplay] = useState('');
    const isOpen = !rowMode || (editing && !readOnly);

    const open = () => {
        if (editingRef.current) {
            return;
        }

        editingRef.current = true;
        setEditing(true);
        setOpenCount((count) => count + 1);
    };

    useEffect(() => {
        const root = rootRef.current;

        if (!root) {
            return;
        }

        const onOpen = () => flushSync(open);
        const onClose = () => {
            editingRef.current = false;
            setEditing(false);
        };

        root.addEventListener(OPEN_EVENT, onOpen);
        window.addEventListener(CLOSE_ALL_EVENT, onClose);

        return () => {
            root.removeEventListener(OPEN_EVENT, onOpen);
            window.removeEventListener(CLOSE_ALL_EVENT, onClose);
        };
    });

    // Re-reads whenever the closed row re-renders, and on DOM changes inside
    // the hidden editors (Radix selects fill their label asynchronously).
    useEffect(() => {
        const editor = editorRef.current;

        if (isOpen || !editor) {
            return;
        }

        const sync = () => {
            const next = valueOverride ?? readDisplay(editor);

            setDisplay((previous) => (previous === next ? previous : next));
        };
        const observer = new MutationObserver(sync);

        sync();
        observer.observe(editor, {
            subtree: true,
            childList: true,
            characterData: true,
            attributes: true,
            attributeFilter: ['data-placeholder', 'value'],
        });

        return () => observer.disconnect();
    }, [isOpen, valueOverride]);

    return (
        <div
            ref={rootRef}
            data-inline-row=""
            className="border-t border-border py-4 first:border-t-0 first:pt-0 last:pb-0"
        >
            <div className="grid gap-x-6 gap-y-2 sm:grid-cols-[minmax(130px,200px)_minmax(0,1fr)_auto] sm:items-start">
                <p className="pt-2 text-sm font-semibold text-muted-foreground">
                    {label}
                </p>
                <div className="min-w-0">
                    {isOpen ? null : (
                        <p className="min-h-9 pt-2 text-sm break-words whitespace-pre-line">
                            {display || (
                                <span className="text-muted-foreground">
                                    &mdash;
                                </span>
                            )}
                        </p>
                    )}
                    <div
                        ref={editorRef}
                        hidden={!isOpen}
                        className={group ? undefined : '[&_label]:sr-only'}
                    >
                        {children}
                    </div>
                    {rowMode && editing && !readOnly ? (
                        <div className="mt-4 flex flex-wrap gap-2">
                            <Button
                                type="button"
                                size="sm"
                                disabled={processing}
                                onClick={() =>
                                    editorRef.current
                                        ?.closest('form')
                                        ?.requestSubmit()
                                }
                            >
                                {processing ? (
                                    <>
                                        <Loader2 className="size-4 animate-spin" />
                                        Saving...
                                    </>
                                ) : (
                                    'Save'
                                )}
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={processing}
                                onClick={onDiscard}
                            >
                                Cancel
                            </Button>
                        </div>
                    ) : null}
                </div>
                <div className="sm:justify-self-end">
                    {rowMode && !editing && !readOnly ? (
                        <RowEditButton
                            disabled={anyOpen}
                            aria-label={`Edit ${label}`}
                            onClick={open}
                        >
                            Edit
                        </RowEditButton>
                    ) : null}
                </div>
            </div>
        </div>
    );
}
