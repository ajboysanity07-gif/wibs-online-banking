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
