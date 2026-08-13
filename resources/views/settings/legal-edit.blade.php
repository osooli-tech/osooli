@extends('layouts.app')

@php $pageTitle = $document->titleFor(app()->getLocale()); @endphp

@section('title', $pageTitle)

@section('breadcrumb')
    <span class="text-on-surface-variant dark:text-on-primary-container mx-1">/</span>
    <a href="{{ route('settings.index') }}" class="hover:underline">{{ __('settings.title') }}</a>
    <span class="text-on-surface-variant dark:text-on-primary-container mx-1">/</span>
    <span class="text-on-surface dark:text-white">{{ $pageTitle }}</span>
@endsection

@section('page-title', $pageTitle)

@section('content')
    @if (session('status'))
        <div class="mb-4 rounded-xl bg-secondary/10 border border-secondary/30 text-secondary px-4 py-3 text-sm">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('legal.update', $document->key) }}" id="legal-form"
          x-data="{ lang: 'ar' }"
          class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl border border-outline-variant
                 dark:border-white/10 shadow-sm p-6 space-y-5">
        @csrf

        {{-- Language switch. Both bodies post together, so nothing is lost by
             editing one and saving from the other. --}}
        <div class="flex gap-1 border-b border-outline-variant dark:border-white/10">
            @foreach (['ar' => __('legal.arabic'), 'en' => __('legal.english')] as $code => $label)
                <button type="button" @click="lang = '{{ $code }}'"
                        :class="lang === '{{ $code }}'
                            ? 'border-secondary text-secondary'
                            : 'border-transparent text-on-surface-variant dark:text-on-primary-container'"
                        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @foreach (['ar', 'en'] as $code)
            <div x-show="lang === '{{ $code }}'" x-cloak class="space-y-5">
                <div>
                    <label for="title_{{ $code }}" class="block text-sm font-semibold mb-2 text-on-surface dark:text-white">
                        {{ __('legal.field_title') }}
                        @if ($code === 'en')
                            <span class="font-normal text-xs text-on-surface-variant dark:text-on-primary-container">
                                — {{ __('legal.optional') }}
                            </span>
                        @endif
                    </label>
                    <input type="text" id="title_{{ $code }}" name="title_{{ $code }}"
                           value="{{ old('title_'.$code, $document->{'title_'.$code}) }}" maxlength="120"
                           dir="{{ $code === 'ar' ? 'rtl' : 'ltr' }}"
                           class="w-full rounded-xl border border-outline-variant bg-transparent px-4 py-2.5
                                  text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-secondary">
                    @error('title_'.$code) <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-2 text-on-surface dark:text-white">
                        {{ __('legal.field_content') }}
                        @if ($code === 'en')
                            <span class="font-normal text-xs text-on-surface-variant dark:text-on-primary-container">
                                — {{ __('legal.optional') }}
                            </span>
                        @endif
                    </label>
                    <div id="editor-{{ $code }}" class="bg-white text-black rounded-b-xl"
                         style="min-height: 420px" dir="{{ $code === 'ar' ? 'rtl' : 'ltr' }}"></div>
                    <input type="hidden" name="content_{{ $code }}" id="content-{{ $code }}">
                    @error('content_'.$code) <p class="mt-1 text-sm text-error">{{ $message }}</p> @enderror
                </div>
            </div>
        @endforeach

        <p class="text-xs text-on-surface-variant dark:text-on-primary-container">
            {{ __('legal.sanitised_note') }}
        </p>

        <div class="flex items-center justify-between">
            <p class="text-xs text-on-surface-variant dark:text-on-primary-container">
                {{ __('legal.last_updated') }}: {{ $document->updated_at?->format('Y/m/d H:i') }}
            </p>
            <button type="submit"
                    class="rounded-xl bg-secondary text-white font-semibold px-8 py-2.5 hover:opacity-90 transition">
                {{ __('legal.save') }}
            </button>
        </div>
    </form>
@endsection

@push('scripts')
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
    <script>
        const toolbar = [
            [{ header: [2, 3, false] }],
            ['bold', 'italic', 'underline'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            [{ direction: 'rtl' }, { align: [] }],
            ['link'],
            ['clean'],
        ];

        const editors = {
            ar: { quill: new Quill('#editor-ar', { theme: 'snow', modules: { toolbar } }), rtl: true },
            en: { quill: new Quill('#editor-en', { theme: 'snow', modules: { toolbar } }), rtl: false },
        };

        editors.ar.quill.clipboard.dangerouslyPasteHTML(@js($document->content_ar));
        editors.en.quill.clipboard.dangerouslyPasteHTML(@js($document->content_en ?? ''));

        editors.ar.quill.format('direction', 'rtl');
        editors.ar.quill.format('align', 'right');

        // Both hidden fields are filled on submit — the server stores the pair,
        // so switching tabs before saving cannot drop the other language.
        document.getElementById('legal-form').addEventListener('submit', () => {
            for (const [code, editor] of Object.entries(editors)) {
                document.getElementById(`content-${code}`).value = editor.quill.getSemanticHTML();
            }
        });
    </script>
@endpush
