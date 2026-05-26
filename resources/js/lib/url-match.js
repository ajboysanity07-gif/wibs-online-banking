/**
 * Normalize a path for matching.
 *
 * @param {string} path
 * @returns {string}
 */
export function normalizePath(path) {
    if (!path) {
        return '/';
    }

    const [pathname] = path.trim().split('?');
    const withLeadingSlash = pathname.startsWith('/')
        ? pathname
        : `/${pathname}`;
    const trimmed =
        withLeadingSlash.length > 1
            ? withLeadingSlash.replace(/\/+$/, '')
            : withLeadingSlash;

    return trimmed || '/';
}

/**
 * Determine if the current path is within the base section.
 *
 * @param {string} basePath
 * @param {string} currentPath
 * @returns {boolean}
 */
export function isWithinSectionPath(basePath, currentPath) {
    const base = normalizePath(basePath);
    const current = normalizePath(currentPath);

    if (base === '/') {
        return current === '/';
    }

    if (current === base) {
        return true;
    }

    return current.startsWith(`${base}/`);
}

/**
 * Determine if the current path matches any exact paths.
 *
 * @param {string[]} paths
 * @param {string} currentPath
 * @returns {boolean}
 */
export function matchesExactPaths(paths, currentPath) {
    const current = normalizePath(currentPath);

    return paths.some((path) => normalizePath(path) === current);
}

/**
 * Determine if the current path is within any section base paths.
 *
 * @param {string[]} basePaths
 * @param {string} currentPath
 * @returns {boolean}
 */
export function matchesSectionPaths(basePaths, currentPath) {
    return basePaths.some((path) => isWithinSectionPath(path, currentPath));
}

/**
 * Determine whether a navigation target matches the current path.
 *
 * @param {{
 *   currentPath: string,
 *   targets: string[],
 *   excludedTargets?: string[],
 *   match?: 'exact' | 'section',
 * }} options
 * @returns {boolean}
 */
export function isRouteMatch(options) {
    const current = normalizePath(options.currentPath);
    const targets = options.targets ?? [];
    const excludedTargets = options.excludedTargets ?? [];
    const shouldExclude =
        matchesExactPaths(excludedTargets, current) ||
        matchesSectionPaths(excludedTargets, current);

    if (shouldExclude) {
        return false;
    }

    if ((options.match ?? 'exact') === 'section') {
        return matchesSectionPaths(targets, current);
    }

    return matchesExactPaths(targets, current);
}
