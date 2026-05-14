import type { InertiaLinkProps } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import {
    isWithinSectionPath,
    matchesExactPaths,
    matchesSectionPaths,
    normalizePath,
} from '@/lib/url-match';
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

export type MatchStrategy = 'exact' | 'section';

export type UrlMatchOptions = {
    href: NonNullable<InertiaLinkProps['href']>;
    match?: MatchStrategy;
    matchPaths?: Array<NonNullable<InertiaLinkProps['href']>>;
    excludeMatchPaths?: Array<NonNullable<InertiaLinkProps['href']>>;
};

export type IsMatchFn = (
    options: UrlMatchOptions,
    currentUrl?: string,
) => boolean;

export type UseCurrentUrlReturn = {
    currentUrl: string;
    isCurrentUrl: IsCurrentUrlFn;
    isWithinSection: IsCurrentUrlFn;
    isMatch: IsMatchFn;
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

    const isMatch: IsMatchFn = (
        options: UrlMatchOptions,
        currentUrl?: string,
    ) => {
        const urlToCompare = normalizePath(currentUrl ?? currentUrlPath);
        const targets =
            options.matchPaths && options.matchPaths.length > 0
                ? options.matchPaths
                : [options.href];
        const excludedTargets = options.excludeMatchPaths ?? [];
        const resolvedExcludedTargets = excludedTargets.map(resolvePathname);
        const shouldExclude =
            matchesExactPaths(resolvedExcludedTargets, urlToCompare) ||
            matchesSectionPaths(resolvedExcludedTargets, urlToCompare);

        if (shouldExclude) {
            return false;
        }

        const resolvedTargets = targets.map(resolvePathname);
        const matchStrategy = options.match ?? 'exact';

        if (matchStrategy === 'section') {
            return matchesSectionPaths(resolvedTargets, urlToCompare);
        }

        return matchesExactPaths(resolvedTargets, urlToCompare);
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
        isMatch,
        whenCurrentUrl,
    };
}
