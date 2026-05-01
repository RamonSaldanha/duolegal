<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'lives' => 5,
        ]);

        event(new Registered($user));

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Credenciais inválidas.',
            ], 401);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Desconectado com sucesso.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(new UserResource($request->user()));
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => 'Se o e-mail existir, você receberá um link de redefinição.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)], 422);
        }

        return response()->json(['message' => 'Senha redefinida com sucesso.']);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'E-mail já verificado.']);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Link de verificação reenviado.']);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $request->user()->id],
        ]);

        $user = $request->user();
        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }
        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }
        $user->save();

        return response()->json(new UserResource($user));
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['message' => 'Senha atual incorreta.'], 422);
        }

        $user->forceFill(['password' => Hash::make($validated['password'])])->save();

        return response()->json(['message' => 'Senha alterada com sucesso.']);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Senha incorreta.'], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Conta excluída.']);
    }
}
