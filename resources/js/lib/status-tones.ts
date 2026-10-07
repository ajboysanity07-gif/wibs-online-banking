// Status badge tones. Colors come from the --ok/--warn/--info/--act/--bad/--hold
// CSS variables (defaults in resources/css/app.css; success/warning/info/danger
// are overridable from Organization settings via resolveBrandingTheme).
const tone = (name: string): string =>
    `border-[var(--${name}-bd)] bg-[var(--${name}-bg)] text-[var(--${name}-ink)]`;

export const statusTones = {
    ok: tone('ok'),
    warn: tone('warn'),
    info: tone('info'),
    act: tone('act'),
    bad: tone('bad'),
    hold: tone('hold'),
    neutral: 'border-border bg-muted text-foreground',
} as const;
