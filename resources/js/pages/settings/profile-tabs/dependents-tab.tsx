import { UserRound } from 'lucide-react';
import {
    BeneficiaryCheckbox,
    countSelectedBeneficiaries,
    DEPENDENT_CATEGORIES,
    DependentCategorySection,
    INSURANCE_BENEFICIARY_LIMIT,
    isBeneficiaryFlag,
    SPOUSE_BENEFICIARY_KEY,
    type DependentValues,
} from '@/components/dependents/dependent-category-section';
import { SurfaceCard } from '@/components/surface-card';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { TabsContent } from '@/components/ui/tabs';

type Props = {
    formErrors: Record<string, string>;
    memberCivilStatus: string;
    dependentsValues: DependentValues;
    handleDependentsChange: (
        field: string,
        value: string | number | boolean | null,
    ) => void;
    spouseName: string;
    spouseBirthdate: string;
};

export function DependentsTab({
    formErrors,
    memberCivilStatus,
    dependentsValues,
    handleDependentsChange,
    spouseName,
    spouseBirthdate,
}: Props) {
    // Spouse isn't a slot-based category the member adds/removes -- it
    // follows civil status automatically, same as the loan request wizard.
    // Its name/birthdate live on the Personal tab, so this is a read-only
    // display, not an editable dependent slot.
    const showSpouse = memberCivilStatus === 'Married';

    return (
        <TabsContent value="dependents" forceMount className="mt-0">
            <SurfaceCard variant="muted" padding="md" className="space-y-6">
                <div className="space-y-6">
                    <div className="space-y-1">
                        <h3 className="text-base font-semibold">Dependents</h3>
                        <p className="text-sm text-muted-foreground">
                            Keep your dependents' names and birthdates up to
                            date. These are used to pre-fill future loan
                            requests. Changes here save immediately.
                        </p>
                    </div>

                    {showSpouse ? (
                        <div className="space-y-3">
                            <div className="flex items-center gap-2">
                                <UserRound className="size-4 text-muted-foreground" />
                                <p className="text-sm font-semibold text-foreground">
                                    Spouse
                                </p>
                            </div>
                            <Card className="gap-2 py-3">
                                <CardContent className="space-y-3 px-4 text-sm">
                                    {spouseName.trim() !== '' ? (
                                        <>
                                            <div className="flex flex-col">
                                                <span className="font-medium text-foreground">
                                                    {spouseName}
                                                </span>
                                                {spouseBirthdate.trim() !==
                                                '' ? (
                                                    <span className="text-xs text-muted-foreground">
                                                        {spouseBirthdate}
                                                    </span>
                                                ) : null}
                                            </div>
                                            <Separator />
                                            <BeneficiaryCheckbox
                                                fieldKey={
                                                    SPOUSE_BENEFICIARY_KEY
                                                }
                                                checked={isBeneficiaryFlag(
                                                    dependentsValues[
                                                        SPOUSE_BENEFICIARY_KEY
                                                    ],
                                                )}
                                                disabled={
                                                    !isBeneficiaryFlag(
                                                        dependentsValues[
                                                            SPOUSE_BENEFICIARY_KEY
                                                        ],
                                                    ) &&
                                                    countSelectedBeneficiaries(
                                                        dependentsValues,
                                                    ) >=
                                                        INSURANCE_BENEFICIARY_LIMIT
                                                }
                                                withNameAttribute
                                                onChange={(next) =>
                                                    handleDependentsChange(
                                                        SPOUSE_BENEFICIARY_KEY,
                                                        next,
                                                    )
                                                }
                                            />
                                        </>
                                    ) : (
                                        <p className="text-muted-foreground">
                                            Add your spouse's name and birthdate
                                            on the Personal tab.
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    ) : null}

                    {DEPENDENT_CATEGORIES.filter((category) => {
                        if (category.key === 'child') {
                            return memberCivilStatus === 'Married';
                        }

                        if (
                            category.key === 'sibling' ||
                            category.key === 'parent'
                        ) {
                            return memberCivilStatus === 'Single';
                        }

                        return true;
                    }).map((category) => (
                        <DependentCategorySection
                            key={category.key}
                            category={category}
                            values={dependentsValues}
                            errors={formErrors}
                            withNameAttribute
                            showCycleFields={false}
                            onChange={handleDependentsChange}
                        />
                    ))}
                </div>
            </SurfaceCard>
        </TabsContent>
    );
}
