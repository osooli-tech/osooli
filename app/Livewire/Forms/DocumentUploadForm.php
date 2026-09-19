<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Models\ParcelPhoto;
use App\Support\Concerns\WritesSafely;
use App\Support\DatabaseEnum;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;
use RuntimeException;

/**
 * Takes files from the browser and turns them into parcel_photos rows.
 *
 * Modelled on OwnerForm: fields, rules, Arabic labels and the write live in
 * one object so nothing can drift. What it adds is the file half — every
 * upload lands on the private `documents` disk under a generated name, and the
 * row starts its life pending, invisible to everyone but a reviewer.
 */
class DocumentUploadForm extends Form
{
    use WritesSafely;

    /** Per-file ceiling, in kilobytes. Stated in the UI, enforced here. */
    public const MAX_KILOBYTES = 10240;

    /** How many files one submission may carry. */
    public const MAX_FILES = 10;

    /** @var list<string> Extensions offered by the picker and accepted here. */
    public const ACCEPTED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    /*
     * The two foreign keys are held as strings, not ?int, because that is what
     * a <select> posts: its empty option is '', which a typed ?int property
     * refuses outright. Conversion happens once, on the way to the column.
     */
    public string $parcelId = '';

    public string $deedId = '';

    public string $photoType = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'parcelId' => ['required', 'integer', Rule::exists('parcels', 'id')],

            // Scoped to the chosen parcel: a deed belonging to another parcel
            // is not merely wrong, it would file the scan under someone else's
            // land. The column stays nullable — only a deed scan names a deed.
            'deedId' => [
                'nullable', 'integer',
                Rule::exists('deeds', 'id')->where('parcel_id', $this->parcel()),
            ],

            'photoType' => ['required', 'string', DatabaseEnum::rule('photo_type')],

            'files' => ['required', 'array', 'min:1', 'max:'.self::MAX_FILES],

            // Extension and reported content type both, so renaming a script to
            // .pdf does not get it past the check.
            'files.*' => [
                'required',
                'file',
                'mimes:'.implode(',', self::ACCEPTED_EXTENSIONS),
                'mimetypes:application/pdf,image/jpeg,image/png',
                'max:'.self::MAX_KILOBYTES,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'parcelId' => __('documents.parcel'),
            'deedId' => __('documents.deed'),
            'photoType' => __('documents.photo_type'),
            'files' => __('documents.files'),
            'files.*' => __('documents.file'),
        ];
    }

    /**
     * Store every selected file and record a document row for each.
     *
     * One writeSafely() call per file rather than one for the batch: each
     * document is reviewed, downloaded and audited on its own, so each needs
     * its own audit entry naming its own id.
     *
     * @return list<ParcelPhoto>
     */
    public function store(): array
    {
        $this->validate();

        $documents = [];

        foreach ($this->files as $file) {
            $documents[] = $this->storeOne($file);
        }

        $this->reset('files');

        return $documents;
    }

    /** The chosen parcel as an id, or null while nothing is chosen. */
    public function parcel(): ?int
    {
        return $this->orIntNull($this->parcelId);
    }

    /** Drop one file from the pending selection before it is submitted. */
    public function forget(int $index): void
    {
        unset($this->files[$index]);

        // Re-index so the list stays a list; Livewire keys the preview markup
        // by position and a hole leaves a stale row on screen.
        $this->files = array_values($this->files);
    }

    private function storeOne(TemporaryUploadedFile $file): ParcelPhoto
    {
        // Generated name, not the user's: the original may collide, may carry
        // path separators, and is attacker-controlled. It is kept verbatim in
        // original_name for display and for the download filename instead.
        $path = $file->store($this->directory(), ParcelPhoto::PRIVATE_DISK);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException(__('documents.store_failed'));
        }

        return $this->writeSafely(
            'document.upload',
            'document',
            null,
            fn (): ParcelPhoto => ParcelPhoto::create([
                'parcel_id' => $this->parcel(),
                'deed_id' => $this->orIntNull($this->deedId),
                'photo_url' => $path,
                'photo_type' => $this->photoType,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'storage_disk' => ParcelPhoto::PRIVATE_DISK,
                'status' => ParcelPhoto::STATUS_PENDING,
                'uploaded_by' => Auth::id(),
            ])
        );
    }

    /**
     * Year/month subdirectories, so a few years of deed scans do not end up as
     * one directory the filesystem struggles to list.
     */
    private function directory(): string
    {
        return now()->format('Y/m');
    }

    private function orIntNull(string $value): ?int
    {
        return $value !== '' ? (int) $value : null;
    }
}
