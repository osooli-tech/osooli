<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Portal\PortalContext;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks, on every portal and app request, that the account that signed in
 * still opens the owner being acted for.
 *
 * It fails closed: no account, an inactive account, a web session older than
 * the account's last "sign out everywhere", or an owner the account no longer
 * opens all end the sign-in. On the web, an account that still opens other
 * owners moves on to one of them instead.
 */
class EnsurePortalAccount
{
    public function __construct(private readonly PortalContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $owner = $this->context->owner();
        $account = $this->context->account();

        if ($owner === null) {
            return $next($request);
        }

        $viaToken = $this->context->viaToken();

        if ($account === null || ! $account->is_active || (! $viaToken && ! $this->context->sessionIsCurrent())) {
            $this->refuse($viaToken);
        }

        if (! $account->opens((int) $owner->id)) {
            $next_owner = $viaToken ? null : $account->defaultOwner();

            if ($next_owner === null) {
                $this->refuse($viaToken);
            }

            $this->context->switchTo($next_owner);

            return redirect()->route('portal.dashboard');
        }

        return $next($request);
    }

    /** @throws AuthenticationException */
    private function refuse(bool $viaToken): never
    {
        if (! $viaToken) {
            $this->context->signOut();
        }

        throw new AuthenticationException(guards: [$viaToken ? 'sanctum' : 'owner']);
    }
}
