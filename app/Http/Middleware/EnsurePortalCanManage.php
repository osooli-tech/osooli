<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Portal\PortalContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets an action through only when the signed-in account may act for the
 * owner, not just look: `portal.manage`. With `portal.manage:self`, only the
 * owner's own account may — for changing the owner's own details.
 */
class EnsurePortalCanManage
{
    public function __construct(private readonly PortalContext $context) {}

    public function handle(Request $request, Closure $next, ?string $who = null): Response
    {
        $allowed = $who === 'self'
            ? $this->context->isSelf()
            : $this->context->canManage();

        abort_unless($allowed, Response::HTTP_FORBIDDEN, __('portal.view_only_refused'));

        return $next($request);
    }
}
