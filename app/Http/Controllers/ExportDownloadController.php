<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\Export\ExportRuns;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Hands out a finished bulk export — to the person who made it, or an administrator. */
class ExportDownloadController extends Controller
{
    public function __invoke(Request $request, string $id): BinaryFileResponse
    {
        $run = ExportRuns::find($id);
        $user = $request->user();

        abort_if($run === null || ($run['state'] ?? null) !== 'succeeded' || ! is_file(ExportRuns::filePath($id)), 404);
        abort_unless((int) ($run['user_id'] ?? 0) === (int) $user?->id || $user?->can('roles.manage'), 403);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'download',
            'target_type' => 'export',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        return response()->download(ExportRuns::filePath($id), "sokuki-deeds-{$id}.geojson", [
            'Content-Type' => 'application/geo+json',
        ]);
    }
}
