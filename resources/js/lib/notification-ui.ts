import type { LucideIcon } from 'lucide-react';
import {
    Ban,
    BadgeCheck,
    FileText,
    PencilLine,
    Settings2,
    Shield,
    ShieldAlert,
    ShieldCheck,
    UserCog,
    XCircle,
} from 'lucide-react';
import { formatCurrency, formatDateTime, formatDisplayText } from '@/lib/formatters';
import { show as showAdminLoanRequest } from '@/routes/admin/requests';
import { show as showClientLoanRequest } from '@/routes/client/loan-requests';
import type { NotificationPayload } from '@/types/notifications';

const MAX_BADGE_COUNT = 99;
const MAX_METADATA_CHIPS = 6;
const relativeTimeFormatter = new Intl.RelativeTimeFormat('en', {
    numeric: 'auto',
});
const conciseDateFormatter = new Intl.DateTimeFormat('en-PH', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
});
const conciseDateWithYearFormatter = new Intl.DateTimeFormat('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
});

type LoanRequestIdentifier = string | number;

export type NotificationChipTone = 'neutral' | 'accent' | 'success' | 'danger';

export type NotificationChip = {
    label: string;
    tone?: NotificationChipTone;
};

export type NotificationVisual = {
    Icon: LucideIcon;
    iconLabel: string;
    className: string;
};

export type NotificationTimestamp = {
    dateTime: string;
    label: string;
    title: string;
};

const LOAN_NOTIFICATION_TYPES = new Set([
    'loan_request_submitted',
    'loan_request_updated',
    'loan_request_cancelled',
    'loan_request_decision',
    'loan_request_corrected_created',
]);

const ACCOUNT_ACCESS_NOTIFICATION_TYPES = new Set([
    'member_status_changed',
    'member_status_audit',
    'admin_access_changed',
    'admin_access_audit',
    'organization_settings_updated',
]);

const formatFieldLabel = (field: string): string =>
    field
        .split('_')
        .filter((segment) => segment.length > 0)
        .map((segment) => segment[0].toUpperCase() + segment.slice(1))
        .join(' ');

const formatStatusLabel = (status?: string | null): string | null => {
    if (!status) {
        return null;
    }

    if (status === 'under_review') {
        return 'Under review';
    }

    return formatFieldLabel(status);
};

const normalizeSearchText = (value?: string | null): string =>
    (value ?? '')
        .toLowerCase()
        .replace(/[^a-z0-9@.]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

const messageMentionsValue = (
    message?: string | null,
    value?: string | null,
): boolean => {
    const normalizedMessage = normalizeSearchText(message);
    const normalizedValue = normalizeSearchText(value);

    if (normalizedMessage === '' || normalizedValue === '') {
        return false;
    }

    return normalizedMessage.includes(normalizedValue);
};

const toLoanRequestIdentifier = (
    value?: NotificationPayload['loan_request_id'],
): LoanRequestIdentifier | null => {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    if (typeof value === 'string') {
        const normalizedValue = value.trim();

        return normalizedValue !== '' ? normalizedValue : null;
    }

    return null;
};

const parseRequestedAmount = (
    value?: NotificationPayload['requested_amount'],
): number | null => {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    if (typeof value === 'string') {
        const parsedValue = Number(value.replace(/,/g, '').trim());

        return Number.isFinite(parsedValue) ? parsedValue : null;
    }

    return null;
};

const formatRequestedAmountChip = (
    value?: NotificationPayload['requested_amount'],
): string | null => {
    const requestedAmount = parseRequestedAmount(value);

    if (requestedAmount === null) {
        return null;
    }

    return `Amount: ${formatCurrency(requestedAmount)}`;
};

const resolveChipTone = (
    status?: string | null,
): NotificationChipTone | undefined => {
    if (status === 'approved' || status === 'active' || status === 'granted') {
        return 'success';
    }

    if (
        status === 'declined' ||
        status === 'suspended' ||
        status === 'revoked' ||
        status === 'cancelled'
    ) {
        return 'danger';
    }

    if (status === 'under_review' || status === 'updated') {
        return 'accent';
    }

    return undefined;
};

const pushChip = (
    chips: NotificationChip[],
    seenLabels: Set<string>,
    label?: string | null,
    tone?: NotificationChipTone,
) => {
    const normalizedLabel = label?.trim();

    if (!normalizedLabel || seenLabels.has(normalizedLabel)) {
        return;
    }

    chips.push({ label: normalizedLabel, tone });
    seenLabels.add(normalizedLabel);
};

export const chipClassNames: Record<NotificationChipTone, string> = {
    neutral:
        'border-border/50 bg-muted/30 text-muted-foreground hover:bg-muted/40',
    accent: 'border-sky-500/15 bg-sky-500/8 text-sky-700 dark:text-sky-200',
    success:
        'border-emerald-500/15 bg-emerald-500/8 text-emerald-700 dark:text-emerald-200',
    danger: 'border-rose-500/15 bg-rose-500/8 text-rose-700 dark:text-rose-200',
};

export const formatNotificationBadgeCount = (count: number): string =>
    count > MAX_BADGE_COUNT ? `${MAX_BADGE_COUNT}+` : `${count}`;

export const isLoanRequestNotification = (
    payload: NotificationPayload,
): boolean =>
    payload.entity_type === 'loan_request' ||
    LOAN_NOTIFICATION_TYPES.has(payload.type);

export const isAccountAccessNotification = (
    payload: NotificationPayload,
): boolean =>
    ACCOUNT_ACCESS_NOTIFICATION_TYPES.has(payload.type);

export const resolveNotificationDestination = (
    payload: NotificationPayload,
): string | null => {
    const loanRequestId = toLoanRequestIdentifier(payload.loan_request_id);
    const correctedLoanRequestId = toLoanRequestIdentifier(
        payload.corrected_loan_request_id,
    );

    if (loanRequestId === null) {
        return null;
    }

    if (payload.type === 'loan_request_submitted') {
        return showAdminLoanRequest(loanRequestId).url;
    }

    if (
        payload.type === 'loan_request_updated' ||
        payload.type === 'loan_request_cancelled' ||
        payload.type === 'loan_request_decision'
    ) {
        return showClientLoanRequest(loanRequestId).url;
    }

    if (payload.type === 'loan_request_corrected_created') {
        if (correctedLoanRequestId !== null) {
            return showClientLoanRequest(correctedLoanRequestId).url;
        }

        return showClientLoanRequest(loanRequestId).url;
    }

    return null;
};

export const formatNotificationTimestamp = (
    value?: string | null,
): NotificationTimestamp | null => {
    if (!value) {
        return null;
    }

    const timestamp = new Date(value);

    if (Number.isNaN(timestamp.getTime())) {
        return null;
    }

    const absoluteLabel = formatDateTime(value);
    const now = Date.now();
    const secondsDelta = Math.round((timestamp.getTime() - now) / 1000);
    const absoluteSeconds = Math.abs(secondsDelta);

    if (absoluteSeconds < 60) {
        return {
            dateTime: timestamp.toISOString(),
            label: 'Just now',
            title: absoluteLabel,
        };
    }

    const relativeUnits: Array<[Intl.RelativeTimeFormatUnit, number]> = [
        ['minute', 60],
        ['hour', 60 * 60],
        ['day', 60 * 60 * 24],
    ];

    for (const [unit, threshold] of relativeUnits) {
        if (
            absoluteSeconds < threshold * (unit === 'day' ? 7 : 1) ||
            unit === 'day'
        ) {
            const divisor =
                unit === 'minute'
                    ? 60
                    : unit === 'hour'
                      ? 60 * 60
                      : 60 * 60 * 24;
            const roundedValue = Math.round(secondsDelta / divisor);

            if (unit !== 'day' || Math.abs(roundedValue) < 7) {
                return {
                    dateTime: timestamp.toISOString(),
                    label: relativeTimeFormatter.format(roundedValue, unit),
                    title: absoluteLabel,
                };
            }
        }
    }

    const formatter =
        timestamp.getFullYear() === new Date(now).getFullYear()
            ? conciseDateFormatter
            : conciseDateWithYearFormatter;

    return {
        dateTime: timestamp.toISOString(),
        label: formatter.format(timestamp),
        title: absoluteLabel,
    };
};

export const getNotificationVisual = (
    payload: NotificationPayload,
): NotificationVisual => {
    if (payload.type === 'loan_request_submitted') {
        return {
            Icon: FileText,
            iconLabel: 'Loan request submitted',
            className:
                'bg-sky-500/10 text-sky-700 ring-sky-500/15 dark:bg-sky-500/15 dark:text-sky-200',
        };
    }

    if (payload.type === 'loan_request_updated') {
        return {
            Icon: PencilLine,
            iconLabel: 'Loan request updated',
            className:
                'bg-sky-500/10 text-sky-700 ring-sky-500/15 dark:bg-sky-500/15 dark:text-sky-200',
        };
    }

    if (payload.type === 'loan_request_cancelled') {
        return {
            Icon: Ban,
            iconLabel: 'Loan request cancelled',
            className:
                'bg-rose-500/10 text-rose-700 ring-rose-500/15 dark:bg-rose-500/15 dark:text-rose-200',
        };
    }

    if (payload.type === 'loan_request_corrected_created') {
        return {
            Icon: PencilLine,
            iconLabel: 'Corrected loan request created',
            className:
                'bg-amber-500/10 text-amber-700 ring-amber-500/15 dark:bg-amber-500/15 dark:text-amber-200',
        };
    }

    if (
        payload.type === 'loan_request_decision' &&
        payload.status === 'approved'
    ) {
        return {
            Icon: BadgeCheck,
            iconLabel: 'Loan request approved',
            className:
                'bg-emerald-500/10 text-emerald-700 ring-emerald-500/15 dark:bg-emerald-500/15 dark:text-emerald-200',
        };
    }

    if (
        payload.type === 'loan_request_decision' &&
        payload.status === 'declined'
    ) {
        return {
            Icon: XCircle,
            iconLabel: 'Loan request declined',
            className:
                'bg-rose-500/10 text-rose-700 ring-rose-500/15 dark:bg-rose-500/15 dark:text-rose-200',
        };
    }

    if (
        payload.type === 'member_status_changed' ||
        payload.type === 'member_status_audit'
    ) {
        return {
            Icon: payload.status === 'active' ? ShieldCheck : ShieldAlert,
            iconLabel: 'Member status update',
            className:
                payload.status === 'active'
                    ? 'bg-emerald-500/10 text-emerald-700 ring-emerald-500/15 dark:bg-emerald-500/15 dark:text-emerald-200'
                    : 'bg-amber-500/10 text-amber-700 ring-amber-500/15 dark:bg-amber-500/15 dark:text-amber-200',
        };
    }

    if (
        payload.type === 'admin_access_changed' ||
        payload.type === 'admin_access_audit'
    ) {
        return {
            Icon: UserCog,
            iconLabel: 'Admin access update',
            className:
                'bg-indigo-500/10 text-indigo-700 ring-indigo-500/15 dark:bg-indigo-500/15 dark:text-indigo-200',
        };
    }

    if (payload.type === 'organization_settings_updated') {
        return {
            Icon: Settings2,
            iconLabel: 'Settings updated',
            className:
                'bg-amber-500/10 text-amber-700 ring-amber-500/15 dark:bg-amber-500/15 dark:text-amber-200',
        };
    }

    return {
        Icon: Shield,
        iconLabel: 'Notification',
        className:
            'bg-slate-500/10 text-slate-700 ring-slate-500/15 dark:bg-slate-500/15 dark:text-slate-200',
    };
};

export const buildNotificationMetadataChips = (
    payload: NotificationPayload,
): NotificationChip[] => {
    const chips: NotificationChip[] = [];
    const seenLabels = new Set<string>();
    const statusLabel = formatStatusLabel(payload.status);
    const changedFields = payload.changed_fields ?? [];
    const memberName = formatDisplayText(payload.member_name);
    const actorName = formatDisplayText(payload.actor_name);
    const actorLabel =
        actorName !== '' &&
        actorName.toLowerCase() !== memberName.toLowerCase()
            ? `By ${actorName}`
            : null;
    const memberLabelIsInMessage = messageMentionsValue(
        payload.message,
        memberName,
    );
    const actorLabelIsInMessage = messageMentionsValue(payload.message, actorName);
    const requestedAmountChip = formatRequestedAmountChip(payload.requested_amount);
    const isLoanNotification = isLoanRequestNotification(payload);

    pushChip(chips, seenLabels, statusLabel, resolveChipTone(payload.status));
    pushChip(
        chips,
        seenLabels,
        payload.reference ? `Ref: ${payload.reference}` : null,
    );

    if (isLoanNotification) {
        if (memberName !== '' && !memberLabelIsInMessage) {
            pushChip(chips, seenLabels, memberName);
        } else {
            pushChip(
                chips,
                seenLabels,
                payload.member_acctno ? `Acct: ${payload.member_acctno}` : null,
            );
        }

        pushChip(chips, seenLabels, requestedAmountChip);
        pushChip(
            chips,
            seenLabels,
            payload.loan_type_label ?? payload.loan_type_code ?? null,
        );

        if (!actorLabelIsInMessage) {
            pushChip(chips, seenLabels, actorLabel);
        }
    } else {
        if (memberName !== '' && !memberLabelIsInMessage) {
            pushChip(chips, seenLabels, memberName);
        }

        pushChip(
            chips,
            seenLabels,
            payload.member_acctno ? `Acct: ${payload.member_acctno}` : null,
        );

        if (!actorLabelIsInMessage) {
            pushChip(chips, seenLabels, actorLabel);
        }

        pushChip(
            chips,
            seenLabels,
            payload.loan_type_label ?? payload.loan_type_code ?? null,
        );
    }

    changedFields.slice(0, 2).forEach((field) => {
        pushChip(chips, seenLabels, formatFieldLabel(field), 'neutral');
    });

    if (changedFields.length > 2) {
        pushChip(
            chips,
            seenLabels,
            `+${changedFields.length - 2} more fields`,
            'neutral',
        );
    }

    return chips.slice(0, MAX_METADATA_CHIPS);
};
