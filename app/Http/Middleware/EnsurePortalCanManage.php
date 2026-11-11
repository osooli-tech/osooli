<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\PortalPermission;
use App\Support\Portal\PortalContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets an action through only when the signed-in account may act for the
 * owner, not just look: `portal.manage`. With `portal.manage:self`, only the
 * owner's own account may — for changing the owner's own details. With a
 * permission, `portal.manage:sell`, only when staff have not turned that off
 * for the account (PortalPermission).
 */
class EnsurePortalCanManage
{
    public function __construct(private readonly PortalContext $context) {}

    public function handle(Request $request, Closure $next, ?string $who = null): Response
    {
        $permission = $who === null ? null : PortalPermission::tryFrom($who);

        $allowed = match (true) {
            $who === 'self' => $this->context->isSelf(),
            // Documents are listed across every owner the account opens; each owner's own are filtered by their grant.
            $permission === PortalPermission::Documents => $this->context->canAny($permission),
            $permission !== null => $this->context->can($permission),
            default => $this->context->canManage(),
        };

        abort_unless($allowed, Response::HTTP_FORBIDDEN, __('portal.view_only_refused'));

        return $next($request);
    }
}
