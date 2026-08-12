<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** Dashboard editor for the CMS-managed legal texts. */
class LegalDocumentController extends Controller
{
    public function edit(string $key): View
    {
        abort_unless(in_array($key, LegalDocument::KEYS, true), 404);

        return view('settings.legal-edit', [
            'document' => LegalDocument::where('key', $key)->firstOrFail(),
        ]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        abort_unless(in_array($key, LegalDocument::KEYS, true), 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'content' => ['required', 'string', 'max:100000'],
        ]);

        LegalDocument::where('key', $key)->firstOrFail()->update($validated);
        Cache::forget("legal_document_{$key}");

        return redirect()
            ->route('legal.edit', $key)
            ->with('status', __('تم حفظ المحتوى بنجاح'));
    }
}
