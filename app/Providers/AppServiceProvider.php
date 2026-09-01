<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\SetLocale;
use App\Services\Import\ArchiveExtractor;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ArchiveExtractor::class, fn (): ArchiveExtractor => new ArchiveExtractor(
            maxEntries: (int) config('imports.max_archive_entries'),
            maxTotalBytes: (int) config('imports.max_archive_bytes'),
        ));
    }

    public function boot(): void
    {
        // SetLocale must also run on Livewire AJAX update requests,
        // which bypass the web middleware group defined in routes/web.php.
        Livewire::addPersistentMiddleware([SetLocale::class]);

        // dompdf does not shape Arabic text or apply the bidi algorithm, so any
        // Arabic string renders character-reversed. @ar(...) pre-shapes it into
        // visual order first; used only inside PDF export views.
        Blade::directive('ar', fn (string $expression) => "<?php echo \App\Support\PdfArabicText::render($expression); ?>");
    }
}
