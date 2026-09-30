import { useId, useState, type ReactNode } from 'react';
import {
    displayCurrency,
    displayDateValue,
    displayText,
    displayValue,
    personName,
    resolveAddress,
    resolveEmployerBusinessAddress,
} from '@/components/loan-request/loan-request-detail-page';
import {
    LoanRequestFactGrid,
    type LoanRequestFact,
} from '@/components/loan-request/loan-request-fact-grid';
import {
    calculateAge,
    composeBirthplace,
    formatCivilStatus,
    formatHousingStatus,
    formatPayday,
} from '@/lib/formatters';
import { INSTITUTIONAL_EMPLOYER_CATEGORY_LABELS } from '@/lib/institutional-employer-category';
import { cn } from '@/lib/utils';
import type { LoanRequestPersonData } from '@/types/loan-requests';

const cardClassName = 'rounded-xl border border-border bg-card shadow-card';

const isEmptyPerson = (person: LoanRequestPersonData | null): boolean =>
    !person || ['', '--'].includes(personName(person));

const initials = (person: LoanRequestPersonData | null): string => {
    const name = isEmptyPerson(person) ? '' : personName(person);

    return (
        name
            .split(/\s+/)
            .filter(Boolean)
            .map((part) => part.charAt(0))
            .slice(0, 2)
            .join('')
            .toUpperCase() || '--'
    );
};

const workFacts = (person: LoanRequestPersonData | null): LoanRequestFact[] => [
    {
        label: 'Employment type',
        value: displayValue(person?.employment_type),
    },
    {
        label: 'Employer / business',
        value: displayText(person?.employer_business_name),
    },
    {
        label: 'Business address',
        value: displayText(resolveEmployerBusinessAddress(person)),
    },
    { label: 'Telephone', value: displayValue(person?.telephone_no) },
    {
        label: 'Current position',
        value: displayText(person?.current_position),
    },
    {
        label: 'Nature of business',
        value: displayText(person?.nature_of_business),
    },
    {
        label: 'Employer category',
        value: person?.institutional_employer_category
            ? (INSTITUTIONAL_EMPLOYER_CATEGORY_LABELS[
                  person.institutional_employer_category
              ] ?? person.institutional_employer_category)
            : '--',
    },
    {
        label: 'Years in work/business',
        value: displayText(person?.years_in_work_business),
    },
    {
        label: 'Date employed',
        value: displayDateValue(person?.employer_date_employed),
    },
    {
        label: 'Gross monthly income',
        value: displayCurrency(person?.gross_monthly_income),
    },
    {
        label: 'Payday',
        value: person?.payday ? formatPayday(person.payday) : '--',
    },
];

const personalFacts = (
    person: LoanRequestPersonData | null,
    { household }: { household: boolean },
): LoanRequestFact[] => [
    { label: 'Cell no.', value: displayValue(person?.cell_no) },
    { label: 'Address', value: displayText(resolveAddress(person)) },
    ...(household
        ? [
              {
                  label: 'Civil status',
                  value: person?.civil_status
                      ? formatCivilStatus(person.civil_status)
                      : '--',
              },
          ]
        : []),
    { label: 'Nickname', value: displayText(person?.nickname) },
    { label: 'Birthdate', value: displayDateValue(person?.birthdate) },
    {
        label: 'Birthplace',
        value: displayText(
            composeBirthplace(
                person?.birthplace_city,
                person?.birthplace_province,
            ),
        ),
    },
    ...(household ? [{ label: 'Sex', value: displayValue(person?.sex) }] : []),
    { label: 'Length of stay', value: displayText(person?.length_of_stay) },
    ...(household
        ? [
              {
                  label: 'Housing status',
                  value: person?.housing_status
                      ? formatHousingStatus(person.housing_status)
                      : '--',
              },
          ]
        : []),
    {
        label: 'Education',
        value: displayText(person?.educational_attainment),
    },
    ...(household
        ? [
              {
                  label: 'Children',
                  value: displayValue(person?.number_of_children),
              },
              {
                  label: 'Spouse name',
                  value: displayText(person?.spouse_name),
              },
              {
                  label: 'Spouse age',
                  value: displayValue(calculateAge(person?.spouse_birthdate)),
              },
              {
                  label: 'Spouse cell no.',
                  value: displayValue(person?.spouse_cell_no),
              },
          ]
        : []),
];

const SectionLabel = ({ children }: { children: ReactNode }) => (
    <p className="mb-3 border-b border-border pb-1.5 text-[11px] font-bold tracking-[0.14em] text-muted-foreground uppercase">
        {children}
    </p>
);

function PersonFacts({
    person,
    household,
    toggleLabel,
}: {
    person: LoanRequestPersonData | null;
    household: boolean;
    toggleLabel: string;
}) {
    const [showMore, setShowMore] = useState(false);
    const moreId = useId();

    return (
        <>
            <SectionLabel>Work and income</SectionLabel>
            <LoanRequestFactGrid facts={workFacts(person)} />
            <button
                type="button"
                className="mt-3.5 min-h-11 text-sm font-bold text-primary underline underline-offset-4 lg:min-h-10"
                aria-expanded={showMore}
                aria-controls={moreId}
                onClick={() => setShowMore((open) => !open)}
            >
                {showMore ? 'Hide' : 'Show'} {toggleLabel}
            </button>
            <div id={moreId} hidden={!showMore} className="mt-2.5">
                <SectionLabel>Personal and household</SectionLabel>
                <LoanRequestFactGrid
                    facts={personalFacts(person, { household })}
                />
            </div>
        </>
    );
}

type ApplicantPanelProps = {
    applicant: LoanRequestPersonData | null;
    /** Edit button, when the viewer may correct the application. */
    headerAction?: ReactNode;
};

export function LoanRequestApplicantPanel({
    applicant,
    headerAction,
}: ApplicantPanelProps) {
    return (
        <section className={cn(cardClassName, 'p-5')} aria-label="Applicant">
            <div className="mb-4 flex items-center gap-3">
                <span className="grid size-11 shrink-0 place-items-center rounded-full bg-accent font-bold text-accent-foreground">
                    {initials(applicant)}
                </span>
                <div className="min-w-0 flex-1">
                    <h2 className="truncate text-lg font-semibold">
                        {personName(applicant)}
                    </h2>
                    <p className="truncate text-[13px] text-muted-foreground">
                        {displayText(applicant?.current_position)} ·{' '}
                        {displayText(applicant?.employer_business_name)}
                    </p>
                </div>
                {headerAction}
            </div>
            <PersonFacts
                person={applicant}
                household
                toggleLabel="personal and household details"
            />
        </section>
    );
}

type CoMakerCardProps = {
    number: 1 | 2;
    person: LoanRequestPersonData | null;
    action?: ReactNode;
};

export function LoanRequestCoMakerCard({
    number,
    person,
    action,
}: CoMakerCardProps) {
    const empty = isEmptyPerson(person);

    return (
        <section
            className={cn(
                'rounded-[10px] p-3.5',
                empty
                    ? 'border border-dashed border-input'
                    : 'border border-border',
            )}
            aria-label={`Co-maker ${number}`}
        >
            <div className="flex flex-wrap items-center gap-3.5">
                {empty ? null : (
                    <span className="grid size-11 shrink-0 place-items-center rounded-full bg-accent font-bold text-accent-foreground">
                        {initials(person)}
                    </span>
                )}
                <div className="min-w-0 flex-1 basis-48">
                    <h3 className="text-sm font-semibold">
                        {empty ? `Co-maker ${number}` : personName(person)}
                    </h3>
                    <p className="text-[13px] text-muted-foreground">
                        {empty
                            ? 'No details submitted'
                            : `Co-maker ${number} · ${displayText(person?.employer_business_name)}`}
                    </p>
                </div>
                {empty ? (
                    <span className="rounded-md bg-muted px-2.5 py-0.5 text-[11px] font-bold text-muted-foreground">
                        Not provided
                    </span>
                ) : null}
                {action}
            </div>
            {empty ? null : (
                <div className="mt-4">
                    <PersonFacts
                        person={person}
                        household={false}
                        toggleLabel="more details"
                    />
                </div>
            )}
        </section>
    );
}
