import type { ReactNode } from 'react';
import { Link2, RefreshCcw } from 'lucide-react';
import { NumericFormat } from 'react-number-format';
import InputError from '@/components/input-error';
import {
    LoanRequestPersonalFields,
    LoanRequestWorkFields,
} from '@/components/loan-request/loan-request-fields';
import { LoanRequestSectionCard } from '@/components/loan-request/loan-request-section-card';
import SignaturePadField from '@/components/signature-pad-field';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import {
    composeAddress,
    composeBirthplace,
    formatCurrency,
    formatDateTime,
    formatDisplayText,
} from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type {
    LoanRequestCoMakerSignatureState,
    LoanRequestFormData,
    LoanRequestGeneratedSignatureLink,
    LoanRequestMemberSummary,
    LoanRequestPersonFormData,
    LoanRequestReadOnlyMap,
    LoanTypeOption,
} from '@/types/loan-requests';

const AVAILMENT_OPTIONS = ['New', 'Re-Loan', 'Restructured'] as const;

type SignatureRole = 'co_maker_1' | 'co_maker_2';
type SignatureMethod = 'in_person' | 'share_link';

type LoanDetailField =
    | 'typecode'
    | 'requested_amount'
    | 'requested_term'
    | 'loan_purpose'
    | 'availment_status';

type LoanDetailsProps = {
    data: LoanRequestFormData;
    errors: Record<string, string | undefined>;
    loanTypes: LoanTypeOption[];
    onChange: (field: LoanDetailField, value: string) => void;
};

export function LoanRequestLoanDetailsStep({
    data,
    errors,
    loanTypes,
    onChange,
}: LoanDetailsProps) {
    return (
        <LoanRequestSectionCard
            title="Loan details"
            description="Select your preferred loan type and request details."
        >
            <div className="grid gap-4 md:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="loan_type">Loan type</Label>
                    <Select
                        value={data.typecode || undefined}
                        onValueChange={(value) => onChange('typecode', value)}
                    >
                        <SelectTrigger id="loan_type" className="mt-1 w-full">
                            <SelectValue placeholder="Select loan type" />
                        </SelectTrigger>
                        <SelectContent>
                            {loanTypes.map((option) => (
                                <SelectItem
                                    key={option.typecode}
                                    value={option.typecode}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.typecode} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="requested_amount">Requested amount</Label>
                    <div className="relative">
                        <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-muted-foreground">
                            PHP
                        </span>
                        <NumericFormat
                            id="requested_amount"
                            className="mt-1 block w-full pl-12"
                            value={data.requested_amount}
                            onValueChange={(values) => {
                                onChange('requested_amount', values.value);
                            }}
                            thousandSeparator
                            decimalScale={2}
                            fixedDecimalScale
                            allowNegative={false}
                            placeholder="0.00"
                            inputMode="decimal"
                            valueIsNumericString
                            customInput={Input}
                        />
                    </div>
                    <InputError message={errors.requested_amount} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="requested_term">Loan term (months)</Label>
                    <Input
                        id="requested_term"
                        type="number"
                        value={data.requested_term}
                        className="mt-1 block w-full"
                        placeholder="e.g. 12"
                        required
                        onChange={(event) =>
                            onChange('requested_term', event.target.value)
                        }
                    />
                    <InputError message={errors.requested_term} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="availment_status">Availment status</Label>
                    <Select
                        value={data.availment_status || undefined}
                        onValueChange={(value) =>
                            onChange('availment_status', value)
                        }
                    >
                        <SelectTrigger
                            id="availment_status"
                            className="mt-1 w-full"
                        >
                            <SelectValue placeholder="Select status" />
                        </SelectTrigger>
                        <SelectContent>
                            {AVAILMENT_OPTIONS.map((option) => (
                                <SelectItem key={option} value={option}>
                                    {option}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.availment_status} />
                </div>

                <div className="grid gap-2 md:col-span-2">
                    <Label htmlFor="loan_purpose">Loan purpose</Label>
                    <Input
                        id="loan_purpose"
                        value={data.loan_purpose}
                        className="mt-1 block w-full"
                        placeholder="Describe your loan purpose"
                        required
                        onChange={(event) =>
                            onChange('loan_purpose', event.target.value)
                        }
                    />
                    <InputError message={errors.loan_purpose} />
                </div>
            </div>
        </LoanRequestSectionCard>
    );
}

type PersonStepProps = {
    values: LoanRequestPersonFormData;
    errors: Record<string, string | undefined>;
    readOnly?: LoanRequestReadOnlyMap | null;
    onChange: (field: keyof LoanRequestPersonFormData, value: string) => void;
    signatureData?: string;
    onSignatureChange?: (value: string) => void;
};

export function LoanRequestApplicantPersonalStep({
    values,
    errors,
    readOnly,
    onChange,
}: PersonStepProps) {
    return (
        <LoanRequestSectionCard
            title="My personal data"
            description="Confirm your personal details."
        >
            <LoanRequestPersonalFields
                prefix="applicant"
                values={values}
                errors={errors}
                readOnly={readOnly}
                includeSpouse
                includeChildren
                onChange={onChange}
            />
        </LoanRequestSectionCard>
    );
}

export function LoanRequestApplicantWorkStep({
    values,
    errors,
    onChange,
    signatureData = '',
    onSignatureChange,
}: PersonStepProps) {
    return (
        <LoanRequestSectionCard
            title="My work & finances"
            description="Share your current employment and income details."
        >
            <LoanRequestWorkFields
                prefix="applicant"
                values={values}
                errors={errors}
                onChange={onChange}
            />
            <Separator className="bg-border/40" />
            <SignaturePadField
                name="applicant_signature_data"
                label="Member / Applicant Signature"
                value={signatureData}
                error={errors.applicant_signature_data}
                onChange={(nextValue) => onSignatureChange?.(nextValue)}
            />
        </LoanRequestSectionCard>
    );
}

type CoMakerStepProps = {
    title: string;
    description: string;
    prefix: string;
    values: LoanRequestPersonFormData;
    errors: Record<string, string | undefined>;
    onChange: (field: keyof LoanRequestPersonFormData, value: string) => void;
    signatureState: LoanRequestCoMakerSignatureState;
    isSignatureRequired: boolean;
    isLocked: boolean;
    selectedSigningMethod?: SignatureMethod;
    generatedLink?: LoanRequestGeneratedSignatureLink;
    isGeneratingSignatureLink: boolean;
    signatureData: string;
    signatureError?: string;
    signatureDataError?: string;
    onSelectSigningMethod: (method: SignatureMethod) => void;
    onSignatureChange: (value: string) => void;
    onEnableSignedEditing: () => void;
    onGenerateSignatureLink: () => void;
    onCopySignatureLink: () => void;
};

export function LoanRequestCoMakerStep({
    title,
    description,
    prefix,
    values,
    errors,
    onChange,
    signatureState,
    isSignatureRequired,
    isLocked,
    selectedSigningMethod,
    generatedLink,
    isGeneratingSignatureLink,
    signatureData,
    signatureError,
    signatureDataError,
    onSelectSigningMethod,
    onSignatureChange,
    onEnableSignedEditing,
    onGenerateSignatureLink,
    onCopySignatureLink,
}: CoMakerStepProps) {
    const signatureStatus = describeCoMakerSignatureStatus(
        signatureState,
        isSignatureRequired,
    );

    return (
        <LoanRequestSectionCard title={title} description={description}>
            {isLocked ? (
                <Alert className="border-emerald-500/30 bg-emerald-500/10 text-emerald-900 dark:text-emerald-100">
                    <AlertTitle>Signed co-maker details are locked</AlertTitle>
                    <AlertDescription className="space-y-3">
                        <p>
                            This co-maker is already confirmed. Unlock the
                            proposed details only if you need to make a change
                            and require a new co-maker signature.
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            className="w-full sm:w-auto"
                            onClick={onEnableSignedEditing}
                        >
                            Edit details and require a new signature
                        </Button>
                    </AlertDescription>
                </Alert>
            ) : null}

            <fieldset
                disabled={isLocked}
                className={cn('space-y-7', isLocked && 'opacity-80')}
            >
                <LoanRequestPersonalFields
                    prefix={prefix}
                    values={values}
                    errors={errors}
                    onChange={onChange}
                />
                <Separator className="bg-border/40" />
                <LoanRequestWorkFields
                    prefix={prefix}
                    values={values}
                    errors={errors}
                    onChange={onChange}
                />
            </fieldset>
            <Separator className="bg-border/40" />
            <Alert className="border-border/50 bg-muted/10">
                <AlertTitle className="flex flex-wrap items-center gap-2">
                    <span>{title} Signature</span>
                    <Badge className={signatureStatus.badgeClassName}>
                        {signatureStatus.label}
                    </Badge>
                </AlertTitle>
                <AlertDescription className="space-y-4">
                    <p>{signatureStatus.description}</p>
                    {signatureState.expires_at ? (
                        <p>
                            Link expires {formatDateTime(signatureState.expires_at)}
                        </p>
                    ) : null}
                    {signatureState.signed_at ? (
                        <p>
                            Confirmed {formatDateTime(signatureState.signed_at)}
                        </p>
                    ) : null}
                    <CoMakerSignatureActionsContent
                        signatureState={signatureState}
                        isRequired={isSignatureRequired}
                        selectedSigningMethod={selectedSigningMethod}
                        generatedLink={generatedLink}
                        isGenerating={isGeneratingSignatureLink}
                        signatureData={signatureData}
                        error={signatureError}
                        signatureDataError={signatureDataError}
                        onSelectSigningMethod={onSelectSigningMethod}
                        onSignatureChange={onSignatureChange}
                        onGenerate={onGenerateSignatureLink}
                        onCopy={onCopySignatureLink}
                    />
                </AlertDescription>
            </Alert>
        </LoanRequestSectionCard>
    );
}

type ReviewStepProps = {
    data: LoanRequestFormData;
    loanTypes: LoanTypeOption[];
    member: LoanRequestMemberSummary;
    errors: Record<string, string | undefined>;
    onUndertakingChange: (value: boolean) => void;
    coMakerOneSignature: LoanRequestCoMakerSignatureState;
    coMakerTwoSignature: LoanRequestCoMakerSignatureState;
    coMakerOneRequired: boolean;
    coMakerTwoRequired: boolean;
    generatedLinks: Partial<
        Record<SignatureRole, LoanRequestGeneratedSignatureLink>
    >;
    coMakerOneHasPendingInPersonSignature: boolean;
    coMakerTwoHasPendingInPersonSignature: boolean;
    onGenerateSignatureLink: (role: SignatureRole) => void;
    onCopySignatureLink: (role: SignatureRole) => void;
    onRefreshSignatures: () => void;
    isGeneratingSignatureLinkRole: SignatureRole | null;
    isRefreshingSignatures: boolean;
    canSubmitForReview: boolean;
    submitDisabledMessage?: string | null;
};

type SummaryItem = {
    label: string;
    value: string;
};

const displayValue = (value: string): string =>
    value.trim() !== '' ? value : '--';

const displayText = (value?: string | null): string => {
    const normalized = formatDisplayText(value);

    return normalized !== '' ? normalized : '--';
};

const formatHousingStatus = (value: string): string => {
    const trimmed = value.trim();

    if (trimmed === '') {
        return '--';
    }

    const upper = trimmed.toUpperCase();

    if (upper === 'OWNED') {
        return 'Owned';
    }

    if (upper === 'RENT' || upper === 'RENTAL') {
        return 'Rent';
    }

    return trimmed;
};

const formatCivilStatus = (value: string): string => {
    const trimmed = value.trim();

    if (trimmed === '') {
        return '--';
    }

    const upper = trimmed.toUpperCase();

    if (upper === 'SINGLE') {
        return 'Single';
    }

    if (upper === 'MARRIED') {
        return 'Married';
    }

    if (upper === 'SEPARATED') {
        return 'Separated';
    }

    if (upper === 'WIDOWED') {
        return 'Widowed';
    }

    return trimmed;
};

const formatPayday = (value: string): string => {
    const trimmed = value.trim();

    if (trimmed === '') {
        return '--';
    }

    const upper = trimmed.toUpperCase();
    const compact = upper.replace(/[^0-9A-Z]/g, '');

    if (upper === 'WEEKLY') {
        return 'Weekly';
    }

    if (upper === 'MONTHLY') {
        return 'Monthly';
    }

    if (compact === 'BIWEEKLY') {
        return 'Bi-Weekly';
    }

    if (compact === '15') {
        return '15th';
    }

    if (compact === '30') {
        return '30th';
    }

    if (upper.includes('15') && upper.includes('30')) {
        return '15th & 30th';
    }

    return trimmed;
};

const displayName = (person: LoanRequestPersonFormData): string => {
    const name = [person.first_name, person.middle_name, person.last_name]
        .map((value) => formatDisplayText(value))
        .map((value) => value.trim())
        .filter((value) => value !== '')
        .join(' ');

    return name !== '' ? name : '--';
};

const resolveBirthplace = (person: LoanRequestPersonFormData): string =>
    composeBirthplace(person.birthplace_city, person.birthplace_province);

const resolveAddress = (person: LoanRequestPersonFormData): string =>
    composeAddress(person.address1, person.address2, person.address3);

const resolveEmployerBusinessAddress = (
    person: LoanRequestPersonFormData,
): string =>
    composeAddress(
        person.employer_business_address1,
        person.employer_business_address2,
        person.employer_business_address3,
    );

const SummaryGrid = ({ items }: { items: SummaryItem[] }) => (
    <div className="grid gap-3 sm:grid-cols-2">
        {items.map((item) => (
            <div key={item.label} className="space-y-1">
                <p className="text-xs text-muted-foreground">{item.label}</p>
                <p className="text-sm font-medium break-words">{item.value}</p>
            </div>
        ))}
    </div>
);

type SummaryCardProps = {
    title: string;
    description?: string;
    children: ReactNode;
};

const SummaryCard = ({ title, description, children }: SummaryCardProps) => (
    <div className="rounded-lg border border-border/50 bg-card/60 p-4">
        <div className="space-y-1">
            <h3 className="text-sm font-semibold">{title}</h3>
            {description ? (
                <p className="text-xs text-muted-foreground">{description}</p>
            ) : null}
        </div>
        <div className="mt-4">{children}</div>
    </div>
);

type SignatureStatusDescriptor = {
    label: string;
    description: string;
    badgeClassName: string;
};

const describeCoMakerSignatureStatus = (
    signatureState: LoanRequestCoMakerSignatureState,
    isRequired: boolean,
): SignatureStatusDescriptor => {
    if (!isRequired) {
        return {
            label: 'Proposed / Not sent',
            description:
                'No proposed co-maker details have been entered for this slot yet.',
            badgeClassName:
                'border-border/50 bg-muted/20 text-muted-foreground',
        };
    }

    if (signatureState.state === 'signed') {
        return {
            label: 'Signed / Confirmed',
            description:
                'This co-maker reviewed the proposed details, consented, and completed their signature.',
            badgeClassName:
                'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-200',
        };
    }

    if (signatureState.state === 'link_active') {
        return {
            label: 'Signing link active',
            description:
                'A secure signing link is active. The co-maker still needs to review the details, consent, and sign.',
            badgeClassName:
                'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-200',
        };
    }

    if (signatureState.state === 'expired') {
        return {
            label: 'Link expired',
            description:
                'The last secure signing link expired before the co-maker completed their signature.',
            badgeClassName:
                'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-200',
        };
    }

    return {
        label: 'Proposed / Not sent',
        description:
            'The borrower entered proposed co-maker details, but no secure signing link has been shared yet.',
        badgeClassName:
            'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-200',
    };
};

type CoMakerSignatureActionsCardProps = {
    title: string;
    description: string;
    signatureState: LoanRequestCoMakerSignatureState;
    isRequired: boolean;
    hasPendingInPersonSignature?: boolean;
    generatedLink?: LoanRequestGeneratedSignatureLink;
    isGenerating: boolean;
    error?: string;
    onGenerate: () => void;
    onCopy: () => void;
};

type CoMakerSignatureActionsContentProps = {
    signatureState: LoanRequestCoMakerSignatureState;
    isRequired: boolean;
    selectedSigningMethod?: SignatureMethod;
    generatedLink?: LoanRequestGeneratedSignatureLink;
    isGenerating: boolean;
    signatureData: string;
    error?: string;
    signatureDataError?: string;
    onSelectSigningMethod: (method: SignatureMethod) => void;
    onSignatureChange: (value: string) => void;
    onGenerate: () => void;
    onCopy: () => void;
};

const CoMakerSignatureActionsContent = ({
    signatureState,
    isRequired,
    selectedSigningMethod,
    generatedLink,
    isGenerating,
    signatureData,
    error,
    signatureDataError,
    onSelectSigningMethod,
    onSignatureChange,
    onGenerate,
    onCopy,
}: CoMakerSignatureActionsContentProps) => {
    const canShareLink = isRequired && !signatureState.is_confirmed;
    const canSignNow = isRequired && !signatureState.is_confirmed;
    const hasVisibleLink =
        generatedLink !== undefined &&
        canShareLink &&
        signatureState.state !== 'expired';
    const primaryActionLabel =
        signatureState.state === 'link_active' ||
        signatureState.state === 'expired'
            ? 'Regenerate link'
            : 'Generate signing link';

    return (
        <div className="space-y-4">
            {signatureState.is_confirmed ? (
                <p className="text-xs text-muted-foreground">
                    Editing the proposed co-maker details will invalidate the
                    current confirmation and require a new co-maker signature.
                </p>
            ) : (
                <>
                    <div className="rounded-lg border border-border/50 bg-background/70 p-4">
                        <div className="space-y-2">
                            <p className="text-sm font-medium text-foreground">
                                Choose how this co-maker will sign.
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Only the co-maker should sign. Do not sign on
                                behalf of another person.
                            </p>
                        </div>

                        <div className="mt-4 grid gap-3 lg:grid-cols-2">
                            <button
                                type="button"
                                className={cn(
                                    'rounded-xl border px-4 py-4 text-left transition-colors',
                                    selectedSigningMethod === 'in_person'
                                        ? 'border-primary bg-primary/5'
                                        : 'border-border/60 bg-muted/10 hover:border-primary/40',
                                )}
                                disabled={!canSignNow}
                                onClick={() => onSelectSigningMethod('in_person')}
                            >
                                <span className="block text-sm font-semibold text-foreground">
                                    Sign now on this device
                                </span>
                                <span className="mt-1 block text-xs text-muted-foreground">
                                    Use this when the co-maker is physically
                                    present with the member.
                                </span>
                            </button>

                            <button
                                type="button"
                                className={cn(
                                    'rounded-xl border px-4 py-4 text-left transition-colors',
                                    selectedSigningMethod === 'share_link'
                                        ? 'border-primary bg-primary/5'
                                        : 'border-border/60 bg-muted/10 hover:border-primary/40',
                                )}
                                disabled={!canShareLink}
                                onClick={() => onSelectSigningMethod('share_link')}
                            >
                                <span className="block text-sm font-semibold text-foreground">
                                    Share secure signing link
                                </span>
                                <span className="mt-1 block text-xs text-muted-foreground">
                                    Use this when the co-maker is not with the
                                    member.
                                </span>
                            </button>
                        </div>
                    </div>

                    {selectedSigningMethod === 'in_person' && canSignNow ? (
                        <div className="space-y-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4">
                            <div className="space-y-1">
                                <p className="text-sm font-semibold text-foreground">
                                    Sign now on this device
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Only the co-maker should sign here. Do not
                                    sign on behalf of another person.
                                </p>
                            </div>

                            <SignaturePadField
                                name={`${signatureState.role}_signature_data`}
                                label="Co-maker signature"
                                value={signatureData}
                                error={signatureDataError}
                                onChange={onSignatureChange}
                            />

                            <p className="text-xs text-muted-foreground">
                                Saving the draft or submitting this request will
                                save the in-person co-maker signature.
                            </p>
                        </div>
                    ) : null}

                    {selectedSigningMethod === 'share_link' && canShareLink ? (
                        <div className="space-y-4 rounded-xl border border-sky-500/20 bg-sky-500/5 p-4">
                            {hasVisibleLink ? (
                                <div className="space-y-2 rounded-lg border border-dashed border-border/60 bg-muted/10 p-3">
                                    <Label
                                        htmlFor={`${signatureState.role}_signing_link`}
                                        className="text-xs font-medium tracking-[0.14em] text-muted-foreground uppercase"
                                    >
                                        Secure signing link
                                    </Label>
                                    <Input
                                        id={`${signatureState.role}_signing_link`}
                                        value={generatedLink.signing_url}
                                        readOnly
                                        className="h-11 bg-background/80 text-xs sm:text-sm"
                                    />
                                    {generatedLink.expires_at ? (
                                        <p className="text-xs text-muted-foreground">
                                            Expires{' '}
                                            {formatDateTime(
                                                generatedLink.expires_at,
                                            )}
                                        </p>
                                    ) : null}
                                </div>
                            ) : signatureState.has_active_link ? (
                                <div className="rounded-lg border border-border/50 bg-muted/10 p-3 text-xs text-muted-foreground">
                                    For security, the full signing URL is only
                                    shown immediately after generation. Use
                                    Regenerate link if you need a fresh secure
                                    link to share again.
                                </div>
                            ) : null}

                            <div
                                className={
                                    hasVisibleLink
                                        ? 'grid gap-2 sm:grid-cols-2'
                                        : 'grid gap-2'
                                }
                            >
                                {hasVisibleLink ? (
                                    <Button
                                        type="button"
                                        className="h-11 w-full"
                                        disabled={isGenerating}
                                        onClick={onCopy}
                                    >
                                        <Link2 className="size-4" />
                                        Copy link
                                    </Button>
                                ) : null}
                                <Button
                                    type="button"
                                    variant={
                                        hasVisibleLink ? 'outline' : 'default'
                                    }
                                    className="h-11 w-full"
                                    disabled={!isRequired || isGenerating}
                                    onClick={onGenerate}
                                >
                                    {signatureState.state === 'link_active' ||
                                    signatureState.state === 'expired' ? (
                                        <RefreshCcw className="size-4" />
                                    ) : (
                                        <Link2 className="size-4" />
                                    )}
                                    {hasVisibleLink
                                        ? 'Regenerate link'
                                        : primaryActionLabel}
                                </Button>
                            </div>
                        </div>
                    ) : null}

                    <InputError message={error} />
                </>
            )}
        </div>
    );
};

const CoMakerSignatureActionsCard = ({
    title,
    description,
    signatureState,
    isRequired,
    hasPendingInPersonSignature = false,
    generatedLink,
    isGenerating,
    error,
    onGenerate,
    onCopy,
}: CoMakerSignatureActionsCardProps) => {
    const signatureStatus = describeCoMakerSignatureStatus(
        signatureState,
        isRequired,
    );

    return (
        <SummaryCard title={title} description={description}>
            <div className="space-y-4">
                <div className="flex flex-wrap items-center gap-2">
                    <Badge className={signatureStatus.badgeClassName}>
                        {signatureStatus.label}
                    </Badge>
                    {signatureState.signed_at ? (
                        <span className="text-xs text-muted-foreground">
                            Confirmed {formatDateTime(signatureState.signed_at)}
                        </span>
                    ) : null}
                    {signatureState.expires_at ? (
                        <span className="text-xs text-muted-foreground">
                            Expires {formatDateTime(signatureState.expires_at)}
                        </span>
                    ) : null}
                </div>

                <p className="text-sm text-muted-foreground">
                    {signatureStatus.description}
                </p>

                {hasPendingInPersonSignature ? (
                    <div className="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-900 dark:text-amber-100">
                        An in-person co-maker signature is ready on this device
                        and will be saved when you save the draft or submit the
                        request.
                    </div>
                ) : null}

                {signatureState.is_confirmed || hasPendingInPersonSignature ? null : (
                    <div className="space-y-4">
                        {generatedLink ? (
                            <div className="space-y-2 rounded-lg border border-dashed border-border/60 bg-muted/10 p-3">
                                <Label
                                    htmlFor={`${signatureState.role}_review_signing_link`}
                                    className="text-xs font-medium tracking-[0.14em] text-muted-foreground uppercase"
                                >
                                    Secure signing link
                                </Label>
                                <Input
                                    id={`${signatureState.role}_review_signing_link`}
                                    value={generatedLink.signing_url}
                                    readOnly
                                    className="h-11 bg-background/80 text-xs sm:text-sm"
                                />
                            </div>
                        ) : null}

                        <div
                            className={
                                generatedLink
                                    ? 'grid gap-2 sm:grid-cols-2'
                                    : 'grid gap-2'
                            }
                        >
                            {generatedLink ? (
                                <Button
                                    type="button"
                                    className="h-11 w-full"
                                    disabled={isGenerating}
                                    onClick={onCopy}
                                >
                                    <Link2 className="size-4" />
                                    Copy link
                                </Button>
                            ) : null}
                            <Button
                                type="button"
                                variant={generatedLink ? 'outline' : 'default'}
                                className="h-11 w-full"
                                disabled={!isRequired || isGenerating}
                                onClick={onGenerate}
                            >
                                {signatureState.state === 'link_active' ||
                                signatureState.state === 'expired' ? (
                                    <RefreshCcw className="size-4" />
                                ) : (
                                    <Link2 className="size-4" />
                                )}
                                {signatureState.state === 'link_active' ||
                                signatureState.state === 'expired'
                                    ? 'Regenerate link'
                                    : 'Generate signing link'}
                            </Button>
                        </div>

                        <InputError message={error} />
                    </div>
                )}
            </div>
        </SummaryCard>
    );
};

export function LoanRequestReviewStep({
    data,
    loanTypes,
    member,
    errors,
    onUndertakingChange,
    coMakerOneSignature,
    coMakerTwoSignature,
    coMakerOneRequired,
    coMakerTwoRequired,
    generatedLinks,
    coMakerOneHasPendingInPersonSignature,
    coMakerTwoHasPendingInPersonSignature,
    onGenerateSignatureLink,
    onCopySignatureLink,
    onRefreshSignatures,
    isGeneratingSignatureLinkRole,
    isRefreshingSignatures,
    canSubmitForReview,
    submitDisabledMessage = null,
}: ReviewStepProps) {
    const loanTypeLabel =
        loanTypes.find((type) => type.typecode === data.typecode)?.label ??
        data.typecode;
    const requestedAmount =
        data.requested_amount !== ''
            ? formatCurrency(Number(data.requested_amount))
            : '--';

    const loanSummary: SummaryItem[] = [
        { label: 'Loan type', value: displayText(loanTypeLabel || '') },
        { label: 'Requested amount', value: requestedAmount },
        {
            label: 'Requested term',
            value:
                data.requested_term.trim() !== ''
                    ? `${data.requested_term} months`
                    : '--',
        },
        {
            label: 'Availment status',
            value: displayValue(data.availment_status),
        },
        { label: 'Loan purpose', value: displayText(data.loan_purpose) },
    ];

    const applicantPersonal: SummaryItem[] = [
        { label: 'Applicant name', value: displayName(data.applicant) },
        { label: 'Nickname', value: displayText(data.applicant.nickname) },
        { label: 'Birthdate', value: displayValue(data.applicant.birthdate) },
        { label: 'Birthplace', value: displayText(resolveBirthplace(data.applicant)) },
        { label: 'Address', value: displayText(resolveAddress(data.applicant)) },
        {
            label: 'Length of stay',
            value: displayText(data.applicant.length_of_stay),
        },
        {
            label: 'Housing status',
            value: formatHousingStatus(data.applicant.housing_status),
        },
        { label: 'Cell no.', value: displayValue(data.applicant.cell_no) },
        {
            label: 'Civil status',
            value: formatCivilStatus(data.applicant.civil_status),
        },
        {
            label: 'Educational attainment',
            value: displayText(data.applicant.educational_attainment),
        },
        {
            label: 'No. of children',
            value: displayValue(data.applicant.number_of_children),
        },
        {
            label: 'Spouse name',
            value: displayText(data.applicant.spouse_name),
        },
        { label: 'Spouse age', value: displayValue(data.applicant.spouse_age) },
        {
            label: 'Spouse cell no.',
            value: displayValue(data.applicant.spouse_cell_no),
        },
    ];

    const applicantWork: SummaryItem[] = [
        {
            label: 'Employment type',
            value: displayValue(data.applicant.employment_type),
        },
        {
            label: 'Employer/Business name',
            value: displayText(data.applicant.employer_business_name),
        },
        {
            label: 'Employer/Business address',
            value: displayText(
                resolveEmployerBusinessAddress(data.applicant),
            ),
        },
        {
            label: 'Telephone no.',
            value: displayValue(data.applicant.telephone_no),
        },
        {
            label: 'Current position',
            value: displayText(data.applicant.current_position),
        },
        {
            label: 'Nature of business',
            value: displayText(data.applicant.nature_of_business),
        },
        {
            label: 'Years in work/business',
            value: displayText(data.applicant.years_in_work_business),
        },
        {
            label: 'Gross monthly income',
            value:
                data.applicant.gross_monthly_income.trim() !== ''
                    ? formatCurrency(
                          Number(data.applicant.gross_monthly_income),
                      )
                    : '--',
        },
        { label: 'Payday', value: formatPayday(data.applicant.payday) },
    ];

    const buildCoMakerSummary = (
        label: string,
        person: LoanRequestPersonFormData,
    ): SummaryItem[] => [
        { label: `${label} name`, value: displayName(person) },
        { label: 'Nickname', value: displayText(person.nickname) },
        { label: 'Birthdate', value: displayValue(person.birthdate) },
        { label: 'Birthplace', value: displayText(resolveBirthplace(person)) },
        { label: 'Address', value: displayText(resolveAddress(person)) },
        { label: 'Length of stay', value: displayText(person.length_of_stay) },
        {
            label: 'Housing status',
            value: formatHousingStatus(person.housing_status),
        },
        { label: 'Cell no.', value: displayValue(person.cell_no) },
        {
            label: 'Civil status',
            value: formatCivilStatus(person.civil_status),
        },
        {
            label: 'Educational attainment',
            value: displayText(person.educational_attainment),
        },
        {
            label: 'Employment type',
            value: displayValue(person.employment_type),
        },
        {
            label: 'Employer/Business name',
            value: displayText(person.employer_business_name),
        },
        {
            label: 'Employer/Business address',
            value: displayText(resolveEmployerBusinessAddress(person)),
        },
        { label: 'Telephone no.', value: displayValue(person.telephone_no) },
        {
            label: 'Current position',
            value: displayText(person.current_position),
        },
        {
            label: 'Nature of business',
            value: displayText(person.nature_of_business),
        },
        {
            label: 'Years in work/business',
            value: displayText(person.years_in_work_business),
        },
        {
            label: 'Gross monthly income',
            value:
                person.gross_monthly_income.trim() !== ''
                    ? formatCurrency(Number(person.gross_monthly_income))
                    : '--',
        },
        { label: 'Payday', value: formatPayday(person.payday) },
    ];

    return (
        <LoanRequestSectionCard
            title="Review & undertaking"
            description="Review your application before submitting."
            contentClassName="space-y-5"
        >
            <div className="rounded-lg border border-border/50 bg-muted/20 p-4 text-sm">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p className="text-xs text-muted-foreground uppercase">
                            Member
                        </p>
                        <p className="mt-2 font-medium">
                            {displayText(member.name)}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Account No: {member.acctno ?? '--'}
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="self-start"
                        disabled={isRefreshingSignatures}
                        onClick={onRefreshSignatures}
                    >
                        <RefreshCcw className="size-4" />
                        Refresh signature statuses
                    </Button>
                </div>
            </div>

            <SummaryCard
                title="Loan details"
                description="Review the requested loan information."
            >
                <SummaryGrid items={loanSummary} />
            </SummaryCard>

            <SummaryCard
                title="Applicant personal data"
                description="Confirm personal information."
            >
                <SummaryGrid items={applicantPersonal} />
            </SummaryCard>

            <SummaryCard
                title="Applicant work & finances"
                description="Verify employment and income details."
            >
                <SummaryGrid items={applicantWork} />
            </SummaryCard>

            <SummaryCard
                title="Co-maker 1"
                description="Review the proposed details for your first co-maker."
            >
                <SummaryGrid
                    items={buildCoMakerSummary('Co-maker 1', data.co_maker_1)}
                />
            </SummaryCard>

            <CoMakerSignatureActionsCard
                title="Co-maker 1 signature"
                description="Share a secure signing link after you confirm the proposed details are correct."
                signatureState={coMakerOneSignature}
                isRequired={coMakerOneRequired}
                hasPendingInPersonSignature={
                    coMakerOneHasPendingInPersonSignature
                }
                generatedLink={generatedLinks.co_maker_1}
                isGenerating={isGeneratingSignatureLinkRole === 'co_maker_1'}
                error={errors['co_maker_1.signature']}
                onGenerate={() => onGenerateSignatureLink('co_maker_1')}
                onCopy={() => onCopySignatureLink('co_maker_1')}
            />

            <SummaryCard
                title="Co-maker 2"
                description="Review the proposed details for your second co-maker."
            >
                <SummaryGrid
                    items={buildCoMakerSummary('Co-maker 2', data.co_maker_2)}
                />
            </SummaryCard>

            <CoMakerSignatureActionsCard
                title="Co-maker 2 signature"
                description="This co-maker is only confirmed after they consent and sign through their own secure link."
                signatureState={coMakerTwoSignature}
                isRequired={coMakerTwoRequired}
                hasPendingInPersonSignature={
                    coMakerTwoHasPendingInPersonSignature
                }
                generatedLink={generatedLinks.co_maker_2}
                isGenerating={isGeneratingSignatureLinkRole === 'co_maker_2'}
                error={errors['co_maker_2.signature']}
                onGenerate={() => onGenerateSignatureLink('co_maker_2')}
                onCopy={() => onCopySignatureLink('co_maker_2')}
            />

            <Alert className="border-amber-500/30 bg-amber-500/10 text-amber-900 dark:text-amber-100">
                <AlertTitle>Submit for Review</AlertTitle>
                <AlertDescription>
                    <p>
                        Submit for Review is available after all required
                        co-makers have signed.
                    </p>
                    {!canSubmitForReview && submitDisabledMessage ? (
                        <p>{submitDisabledMessage}</p>
                    ) : null}
                </AlertDescription>
            </Alert>

            <SummaryCard
                title="Undertaking"
                description="Please read and confirm before submission."
            >
                <div className="space-y-4 text-sm text-muted-foreground">
                    <p>
                        I/We hereby undertake that all information provided here
                        in this application form and in all supporting document
                        are true and correct. I/We hereby authorized MRDINC to
                        verify any and all information furnished by me/us
                        including previous credit transactions with other
                        institution. In this connection, I/We hereby expressly
                        waive any and all statutory or regulatory provisions
                        governing confidentiality of such information. I fully
                        understand that any misrepresentation or failure to
                        disclose information on my/our part as required herein,
                        may cause the disapproval of my application.
                    </p>
                    <p>
                        Upon acceptance of my application, I/We legally and
                        validly bind to the terms and conditions of MRDINC
                        including, but not limited to, join and several
                        liability for all charges, fees and other obligations
                        incurred through the use of my loan. In case of
                        disapproval of this application, I understand that
                        MRDINC is not obligated to disclose the reasons for such
                        disapproval.
                    </p>
                    <p>
                        In the event of future delinquency, I hereby authorized
                        MRDINC to report and or include my name in the negative
                        listing of any bureau or institution.
                    </p>
                </div>

                <Separator className="my-4 bg-border/40" />

                <div className="flex items-start gap-3">
                    <Checkbox
                        id="undertaking_accepted"
                        checked={data.undertaking_accepted}
                        onCheckedChange={(checked) =>
                            onUndertakingChange(checked === true)
                        }
                    />
                    <div className="space-y-2">
                        <Label htmlFor="undertaking_accepted">
                            I confirm that I have read and agree to the
                            undertaking above.
                        </Label>
                        <InputError message={errors.undertaking_accepted} />
                    </div>
                </div>
            </SummaryCard>
        </LoanRequestSectionCard>
    );
}
