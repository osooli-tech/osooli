<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    public function switch(string $lang): RedirectResponse
    {
        abort_if(! in_array($lang, ['ar', 'en']), 404);

        session(['locale' => $lang]);

        return redirect()->back();
    }
}
