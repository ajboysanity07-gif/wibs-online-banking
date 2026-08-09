import {
    DEPENDENT_CATEGORIES,
    DependentCategorySection,
    type DependentValues,
} from '@/components/dependents/dependent-category-section';
import { SurfaceCard } from '@/components/surface-card';
import { TabsContent } from '@/components/ui/tabs';

type Props = {
    formErrors: Record<string, string>;
    memberCivilStatus: string;
    dependentsValues: DependentValues;
    handleDependentsChange: (
        field: string,
        value: string | number | boolean | null,
    ) => void;
};

export function DependentsTab({
    formErrors,
    memberCivilStatus,
    dependentsValues,
    handleDependentsChange,
}: Props) {
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
