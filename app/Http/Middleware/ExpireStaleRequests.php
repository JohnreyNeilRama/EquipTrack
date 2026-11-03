<?php

namespace App\Http\Middleware;

use App\Models\BorrowRequest;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

// Pending requests whose due date's return deadline has passed become Expired
// before any signed-in page or feed is built, so they leave review queues,
// counts and alerts without needing the scheduler. Runs at most once a minute.
class ExpireStaleRequests
{
    private const EVERY_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        if (Cache::add('equiptrack:expire-stale-requests', true, self::EVERY_SECONDS)) {
            BorrowRequest::expireStale();
        }

        return $next($request);
    }
}
