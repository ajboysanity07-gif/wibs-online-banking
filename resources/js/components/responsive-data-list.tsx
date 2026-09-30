import type { RowData } from '@tanstack/react-table';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type ResponsiveColumnPriority =
    | 'title'
    | 'amount'
    | 'badge'
    | 'detail'
    | 'action'
    | 'hidden';

declare module '@tanstack/react-table' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars -- must mirror the library's generics
    interface ColumnMeta<TData extends RowData, TValue> {
        /** Role of the column in the mobile list. Defaults to "detail". */
        priority?: ResponsiveColumnPriority;
        /** Mobile label when `header` is not a plain string. */
        label?: string;
        /** Text the mobile avatar initials are taken from ("title" column). */
        text?: (row: TData) => string;
    }
}

export type ResponsiveDataListColumn = {
    key: string;
    label: string;
    align?: 'left' | 'right';
    priority?: ResponsiveColumnPriority;
};

export type ResponsiveDataListRow = {
    id: string;
    /** Text the avatar initials are taken from. */
    initials?: string;
    cells: Record<string, ReactNode>;
};

type Props = {
    columns: ResponsiveDataListColumn[];
    rows: ResponsiveDataListRow[];
    renderAction?: (row: ResponsiveDataListRow) => ReactNode;
    emptyMessage?: string;
    /** The existing desktop table, shown from `md:` up unchanged. */
    children: ReactNode;
    className?: string;
};

const initialsOf = (text?: string): string => {
    const words = (text ?? '').trim().split(/\s+/).filter(Boolean);

    if (words.length === 0) {
        return '?';
    }

    return (words[0][0] + (words.length > 1 ? words[1][0] : '')).toUpperCase();
};

const isEmpty = (node: ReactNode): boolean =>
    node === null || node === undefined || node === false || node === '';

export function ResponsiveDataList({
    columns,
    rows,
    renderAction,
    emptyMessage = 'No results.',
    children,
    className,
}: Props) {
    const [openId, setOpenId] = useState<string | null>(null);
    const byPriority = (priority: ResponsiveColumnPriority) =>
        columns.filter((column) => (column.priority ?? 'detail') === priority);
    const title = byPriority('title')[0];
    const amount = byPriority('amount')[0];
    const badge = byPriority('badge')[0];
    const details = byPriority('detail');
    const action = byPriority('action')[0];
    const subtitleColumns = details.slice(0, 2);
    const expandedColumns = details.slice(2);

    return (
        <div className={className}>
            <div className="hidden md:block">{children}</div>
            <ul className="divide-y divide-border md:hidden">
                {rows.length === 0 ? (
                    <li className="p-6 text-center text-sm text-muted-foreground">
                        {emptyMessage}
                    </li>
                ) : (
                    rows.map((row) => {
                        const isOpen = openId === row.id;
                        const subtitle = subtitleColumns
                            .map((column) => row.cells[column.key])
                            .filter((cell) => !isEmpty(cell));
                        const expanded = expandedColumns.filter(
                            (column) => !isEmpty(row.cells[column.key]),
                        );
                        const actionCell = renderAction
                            ? renderAction(row)
                            : action
                              ? row.cells[action.key]
                              : null;

                        return (
                            <li key={row.id}>
                                <button
                                    type="button"
                                    aria-expanded={isOpen}
                                    onClick={() =>
                                        setOpenId(isOpen ? null : row.id)
                                    }
                                    className="flex min-h-14 w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset"
                                >
                                    <span
                                        aria-hidden="true"
                                        className="flex size-10 shrink-0 items-center justify-center rounded-full bg-secondary text-sm font-semibold text-secondary-foreground"
                                    >
                                        {initialsOf(row.initials)}
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-[15px] font-semibold">
                                            {title ? row.cells[title.key] : null}
                                        </span>
                                        {subtitle.length > 0 ? (
                                            <span className="mt-0.5 flex min-w-0 gap-1 truncate text-[13px] text-muted-foreground">
                                                {subtitle.map((cell, index) => (
                                                    <span
                                                        key={index}
                                                        className="truncate"
                                                    >
                                                        {index > 0 ? ' · ' : ''}
                                                        {cell}
                                                    </span>
                                                ))}
                                            </span>
                                        ) : null}
                                    </span>
                                    <span className="flex shrink-0 flex-col items-end gap-1">
                                        {amount ? (
                                            <span className="font-bold tabular-nums">
                                                {row.cells[amount.key]}
                                            </span>
                                        ) : null}
                                        {badge ? (
                                            <span>{row.cells[badge.key]}</span>
                                        ) : null}
                                    </span>
                                    <ChevronRight
                                        aria-hidden="true"
                                        className={cn(
                                            'size-4 shrink-0 text-muted-foreground transition-transform duration-200 motion-reduce:transition-none',
                                            isOpen && 'rotate-90',
                                        )}
                                    />
                                </button>
                                <div
                                    className={cn(
                                        'grid transition-[grid-template-rows] duration-200 ease-out motion-reduce:transition-none',
                                        isOpen
                                            ? 'grid-rows-[1fr]'
                                            : 'grid-rows-[0fr]',
                                    )}
                                    inert={!isOpen}
                                >
                                    <div className="overflow-hidden">
                                        <div className="space-y-3 pr-4 pb-4 pl-[68px]">
                                            {expanded.length > 0 ? (
                                                <dl className="grid grid-cols-2 gap-x-4 gap-y-3">
                                                    {expanded.map((column) => (
                                                        <div
                                                            key={column.key}
                                                            className="min-w-0"
                                                        >
                                                            <dt className="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                                                                {column.label}
                                                            </dt>
                                                            <dd className="text-sm font-semibold break-words">
                                                                {
                                                                    row.cells[
                                                                        column
                                                                            .key
                                                                    ]
                                                                }
                                                            </dd>
                                                        </div>
                                                    ))}
                                                </dl>
                                            ) : null}
                                            {!isEmpty(actionCell) ? (
                                                <div className="[&_a]:w-full [&_button]:w-full [&>*]:w-full">
                                                    {actionCell}
                                                </div>
                                            ) : null}
                                        </div>
                                    </div>
                                </div>
                            </li>
                        );
                    })
                )}
            </ul>
        </div>
    );
}
