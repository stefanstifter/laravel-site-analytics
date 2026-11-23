<?php

declare(strict_types=1);

namespace Stifter\SiteAnalytics\Session;

use AndreasElia\Analytics\Contracts\SessionProvider;
use Illuminate\Http\Request;

/**
 * Identifies page view sessions by a one-way hash so live session IDs are never stored in analytics data.
 */
final class HashedSessionProvider implements SessionProvider
{
    public function get(Request $request): string
    {
        return hash('sha256', $request->session()->getId());
    }
}
