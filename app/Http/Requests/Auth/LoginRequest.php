<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('remember')) {
            $this->merge([
                'remember' => $this->boolean('remember'),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::validate($this->only('email', 'password'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = User::where('email', $this->email)->first();

        if ($user && $this->hasActiveSession($user)) {
            throw ValidationException::withMessages([
                'email' => __('auth.active_session'),
            ]);
        }

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Determina si el usuario tiene una sesión activa en otro dispositivo.
     */
    protected function hasActiveSession(User $user): bool
    {
        try {
            if (! Schema::hasColumn('users', 'current_session_id')) {
                return false;
            }

            $currentSessionId = $this->session()->getId();

            // 1. Verificación primaria en base de datos si el driver es database y la tabla sessions existe
            if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
                $lifetime = (int) config('session.lifetime', 120);
                $threshold = now()->subMinutes($lifetime)->timestamp;

                $hasActiveDbSession = DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $currentSessionId)
                    ->where('last_activity', '>=', $threshold)
                    ->exists();

                if ($hasActiveDbSession) {
                    return true;
                }

                // Si expiró o ya no existe en la tabla sessions, limpiar current_session_id
                if (! empty($user->current_session_id)) {
                    $user->forceFill(['current_session_id' => null])->saveQuietly();
                }

                return false;
            }

            // 2. Verificación secundaria para otros drivers (testing / array / file)
            return ! empty($user->current_session_id) && $user->current_session_id !== $this->session()->get('auth_session_token');
        } catch (\Throwable $e) {
            Log::warning('Error comprobando sesión activa en LoginRequest: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
