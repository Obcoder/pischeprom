<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Mobile\MobileAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class MobileAuthController extends Controller
{
    public function login(Request $request, TwoFactorAuthenticationProvider $twoFactor): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
            'device_name' => ['required', 'string', 'max:100'],
            'two_factor_code' => ['nullable', 'string', 'regex:/^[0-9]{6}$/D'],
            'recovery_code' => ['nullable', 'string', 'max:100'],
        ]);

        return DB::transaction(function () use ($data, $twoFactor): JsonResponse {
            // The lock also makes recovery-code consumption atomic across devices.
            $user = User::query()->where('email', Str::lower(trim($data['email'])))
                ->lockForUpdate()->first();

            if (! $user || ! Hash::check($data['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'email' => ['Неверный адрес электронной почты или пароль.'],
                ]);
            }

            abort_unless(MobileAccess::allowed($user), 403,
                'Доступ разрешён подтверждённым сотрудникам с правом складских операций.');

            if ($user->hasEnabledTwoFactorAuthentication()) {
                $recovery = $data['recovery_code'] ?? null;
                $code = $data['two_factor_code'] ?? null;

                if (! $recovery && ! $code) {
                    return response()->json([
                        'message' => 'Введите код двухфакторной аутентификации или резервный код.',
                        'code' => 'two_factor_required',
                        'errors' => ['two_factor_code' => ['Требуется подтверждение входа.']],
                    ], 422)->header('Cache-Control', 'no-store, private');
                }

                if ($recovery) {
                    $matched = collect($user->two_factor_recovery_codes ? $user->recoveryCodes() : [])
                        ->first(fn (string $stored): bool => hash_equals($stored, $recovery));

                    if (! $matched) {
                        throw ValidationException::withMessages(['recovery_code' => ['Неверный резервный код.']]);
                    }

                    $user->replaceRecoveryCode($matched);
                } elseif (! $twoFactor->verify(Fortify::currentEncrypter()->decrypt($user->two_factor_secret), $code)) {
                    throw ValidationException::withMessages(['two_factor_code' => ['Неверный код подтверждения.']]);
                }
            }

            $expiresAt = now()->addMinutes(max(1, min(10080, (int) config('mobile.token_ttl_minutes', 480))));
            $token = $user->createToken('mobile:'.$data['device_name'], [MobileAccess::ABILITY], $expiresAt);

            return response()->json([
                'token' => $token->plainTextToken,
                'expires_at' => $expiresAt->toIso8601String(),
                ...$this->profile($user),
            ])->header('Cache-Control', 'no-store, private');
        });
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->profile($request->user()));
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    private function profile(User $user): array
    {
        return [
            'user' => $user->only(['id', 'name', 'email']),
            'abilities' => [MobileAccess::ABILITY],
        ];
    }
}
