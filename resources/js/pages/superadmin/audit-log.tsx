import { Head } from '@inertiajs/react';
import axios from 'axios';
import { Download, Filter } from 'lucide-react';
import {
    type FormEvent,
    useCallback,
    useEffect,
    useRef,
    useState,
} from 'react';
import { PageHero } from '@/components/page-hero';
import { PageShell } from '@/components/page-shell';
import { SurfaceCard } from '@/components/surface-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { showErrorToast } from '@/lib/toast';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Audit Log', href: '/superadmin/audit-log' },
];

type AuditEntry = {
    type: 'role_change' | 'loan_change' | 'document_access';
    id: number;
    action: string;
    actor: string | null;
    target_user?: string | null;
    loan_reference?: string | null;
    document_key?: string | null;
    from_status?: string | null;
    to_status?: string | null;
    detail?: string | null;
    reason?: string | null;
    occurred_at: string | null;
};

type Meta = {
    type: string;
    page: number;
    per_page: number;
    total: number;
    last_page: number;
};

type Filters = {
    type: string;
    user_id: string;
    loan_reference: string;
    date_from: string;
    date_to: string;
};

const TYPE_LABELS: Record<string, string> = {
    all: 'All Events',
    role_changes: 'Role Changes',
    loan_changes: 'Loan Workflow',
    document_access: 'Document Access',
};

const ACTION_BADGE_VARIANT: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    download: 'default',
    view: 'secondary',
    reject: 'destructive',
    approve: 'default',
};

export default function AuditLog() {
    const [filters, setFilters] = useState<Filters>({
        type: 'all',
        user_id: '',
        loan_reference: '',
        date_from: '',
        date_to: '',
    });
    const [page, setPage] = useState(1);
    const [items, setItems] = useState<AuditEntry[]>([]);
    const [meta, setMeta] = useState<Meta | null>(null);
    const [loading, setLoading] = useState(false);

    const fetchAuditLog = useCallback(
        async (currentFilters: Filters, currentPage: number) => {
            setLoading(true);
            try {
                const params: Record<string, string> = {
                    page: String(currentPage),
                };
                if (currentFilters.type && currentFilters.type !== 'all')
                    params.type = currentFilters.type;
                if (currentFilters.user_id)
                    params.user_id = currentFilters.user_id;
                if (currentFilters.loan_reference)
                    params.loan_reference = currentFilters.loan_reference;
                if (currentFilters.date_from)
                    params.date_from = currentFilters.date_from;
                if (currentFilters.date_to)
                    params.date_to = currentFilters.date_to;

                const response = await axios.get('/spa/superadmin/audit-log', {
                    params,
                });
                setItems(response.data.data.items);
                setMeta(response.data.data.meta);
            } catch {
                showErrorToast(null, 'Failed to load audit log.');
            } finally {
                setLoading(false);
            }
        },
        [],
    );

    const previousTextFilters = useRef({
        user_id: filters.user_id,
        loan_reference: filters.loan_reference,
    });

    useEffect(() => {
        const textFiltersChanged =
            filters.user_id !== previousTextFilters.current.user_id ||
            filters.loan_reference !==
                previousTextFilters.current.loan_reference;
        previousTextFilters.current = {
            user_id: filters.user_id,
            loan_reference: filters.loan_reference,
        };

        const timer = setTimeout(
            () => {
                void fetchAuditLog(filters, page);
            },
            textFiltersChanged ? 300 : 0,
        );

        return () => {
            clearTimeout(timer);
        };
    }, [fetchAuditLog, filters, page]);

    const handleFilterSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        setPage(1);
        void fetchAuditLog(filters, 1);
    };

    const handleExport = async () => {
        window.location.href = '/spa/superadmin/audit-log/export';
    };

    const describeEntry = (entry: AuditEntry): string => {
        if (entry.type === 'role_change') {
            return `${entry.action} → ${entry.target_user ?? '—'}`;
        }
        if (entry.type === 'loan_change') {
            return entry.loan_reference ?? '—';
        }
        return `${entry.document_key ?? '—'} (${entry.loan_reference ?? '—'})`;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Audit Log" />
            <PageShell>
                <PageHero
                    title="Audit Log"
                    description="System-wide record of role changes, loan workflow actions, and document access."
                    rightSlot={
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleExport}
                        >
                            <Download className="mr-2 h-4 w-4" />
                            Export
                        </Button>
                    }
                />

                <SurfaceCard>
                    <form
                        onSubmit={handleFilterSubmit}
                        className="flex flex-wrap gap-3 p-4"
                    >
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="filter-type">Event type</Label>
                            <Select
                                value={filters.type}
                                onValueChange={(v) =>
                                    setFilters((f) => ({ ...f, type: v }))
                                }
                            >
                                <SelectTrigger
                                    id="filter-type"
                                    className="w-44"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(TYPE_LABELS).map(
                                        ([value, label]) => (
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="flex flex-col gap-1">
                            <Label htmlFor="filter-loan">Loan reference</Label>
                            <Input
                                id="filter-loan"
                                placeholder="LNREQ-000001"
                                className="w-40"
                                value={filters.loan_reference}
                                onChange={(e) =>
                                    setFilters((f) => ({
                                        ...f,
                                        loan_reference: e.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="flex flex-col gap-1">
                            <Label htmlFor="filter-from">From date</Label>
                            <Input
                                id="filter-from"
                                type="date"
                                className="w-36"
                                value={filters.date_from}
                                onChange={(e) =>
                                    setFilters((f) => ({
                                        ...f,
                                        date_from: e.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="flex flex-col gap-1">
                            <Label htmlFor="filter-to">To date</Label>
                            <Input
                                id="filter-to"
                                type="date"
                                className="w-36"
                                value={filters.date_to}
                                onChange={(e) =>
                                    setFilters((f) => ({
                                        ...f,
                                        date_to: e.target.value,
                                    }))
                                }
                            />
                        </div>

                        <div className="flex items-end">
                            <Button type="submit" size="sm" disabled={loading}>
                                <Filter className="mr-2 h-4 w-4" />
                                Filter
                            </Button>
                        </div>
                    </form>

                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Type</TableHead>
                                <TableHead>Action</TableHead>
                                <TableHead>Actor</TableHead>
                                <TableHead>Target / Document</TableHead>
                                <TableHead>Reason</TableHead>
                                <TableHead>Occurred At</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {loading && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="text-center text-muted-foreground"
                                    >
                                        Loading…
                                    </TableCell>
                                </TableRow>
                            )}
                            {!loading && items.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="text-center text-muted-foreground"
                                    >
                                        No audit records found.
                                    </TableCell>
                                </TableRow>
                            )}
                            {!loading &&
                                items.map((entry) => (
                                    <TableRow key={`${entry.type}-${entry.id}`}>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {TYPE_LABELS[entry.type] ??
                                                    entry.type}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    ACTION_BADGE_VARIANT[
                                                        entry.action
                                                    ] ?? 'secondary'
                                                }
                                            >
                                                {entry.action}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {entry.actor ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            {describeEntry(entry)}
                                        </TableCell>
                                        <TableCell className="max-w-xs truncate text-muted-foreground">
                                            {entry.reason ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-sm whitespace-nowrap text-muted-foreground">
                                            {entry.occurred_at
                                                ? new Date(
                                                      entry.occurred_at,
                                                  ).toLocaleString()
                                                : '—'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                        </TableBody>
                    </Table>

                    {meta && meta.last_page > 1 && (
                        <div className="flex items-center justify-between px-4 py-3">
                            <span className="text-sm text-muted-foreground">
                                Page {meta.page} of {meta.last_page} (
                                {meta.total} records)
                            </span>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={page <= 1}
                                    onClick={() =>
                                        setPage((p) => Math.max(1, p - 1))
                                    }
                                >
                                    Previous
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={page >= meta.last_page}
                                    onClick={() => setPage((p) => p + 1)}
                                >
                                    Next
                                </Button>
                            </div>
                        </div>
                    )}
                </SurfaceCard>
            </PageShell>
        </AppLayout>
    );
}
