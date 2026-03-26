import { useBranding } from '@/hooks/use-branding';
import { cn } from '@/lib/utils';
import AppLogoIcon from './app-logo-icon';

type AppLogoProps = {
    variant?: 'horizontal' | 'stacked';
    className?: string;
    iconClassName?: string;
    titleClassName?: string;
    subtitleClassName?: string;
};

export default function AppLogo({
    variant = 'horizontal',
    className,
    iconClassName,
    titleClassName,
    subtitleClassName,
}: AppLogoProps) {
    const branding = useBranding();
    const isStacked = variant === 'stacked';
    const showCompanyName = !branding.logoIsWordmark;
    const showPortalLabel = branding.portalLabel.trim() !== '';

    return (
        <span
            className={cn(
                'flex gap-2',
                isStacked
                    ? 'flex-col items-center text-center'
                    : 'items-center',
                className,
            )}
        >
            <AppLogoIcon
                className={cn(
                    isStacked
                        ? 'h-10 w-auto object-contain'
                        : 'h-8 w-auto object-contain',
                    iconClassName,
                )}
            />
            <span
                className={cn(
                    'flex flex-col leading-tight',
                    isStacked ? 'items-center' : '',
                )}
            >
                {showCompanyName ? (
                    <span
                        className={cn('text-sm font-semibold', titleClassName)}
                    >
                        {branding.companyName}
                    </span>
                ) : null}
                {showPortalLabel ? (
                    <span
                        className={cn(
                            'text-xs text-muted-foreground',
                            subtitleClassName,
                        )}
                    >
                        {branding.portalLabel}
                    </span>
                ) : null}
            </span>
        </span>
    );
}
