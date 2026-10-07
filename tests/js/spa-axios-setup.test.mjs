import assert from 'node:assert/strict';
import { readdir, readFile } from 'node:fs/promises';
import test from 'node:test';

const read = (path) =>
    readFile(new URL(`../../resources/js/${path}`, import.meta.url), 'utf8');

const sourceFiles = async (dir) =>
    (
        await readdir(new URL(`../../resources/js/${dir}`, import.meta.url), {
            recursive: true,
        })
    ).filter((file) => /\.tsx?$/.test(file));

test('toasts are mounted and Inertia owns the progress bar', async () => {
    const app = await read('app.tsx');

    assert.match(app, /<Toaster \/>/);
    assert.match(app, /progress: \{/);
    assert.doesNotMatch(app, /LoadingBar|nprogress/);
});

test('JSON requests go through the axios client, not fetch', async () => {
    for (const file of await sourceFiles('.')) {
        const source = await read(file);

        assert.doesNotMatch(source, /\bfetch\(/, `${file} uses fetch()`);
    }
});

test('error page reload button reloads the current page', async () => {
    const source = await read('pages/errors/error.tsx');

    assert.match(
        source,
        /const handleReload = \(\) => \{[\s\S]*?window\.location\.reload\(\);/,
    );
});
