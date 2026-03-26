import type { ReactNode } from 'react';
import { SectionHeader } from '@/components/section-header';
import { SurfaceCard } from '@/components/surface-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type MemberRecordsCardProps = {
    title: string;
    description?: string;
    headerAccessory?: ReactNode;
    isUpdating?: boolean;
    error?: string | null;
    errorTitle?: string;
    onRetry?: () => void;
    showSkeleton?: boolean;
    skeletonBody?: ReactNode;
    skeletonMobile?: ReactNode;
    skeletonDesktop?: ReactNode;
    mobileWrapperClassName?: string;
    desktopWrapperClassName?: string;
    body?: ReactNode;
    mobileContent?: ReactNode;
    desktopContent?: ReactNode;
    footer?: ReactNode;
    showFooterWhenError?: boolean;
};

export function MemberRecordsCard({
    title,
    description,
    headerAccessory,
    isUpdating = false,
    error = null,
    errorTitle = 'Unable to load data',
    onRetry,
    showSkeleton = false,
    skeletonBody,
    skeletonMobile,
    skeletonDesktop,
    mobileWrapperClassName,
    desktopWrapperClassName,
    body,
    mobileContent,
    desktopContent,
    footer,
    showFooterWhenError = false,
}: MemberRecordsCardProps) {
    const headerRight =
        headerAccessory ??
        (isUpdating ? (
            <span className="text-xs text-muted-foreground">Updating...</span>
        ) : null);

    return (
        <SurfaceCard variant="default" padding="md" className="space-y-5">
            <SectionHeader
                title={title}
                description={description}
                actions={headerRight}
                titleClassName="text-base font-semibold"
            />
            <div className="space-y-4">
                {error ? (
                    <Alert variant="destructive">
                        <AlertTitle>{errorTitle}</AlertTitle>
                        <AlertDescription className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <span>{error}</span>
                            {onRetry ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={onRetry}
                                >
                                    Retry
                                </Button>
                            ) : null}
                        </AlertDescription>
                    </Alert>
                ) : null}
                {showSkeleton ? (
                    (skeletonBody ?? (
                        <>
                            {skeletonMobile ? (
                                <div
                                    className={cn(
                                        'md:hidden',
                                        mobileWrapperClassName,
                                    )}
                                    aria-busy="true"
                                >
                                    {skeletonMobile}
                                </div>
                            ) : null}
                            {skeletonDesktop ? (
                                <div
                                    className={cn(
                                        'hidden md:block',
                                        desktopWrapperClassName,
                                    )}
                                    aria-busy="true"
                                >
                                    {skeletonDesktop}
                                </div>
                            ) : null}
                        </>
                    ))
                ) : body ? (
                    body
                ) : (
                    <>
                        {mobileContent ? (
                            <div
                                className={cn(
                                    'md:hidden',
                                    mobileWrapperClassName,
                                )}
                            >
                                {mobileContent}
                            </div>
                        ) : null}
                        {desktopContent ? (
                            <div
                                className={cn(
                                    'hidden md:block',
                                    desktopWrapperClassName,
                                )}
                            >
                                {desktopContent}
                            </div>
                        ) : null}
                    </>
                )}
                {footer && (showFooterWhenError || !error) ? footer : null}
            </div>
        </SurfaceCard>
    );
}
