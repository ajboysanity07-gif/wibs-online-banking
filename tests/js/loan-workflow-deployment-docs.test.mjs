import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import test from 'node:test';

test('loan workflow deployment guide documents the staged release sequence', async () => {
    const file = await readFile(
        resolve('docs', 'loan-workflow-production-deployment.md'),
        'utf8',
    );

    const orderedSnippets = [
        '7. Run pre-migration preflight.',
        '9. Run `php artisan migrate --force`.',
        '10. Run `php artisan loan-workflow:seed-permissions`.',
        '11. Run `php artisan loan-workflow:repair` in dry-run mode.',
        '13. Run `php artisan loan-workflow:repair --apply` only when approved.',
        '14. Run strict post-migration preflight.',
        '15. Run `php artisan loan-workflow:smoke-test`.',
    ];

    let previousIndex = -1;

    for (const snippet of orderedSnippets) {
        const currentIndex = file.indexOf(snippet);

        assert.notEqual(currentIndex, -1, `Missing deployment step: ${snippet}`);
        assert.ok(
            currentIndex > previousIndex,
            `Deployment step out of order: ${snippet}`,
        );

        previousIndex = currentIndex;
    }

    assert.match(file, /Do not continue to public traffic unless step 14 and step 15 pass\./);
});

test('loan workflow deployment guide documents staging-safe delivery and audited repair rehearsal', async () => {
    const file = await readFile(
        resolve('docs', 'loan-workflow-production-deployment.md'),
        'utf8',
    );

    assert.match(file, /loan-workflow:deployment-check --stage=pre-migration/);
    assert.match(file, /loan_workflow_repairs/);
    assert.match(file, /MAIL_MAILER=array/);
    assert.match(file, /SEMAPHORE_API_KEY/);
    assert.match(
        file,
        /The smoke test treats non-production mail or SMS configurations as unsafe when they appear to target live providers\./,
    );
});
