<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\EnsurePortalAccount;
use App\Http\Middleware\SetLocale;
use App\Models\Owner;
use App\Services\Import\ArchiveExtractor;
use App\Support\Database\DatabaseSettings;
use App\Support\Portal\PortalContext;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DatabaseSettings::class);
        $this->app->scoped(PortalContext::class);

        // Before anything opens a connection: the dashboard's database
        // settings decide which database is primary and how to reach both.
        // Tests always run against the connection phpunit.xml names — a
        // settings file on a developer's machine must never point
        // RefreshDatabase at a real database.
        if (! $this->app->runningUnitTests()) {
            $this->app->make(DatabaseSettings::class)->apply();
        }
        $this->app->bind(ArchiveExtractor::class, fn (): ArchiveExtractor => new ArchiveExtractor(
            maxEntries: (int) config('imports.max_archive_entries'),
            maxTotalBytes: (int) config('imports.max_archive_bytes'),
        ));
    }

    public function boot(): void
    {
        // SetLocale must also run on Livewire AJAX update requests,
        // which bypass the web middleware group defined in routes/web.php.
        // The portal's account check too: a deactivated account or a revoked
        // grant must stop a page's Livewire requests, not only page loads.
        Livewire::addPersistentMiddleware([SetLocale::class, EnsurePortalAccount::class]);

        // dompdf does not shape Arabic text or apply the bidi algorithm, so any
        // Arabic string renders character-reversed. @ar(...) pre-shapes it into
        // visual order first; used only inside PDF export views.
        // The portal's sidebar shows "linked parcels" only to an owner who has
        // some; the layout stays a thin shell and is handed the answer.
        View::composer('portal.layout', function (ViewContract $view): void {
            $owner = Auth::guard('owner')->user();
            $view->with('hasLinkedParcels', $owner instanceof Owner && $owner->linkedParcels()->exists());

            // The account that signed in, and the owners it may switch to.
            $context = app(PortalContext::class);
            $view->with('portalAccount', $context->account());
            $view->with('portalSwitchable', $owner instanceof Owner ? $context->switchable() : []);
            $view->with('portalCanManage', $owner instanceof Owner && $context->canManage());
        });

        Blade::directive('ar', fn (string $expression) => "<?php echo \App\Support\PdfArabicText::render($expression); ?>");
    }
}
