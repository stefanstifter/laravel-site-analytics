<?php

declare(strict_types=1);

namespace Stifter\SiteAnalytics\Session;

use Illuminate\Http\Request;

/**
 * Identifies a session by a one-way hash so live session IDs are never stored in analytics data.
 */
final class SessionHash
{
    public static function fromRequest(Request $request): ?string
    {
        return $request->hasSession() ? hash('sha256', $request->session()->getId()) : null;
    }
}
