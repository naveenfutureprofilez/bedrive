<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitPasswordAttempts
{
    protected $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->resolveRequestSignature($request);

        if ($this->limiter->tooManyAttempts($key, 5)) { // 5 attempts per minute
            $seconds = $this->limiter->availableIn($key);
            
            return response()->json([
                'message' => "Too many password attempts. Try again in {$seconds} seconds.",
                'retry_after' => $seconds,
            ], 429);
        }

        $this->limiter->hit($key, 60); // 60 seconds decay

        $response = $next($request);

        // Clear attempts on successful password verification
        if ($response->getStatusCode() === 200) {
            $this->limiter->clear($key);
        }

        return $response;
    }

    /**
     * Resolve request signature for rate limiting.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $hash = $request->route('hash');
        $ip = $request->ip();
        
        return "transfer_password_attempts:{$hash}:{$ip}";
    }
}
