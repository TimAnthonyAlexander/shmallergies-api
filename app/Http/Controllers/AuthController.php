<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user.
     *
     * @group Authentication
     *
     * @bodyParam name string required The user's name. Example: John Doe
     * @bodyParam email string required The user's email address. Example: john@example.com
     * @bodyParam password string required The user's password (min 8 characters). Example: password123
     * @bodyParam password_confirmation string required Password confirmation. Example: password123
     *
     * @response 201 {
     *   "message": "User created successfully. Please check your email to verify your account.",
     *   "user": {
     *     "id": 1,
     *     "name": "John Doe",
     *     "email": "john@example.com",
     *     "email_verified_at": null
     *   }
     * }
     */
    public function signup(Request $request): JsonResponse
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Send email verification notification
        event(new Registered($user));

        return response()->json([
            'message' => 'User created successfully. Please check your email to verify your account.',
            'user'    => $user->only(['id', 'name', 'email', 'email_verified_at']),
        ], 201);
    }

    /**
     * Login user.
     *
     * @group Authentication
     *
     * @bodyParam email string required The user's email address. Example: john@example.com
     * @bodyParam password string required The user's password. Example: password123
     *
     * @response 200 {
     *   "message": "Login successful",
     *   "user": {
     *     "id": 1,
     *     "name": "John Doe",
     *     "email": "john@example.com"
     *   },
     *   "token": "1|abc123token"
     * }
     * @response 422 {
     *   "message": "The provided credentials are incorrect.",
     *   "errors": {
     *     "email": ["The provided credentials are incorrect."]
     *   }
     * }
     * @response 403 {
     *   "message": "Your email address is not verified.",
     *   "errors": {
     *     "email": ["Your email address is not verified."]
     *   }
     * }
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = User::where('email', $request->email)->first();

        // Check if email is verified
        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Your email address is not verified.',
                'errors'  => [
                    'email' => ['Your email address is not verified.'],
                ],
            ], 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user'    => $user,
            'token'   => $token,
        ]);
    }

    /**
     * Logout user.
     *
     * @group Authentication
     *
     * @authenticated
     *
     * @response 200 {
     *   "message": "Logged out successfully"
     * }
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Verify email address.
     *
     * @group Authentication
     *
     * @urlParam id integer required The user ID. Example: 1
     * @urlParam hash string required The email verification hash.
     *
     * @queryParam expires integer required The expiration timestamp.
     * @queryParam signature string required The signature for verification.
     *
     * @response 200 {
     *   "message": "Email verified successfully."
     * }
     * @response 400 {
     *   "message": "Invalid verification link."
     * }
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $user = User::find($request->route('id'));

        if (! $user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Check if the hash matches
        if (! hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash'))) {
            return response()->json(['message' => 'Invalid verification link.'], 400);
        }

        if (! $request->hasValidSignature()) {
            return response()->json(['message' => 'Invalid verification link.'], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 200);
        }

        $user->markEmailAsVerified();

        return response()->json(['message' => 'Email verified successfully.'], 200);
    }

    /**
     * Resend email verification.
     *
     * @group Authentication
     *
     * @bodyParam email string required The user's email address. Example: john@example.com
     *
     * @response 200 {
     *   "message": "Verification email sent."
     * }
     * @response 400 {
     *   "message": "Email is already verified."
     * }
     * @response 404 {
     *   "message": "User not found."
     * }
     */
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email is already verified.'], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification email sent.'], 200);
    }
}
