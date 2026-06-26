<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\SetLocale;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // SetLocale must also run on Livewire AJAX update requests,
        // which bypass the web middleware group defined in routes/web.php.
        Livewire::addPersistentMiddleware([SetLocale::class]);
    }
}
