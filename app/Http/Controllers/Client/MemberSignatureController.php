<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Wmaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MemberSignatureController extends Controller
{
    /**
     * Stream a member's signature image stored in wmaster.bsignature.
     *
     * Members may load their own signature; staff with member.view may load
     * any member's for statements and certificates. Empty or missing blobs
     * 404 so the UI falls back to the "signature on file" line.
     */
    public function __invoke(Request $request, string $acctno): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $acctno = trim($acctno);

        $isOwn = $user->acctno !== null && trim((string) $user->acctno) === $acctno;

        if (! $isOwn && ! $user->hasPermission(Permission::MEMBER_VIEW)) {
            abort(403);
        }

        try {
            if (! Schema::hasTable('wmaster') || ! Schema::hasColumn('wmaster', 'bsignature')) {
                abort(404);
            }

            $blob = Wmaster::query()->where('acctno', $acctno)->value('bsignature');
        } catch (Throwable $exception) {
            report($exception);
            abort(404);
        }

        if (! is_string($blob) || strlen($blob) < 8) {
            abort(404);
        }

        return new Response($blob, 200, [
            'Content-Type' => self::detectImageMime($blob),
            'Content-Length' => (string) strlen($blob),
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private static function detectImageMime(string $blob): string
    {
        if (str_starts_with($blob, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }

        if (str_starts_with($blob, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }

        if (str_starts_with($blob, 'GIF87a') || str_starts_with($blob, 'GIF89a')) {
            return 'image/gif';
        }

        if (str_starts_with($blob, 'BM')) {
            return 'image/bmp';
        }

        return 'application/octet-stream';
    }
}
