<?php

namespace App\Http\Middleware;

use App\Models\Avatar;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveAvatarFromHost
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $suffix = strtolower(config('avatar.public_domain_suffix'));
        $slug = config('avatar.local_default_slug');

        if ($host !== 'localhost' && ! filter_var($host, FILTER_VALIDATE_IP) && str_ends_with($host, ".{$suffix}")) {
            $slug = substr($host, 0, -strlen(".{$suffix}"));
        }

        abort_unless(is_string($slug) && preg_match('/^[a-z0-9-]+$/', $slug) === 1, 404);

        $avatar = Avatar::where('slug', $slug)->where('status', 'published')->first();
        abort_unless($avatar, 404);

        $request->attributes->set('avatar', $avatar);

        return $next($request);
    }
}
