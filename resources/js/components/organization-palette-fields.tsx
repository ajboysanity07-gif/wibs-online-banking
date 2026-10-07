import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BrandPaletteKey } from '@/types';

export type PaletteValues = Record<BrandPaletteKey, string>;

type PaletteField = {
    key: BrandPaletteKey;
    label: string;
    hint: string;
    fallback: string;
};

export const PALETTE_GROUPS: Array<{
    title: string;
    description: string;
    fields: PaletteField[];
}> = [
    {
        title: 'Text',
        description: 'Default text color used across the portal.',
        fields: [
            {
                key: 'ink',
                label: 'Ink',
                hint: 'Body text and headings.',
                fallback: '#14170f',
            },
        ],
    },
    {
        title: 'Surfaces',
        description: 'Backgrounds and borders in light mode.',
        fields: [
            {
                key: 'background',
                label: 'Page background',
                hint: 'Behind every page.',
                fallback: '#dfe7cb',
            },
            {
                key: 'card',
                label: 'Cards & dialogs',
                hint: 'Panels, tables, popovers.',
                fallback: '#ffffff',
            },
            {
                key: 'secondary',
                label: 'Secondary',
                hint: 'Selected rows and soft buttons.',
                fallback: '#d3e6a6',
            },
            {
                key: 'muted',
                label: 'Muted',
                hint: 'Table headers and quiet fills.',
                fallback: '#eef3de',
            },
            {
                key: 'border',
                label: 'Borders',
                hint: 'Dividers and card outlines.',
                fallback: '#b7c399',
            },
            {
                key: 'sidebar',
                label: 'Sidebar',
                hint: 'Navigation panel.',
                fallback: '#176433',
            },
        ],
    },
    {
        title: 'Status colors',
        description:
            'Badges for approved, pending, info, and rejected states. One base color each; shades are derived for light and dark mode.',
        fields: [
            {
                key: 'success',
                label: 'Success',
                hint: 'Approved, active.',
                fallback: '#3f8a3a',
            },
            {
                key: 'warning',
                label: 'Warning',
                hint: 'Pending, needs attention.',
                fallback: '#b7791f',
            },
            {
                key: 'info',
                label: 'Info',
                hint: 'Under review, notes.',
                fallback: '#2b7aa8',
            },
            {
                key: 'danger',
                label: 'Danger',
                hint: 'Rejected, suspended.',
                fallback: '#c0392b',
            },
        ],
    },
];

const HEX = /^#[0-9a-f]{6}$/i;

type Props = {
    values: PaletteValues;
    errors: Record<string, string | undefined>;
    onChange: (key: BrandPaletteKey, value: string) => void;
};

export function OrganizationPaletteFields({ values, errors, onChange }: Props) {
    return (
        <div className="grid gap-6">
            {PALETTE_GROUPS.map((group) => (
                <div key={group.title} className="grid gap-4">
                    <div>
                        <h3 className="text-sm font-bold">{group.title}</h3>
                        <p className="text-xs text-muted-foreground">
                            {group.description}
                        </p>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {group.fields.map((field) => {
                            const value = values[field.key];
                            const valid = HEX.test(value);
                            const id = `brand_palette_${field.key}`;
                            const error = errors[`brand_palette.${field.key}`];

                            return (
                                <div key={field.key} className="grid gap-2">
                                    <Label htmlFor={id}>{field.label}</Label>
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="color"
                                            value={
                                                valid ? value : field.fallback
                                            }
                                            aria-label={`${field.label} color picker`}
                                            className="h-9 w-9 shrink-0 cursor-pointer rounded-md border border-border bg-transparent p-0"
                                            onChange={(event) =>
                                                onChange(
                                                    field.key,
                                                    event.target.value.toLowerCase(),
                                                )
                                            }
                                        />
                                        <Input
                                            id={id}
                                            name={`brand_palette[${field.key}]`}
                                            value={value}
                                            placeholder={field.fallback}
                                            maxLength={7}
                                            spellCheck={false}
                                            autoCapitalize="none"
                                            aria-invalid={
                                                error !== undefined ||
                                                (value !== '' && !valid)
                                            }
                                            className="w-28 font-mono"
                                            onChange={(event) =>
                                                onChange(
                                                    field.key,
                                                    event.target.value.toLowerCase(),
                                                )
                                            }
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            disabled={value === ''}
                                            onClick={() =>
                                                onChange(field.key, '')
                                            }
                                        >
                                            Reset
                                        </Button>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {field.hint} Default: {field.fallback}
                                    </p>
                                    <InputError
                                        message={
                                            error ??
                                            (value !== '' && !valid
                                                ? 'Use a 6-digit hex value like #1a2b3c.'
                                                : undefined)
                                        }
                                    />
                                </div>
                            );
                        })}
                    </div>
                </div>
            ))}
        </div>
    );
}
