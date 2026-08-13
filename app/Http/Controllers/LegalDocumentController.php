<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLegalDocumentRequest;
use App\Models\LegalDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** Public legal pages, and the dashboard editor behind them. */
class LegalDocumentController extends Controller
{
    /** Public page. Renders in the visitor's language, falling back to Arabic. */
    public function show(string $key): View
    {
        abort_unless(in_array($key, LegalDocument::KEYS, true), 404);

        $document = LegalDocument::where('key', $key)->firstOrFail();
        $locale = app()->getLocale();

        return view('legal.show', [
            'document' => $document,
            'title' => $document->titleFor($locale),
            'content' => $document->contentFor($locale),
            'isTranslated' => $document->hasTranslation($locale),
        ]);
    }

    public function edit(string $key): View
    {
        abort_unless(in_array($key, LegalDocument::KEYS, true), 404);

        return view('settings.legal-edit', [
            'document' => LegalDocument::where('key', $key)->firstOrFail(),
        ]);
    }

    public function update(UpdateLegalDocumentRequest $request, string $key): RedirectResponse
    {
        abort_unless(in_array($key, LegalDocument::KEYS, true), 404);

        // The model purifies both bodies on write, so nothing unsafe is stored
        // even if this request never went through the editor.
        LegalDocument::where('key', $key)->firstOrFail()->update($request->validated());

        foreach (LegalDocument::LOCALES as $locale) {
            Cache::forget("legal_document_{$key}_{$locale}");
        }

        return redirect()
            ->route('legal.edit', $key)
            ->with('status', __('legal.saved'));
    }
}
