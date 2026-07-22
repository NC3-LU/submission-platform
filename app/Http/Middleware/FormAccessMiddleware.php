<?php

namespace App\Http\Middleware;

use App\Models\Form;
use App\Models\FormAccessLink;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FormAccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $form = $request->route('form');

        if (! $form instanceof Form) {
            return $next($request);
        }

        switch ($form->visibility) {
            case 'public':
                return $next($request);

            case 'authenticated':
                if (! auth()->check()) {
                    return redirect()->route('login');
                }

                return $next($request);

            case 'private':
                // Check if user is authenticated and has permission
                if (auth()->check()) {
                    $user = auth()->user();
                    if ($user->isAdmin() ||
                        $user->id === $form->user_id ||
                        $form->appointedUsers->contains($user->id)) {
                        return $next($request);
                    }
                }

                // Get session data
                $sessionKey = 'form_access_'.$form->id;
                if ($request->session()->has($sessionKey)) {
                    $sessionAccess = $request->session()->get($sessionKey);

                    // Ensure session data is in the expected format
                    if (! is_array($sessionAccess) || ! isset($sessionAccess['token'])) {
                        $request->session()->forget($sessionKey);

                        return redirect()->route('homepage')
                            ->with('error', 'Invalid access format. Please use the access link again.');
                    }

                    // Check expiration if set
                    if (isset($sessionAccess['expires_at']) && $sessionAccess['expires_at'] < now()->timestamp) {
                        $request->session()->forget($sessionKey);

                        return redirect()->route('homepage')
                            ->with('error', 'Your access to this form has expired.');
                    }

                    // Verify the access link is still valid
                    $accessLink = FormAccessLink::findValidByToken($sessionAccess['token']);
                    if (! $accessLink) {
                        $request->session()->forget($sessionKey);

                        return redirect()->route('homepage')
                            ->with('error', 'The access link for this form is no longer valid.');
                    }

                    return $next($request);
                }

                abort(403, 'You do not have permission to access this form.');
        }

        return $next($request);
    }
}
