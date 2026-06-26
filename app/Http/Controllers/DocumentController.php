<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ParcelPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DocumentController extends Controller
{
    public function download(Request $request, ParcelPhoto $photo): RedirectResponse
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'download',
            'target_type' => 'document',
            'target_id' => $photo->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->away($photo->photo_url);
    }
}
