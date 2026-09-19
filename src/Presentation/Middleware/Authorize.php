<?php

declare(strict_types=1);

namespace MediCareMini\Presentation\Middleware;

use MediCareMini\Domain\Entity\User;
use MediCareMini\Domain\Exception\HttpException;
use MediCareMini\Infrastructure\Support\Logger;
use MediCareMini\Presentation\Http\Request;
use MediCareMini\Presentation\Http\Response;

/**
 * Permission gate for a route.
 *
 * Instantiated per permission and registered under a name like
 * "can:payments.verify", so the requirement is visible in the route table
 * rather than buried at the top of a controller method. Reading routes.php
 * is then enough to audit who can reach what.
 *
 * Always runs after Authenticate, which guarantees a user is present.
 */
final readonly class Authorize
{
    public function __construct(
        private string $permission,
        private Logger $logger,
    ) {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $user = $GLOBALS['medicaremini_current_user'] ?? null;

        if (!$user instanceof User) {
            // Authenticate should have caught this; reaching here means the
            // route was misconfigured, so fail closed.
            throw HttpException::unauthorized();
        }

        if (!$user->can($this->permission)) {
            $this->logger->warning('Permission denied', [
                'user_id'    => $user->id,
                'role'       => $user->roleLabel,
                'permission' => $this->permission,
                'path'       => $request->path,
            ]);

            throw HttpException::forbidden();
        }

        return $next($request);
    }
}
