<?php

namespace App\Http\Middleware;

use App\Models\Transfer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CheckTransferPassword
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Get transfer UUID or hash from route parameters
        $uuid = $request->route('uuid');
        $hash = $request->route('hash');
        
        if (!$uuid && !$hash) {
            return $next($request);
        }

        // Find transfer by UUID or hash
        $transfer = null;
        if ($uuid) {
            $transfer = Transfer::where('uuid', $uuid)->first();
        } elseif ($hash) {
            $transfer = Transfer::where('hash', $hash)->first();
        }

        if (!$transfer) {
            return response()->json(['message' => 'Transfer not found'], 404);
        }

        // If transfer is not password protected, continue
if (!$transfer->isPasswordProtected()) {
            $request->session()->put('transfer_verified_' . ($uuid ?? $hash), true);
            return $next($request);
        }

        // Check if session has valid password token for this transfer
        $sessionKey = 'transfer_password_verified_' . ($uuid ?? $hash);
        if ($request->session()->has($sessionKey)) {
            $sessionData = $request->session()->get($sessionKey);
            // Check if session token is still valid (24 hours)
            if (isset($sessionData['expires_at']) && now()->isBefore($sessionData['expires_at'])) {
                return $next($request);
            } else {
                // Remove expired session
                $request->session()->forget($sessionKey);
            }
        }

        // Check if request has valid signature (from password verification)
        if ($request->hasValidSignature()) {
            return $next($request);
        }

        // Check for password in request headers (for API access)
        if ($request->hasHeader('X-Transfer-Password')) {
            $password = $request->header('X-Transfer-Password');
            if ($transfer->verifyPassword($password)) {
                // Store verified session token
                $request->session()->put($sessionKey, [
                    'verified_at' => now(),
                    'expires_at' => now()->addHours(24),
                ]);
                return $next($request);
            }
        }

        // Return 403 with password required message
        return response()->json([
            'message' => 'This transfer is password protected',
            'error' => 'password_required',
            'transfer_id' => $uuid ?? $hash,
        ], 403);
    }
}
