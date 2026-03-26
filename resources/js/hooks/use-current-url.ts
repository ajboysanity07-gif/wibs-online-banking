import type { InertiaLinkProps } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { normalizePath, isWithinSectionPath } from '@/lib/url-match';
import { toUrl } from '@/lib/utils';

export type IsCurrentUrlFn = (
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    currentUrl?: string,
) => boolean;

export type WhenCurrentUrlFn = <TIfTrue, TIfFalse = null>(
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    ifTrue: TIfTrue,
    ifFalse?: TIfFalse,
) => TIfTrue | TIfFalse;

export type UseCurrentUrlReturn = {
    currentUrl: string;
    isCurrentUrl: IsCurrentUrlFn;
    isWithinSection: IsCurrentUrlFn;
    whenCurrentUrl: WhenCurrentUrlFn;
};

const resolvePathname = (
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
): string => {
    const urlString = toUrl(urlToCheck);

    if (!urlString.startsWith('http')) {
        return normalizePath(urlString);
    }

    try {
        const absoluteUrl = new URL(urlString);
        return normalizePath(absoluteUrl.pathname);
    } catch {
        return normalizePath(urlString);
    }
};

export function useCurrentUrl(): UseCurrentUrlReturn {
    const page = usePage();
    const currentUrlPath = normalizePath(
        new URL(page.url, window?.location.origin).pathname,
    );

    const isCurrentUrl: IsCurrentUrlFn = (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
    ) => {
        const urlToCompare = normalizePath(currentUrl ?? currentUrlPath);
        const urlPath = resolvePathname(urlToCheck);

        return urlPath === urlToCompare;
    };

    const isWithinSection: IsCurrentUrlFn = (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
    ) => {
        const urlToCompare = normalizePath(currentUrl ?? currentUrlPath);
        const urlPath = resolvePathname(urlToCheck);

        return isWithinSectionPath(urlPath, urlToCompare);
    };

    const whenCurrentUrl: WhenCurrentUrlFn = <TIfTrue, TIfFalse = null>(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        ifTrue: TIfTrue,
        ifFalse: TIfFalse = null as TIfFalse,
    ): TIfTrue | TIfFalse => {
        return isCurrentUrl(urlToCheck) ? ifTrue : ifFalse;
    };

    return {
        currentUrl: currentUrlPath,
        isCurrentUrl,
        isWithinSection,
        whenCurrentUrl,
    };
}
