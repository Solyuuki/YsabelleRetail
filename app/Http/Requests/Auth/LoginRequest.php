<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const ADMIN_PORTAL = 'admin';

    private const STOREFRONT_PORTAL = 'storefront';

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim($this->string('email')->toString())),
            'remember' => $this->boolean('remember'),
            'portal' => $this->normalizePortal($this->input('portal')),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
            'portal' => ['nullable', 'string'],
        ];
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Too many login attempts. Try again in {$seconds} seconds.",
        ]);
    }

    public function hitRateLimiter(): void
    {
        RateLimiter::hit($this->throttleKey(), 60);
    }

    public function clearRateLimiter(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    public function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower($this->string('email')->toString())
            .'|'.$this->ip()
            .'|'.$this->loginPortal()
        );
    }

    public function loginPortal(): string
    {
        $explicitPortal = $this->normalizePortal($this->input('portal'));

        if ($explicitPortal !== null) {
            return $explicitPortal;
        }

        return $this->session()->get('auth.login_portal') === self::ADMIN_PORTAL
            ? self::ADMIN_PORTAL
            : self::STOREFRONT_PORTAL;
    }

    public function candidateUser(): ?User
    {
        return User::query()
            ->where('email', $this->string('email')->toString())
            ->first();
    }

    private function normalizePortal(mixed $portal): ?string
    {
        if (! is_string($portal)) {
            return null;
        }

        $portal = Str::lower(trim($portal));

        return match ($portal) {
            self::ADMIN_PORTAL => self::ADMIN_PORTAL,
            self::STOREFRONT_PORTAL => self::STOREFRONT_PORTAL,
            default => null,
        };
    }
}
