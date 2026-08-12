@extends('layouts.app')

@section('title', $document->title)

@section('breadcrumb')
    <span class="text-on-surface-variant dark:text-on-primary-container mx-1">/</span>
    <a href="{{ route('settings.index') }}" class="hover:underline">{{ __('settings.title') }}</a>
    <span class="text-on-surface-variant dark:text-on-primary-container mx-1">/</span>
    <span class="text-on-surface dark:text-white">{{ $document->title }}</span>
@endsection

@section('page-title', $document->title)

@section('content')
    @if (session('status'))
        <div class="mb-4 rounded-xl bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('legal.update', $document->key) }}" id="legal-form"
          class="bg-white dark:bg-[#0b1a2b] rounded-2xl shadow-sm p-6 space-y-5">
        @csrf

        <div>
            <label class="block text-sm font-semibold mb-2 text-on-surface dark:text-white">
                {{ __('العنوان') }}
            </label>
            <input type="text" name="title" value="{{ old('title', $document->title) }}" maxlength="120"
                   class="w-full rounded-xl border border-outline-variant bg-transparent px-4 py-2.5
                          text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-secondary">
            @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-semibold mb-2 text-on-surface dark:text-white">
                {{ __('المحتوى') }}
            </label>
            {{-- Quill editor --}}
            <div id="editor" class="bg-white text-black rounded-b-xl" style="min-height: 420px" dir="rtl"></div>
            <input type="hidden" name="content" id="content-input">
            @error('content') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center justify-between">
            <p class="text-xs text-on-surface-variant dark:text-on-primary-container">
                {{ __('آخر تحديث') }}: {{ $document->updated_at?->format('Y/m/d H:i') }}
            </p>
            <button type="submit"
                    class="rounded-xl bg-secondary text-white font-semibold px-8 py-2.5 hover:opacity-90 transition">
                {{ __('حفظ') }}
            </button>
        </div>
    </form>
@endsection

@push('scripts')
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
    <script>
        const quill = new Quill('#editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ header: [2, 3, false] }],
                    ['bold', 'italic', 'underline'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    [{ direction: 'rtl' }, { align: [] }],
                    ['link'],
                    ['clean'],
                ],
            },
        });

        // Load current content, keep RTL as default.
        quill.clipboard.dangerouslyPasteHTML(@json($document->content));
        quill.format('direction', 'rtl');
        quill.format('align', 'right');

        document.getElementById('legal-form').addEventListener('submit', () => {
            document.getElementById('content-input').value = quill.getSemanticHTML();
        });
    </script>
@endpush
