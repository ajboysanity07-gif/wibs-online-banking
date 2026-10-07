<?php

use Illuminate\Console\Scheduling\Schedule;

test('backups are scheduled daily', function (): void {
    $commands = collect(app(Schedule::class)->events())->pluck('command')->implode(' ');

    expect($commands)->toContain('backup:run')->toContain('backup:clean')->toContain('backup:monitor');
});

test('backups cover only private document storage and never the code or .env', function (): void {
    $include = config('backup.backup.source.files.include');
    $backupsRoot = str_replace(DIRECTORY_SEPARATOR, '/', config('filesystems.disks.backups.root'));

    // Tests run against an isolated storage path, so compare suffixes only.
    expect($include)->toHaveCount(1);
    expect(str_replace(DIRECTORY_SEPARATOR, '/', $include[0]))->toEndWith('app/private');
    expect($backupsRoot)->toEndWith('app/backups');
    expect(config('backup.backup.destination.disks'))->not->toBeEmpty();
});
