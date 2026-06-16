<?php

namespace App\Services\LoanRequests;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class LoanRequestDocumentStorage
{
    public function documentDisk(): string
    {
        $disk = trim((string) config('loan_workflow.documents.disk', 'local'));

        return $disk !== '' ? $disk : 'local';
    }

    public function documentDirectory(): string
    {
        return trim((string) config(
            'loan_workflow.documents.directory',
            'loan-request-documents',
        ));
    }

    public function temporaryDirectory(): string
    {
        return trim((string) config(
            'loan_workflow.documents.temporary_directory',
            'tmp/loan-workflow',
        ));
    }

    public function temporaryRetentionHours(): int
    {
        $hours = (int) config(
            'loan_workflow.documents.temporary_retention_hours',
            24,
        );

        return $hours > 0 ? $hours : 24;
    }

    public function newGeneratedDocumentPath(
        int $loanRequestId,
        string $documentKey,
        int $version,
        string $extension,
    ): string {
        return sprintf(
            '%s/%d/%s/v%d.%s',
            $this->documentDirectory(),
            $loanRequestId,
            trim($documentKey),
            max(1, $version),
            ltrim(trim($extension), '.'),
        );
    }

    public function temporaryWorkingDirectory(
        string $prefix,
        ?int $loanRequestId = null,
    ): string {
        $directory = sprintf(
            '%s/%s%s',
            $this->temporaryDirectory(),
            trim($prefix),
            $loanRequestId === null
                ? ''
                : '/'.$loanRequestId.'-'.Str::uuid(),
        );

        $absolutePath = Storage::disk($this->documentDisk())
            ->path($this->ensureSafeRelativePath(
                $directory,
                [$this->temporaryDirectory()],
            ));
        File::ensureDirectoryExists($absolutePath);

        return $absolutePath;
    }

    public function temporaryFilePath(
        string $prefix,
        string $extension,
    ): string {
        $relativePath = sprintf(
            '%s/%s-%s.%s',
            $this->temporaryDirectory(),
            trim($prefix),
            Str::uuid(),
            ltrim(trim($extension), '.'),
        );

        $absolutePath = $this->absolutePath(
            $relativePath,
            $this->documentDisk(),
            [$this->temporaryDirectory()],
        );
        File::ensureDirectoryExists(dirname($absolutePath));

        return $absolutePath;
    }

    /**
     * @param  list<string>|null  $allowedRoots
     */
    public function absolutePath(
        string $relativePath,
        ?string $disk = null,
        ?array $allowedRoots = null,
    ): string {
        return Storage::disk($disk ?? $this->documentDisk())
            ->path($this->ensureSafeRelativePath($relativePath, $allowedRoots));
    }

    public function legacyAbsolutePath(string $relativePath): string
    {
        return storage_path('app/'.$this->ensureSafeRelativePath($relativePath));
    }

    public function fileExists(string $relativePath, ?string $disk = null): bool
    {
        $diskName = $disk ?? $this->documentDisk();
        $safePath = $this->ensureSafeRelativePath($relativePath);

        if (Storage::disk($diskName)->exists($safePath)) {
            return true;
        }

        if ((string) config("filesystems.disks.{$diskName}.driver") !== 'local') {
            return false;
        }

        try {
            return File::exists(Storage::disk($diskName)->path($safePath));
        } catch (\Throwable) {
            return false;
        }
    }

    public function legacyFileExists(string $relativePath): bool
    {
        return File::exists($this->legacyAbsolutePath($relativePath));
    }

    public function copyLegacyFileToDisk(
        string $relativePath,
        ?string $disk = null,
    ): bool {
        $safePath = $this->ensureSafeRelativePath($relativePath);
        $legacyPath = $this->legacyAbsolutePath($safePath);

        if (! File::exists($legacyPath)) {
            return false;
        }

        $targetPath = $this->absolutePath($safePath, $disk);
        File::ensureDirectoryExists(dirname($targetPath));

        return File::copy($legacyPath, $targetPath);
    }

    public function checksumForAbsolutePath(string $absolutePath): ?string
    {
        if (! File::exists($absolutePath) || ! File::isFile($absolutePath)) {
            return null;
        }

        $hash = hash_file('sha256', $absolutePath);

        return is_string($hash) && $hash !== '' ? $hash : null;
    }

    public function checksumForStoredFile(
        string $relativePath,
        ?string $disk = null,
    ): ?string {
        return $this->checksumForAbsolutePath(
            $this->absolutePath($relativePath, $disk),
        );
    }

    /**
     * @return list<array{path:string, absolute_path:string, modified_at:CarbonImmutable|null}>
     */
    public function temporaryPathsOlderThan(
        CarbonImmutable $threshold,
    ): array {
        $relativeRoot = $this->temporaryDirectory();
        $absoluteRoot = $this->absolutePath(
            $relativeRoot,
            $this->documentDisk(),
            [$relativeRoot],
        );

        if (! File::isDirectory($absoluteRoot)) {
            return [];
        }

        $matches = [];

        foreach (File::allFiles($absoluteRoot) as $file) {
            $modifiedAt = CarbonImmutable::createFromTimestamp(
                $file->getMTime(),
            );

            if ($modifiedAt->greaterThan($threshold)) {
                continue;
            }

            $matches[] = [
                'path' => str_replace('\\', '/', Str::after(
                    $file->getPathname(),
                    rtrim(Storage::disk($this->documentDisk())->path(''), '\\/'),
                )),
                'absolute_path' => $file->getPathname(),
                'modified_at' => $modifiedAt,
            ];
        }

        return $matches;
    }

    /**
     * @param  list<string>|null  $allowedRoots
     */
    public function ensureSafeRelativePath(
        string $relativePath,
        ?array $allowedRoots = null,
    ): string {
        $normalizedPath = str_replace('\\', '/', trim($relativePath));
        $normalizedPath = ltrim($normalizedPath, '/');
        $normalizedPath = preg_replace('#/+#', '/', $normalizedPath) ?? '';

        if (
            $normalizedPath === ''
            || str_contains($normalizedPath, '../')
            || str_starts_with($normalizedPath, '../')
            || preg_match('#^[a-zA-Z]:/#', $normalizedPath) === 1
        ) {
            throw new RuntimeException('Unsafe loan workflow document path.');
        }

        $roots = $allowedRoots ?? [
            $this->documentDirectory(),
            $this->temporaryDirectory(),
        ];

        foreach ($roots as $root) {
            $normalizedRoot = trim(str_replace('\\', '/', trim($root)), '/');

            if (
                $normalizedRoot !== ''
                && (
                    $normalizedPath === $normalizedRoot
                    || str_starts_with($normalizedPath, $normalizedRoot.'/')
                )
            ) {
                return $normalizedPath;
            }
        }

        throw new RuntimeException('Loan workflow document path is outside the allowed roots.');
    }
}
