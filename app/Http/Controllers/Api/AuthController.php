<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Room;
use App\Models\RoomMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | REGISTER
    |--------------------------------------------------------------------------
    */

    public function register(RegisterRequest $request)
    {
        // Cek jika user join room
        $room = null;

        if ($request->role === User::ROLE_USER) {

            if (!$request->room_code) {
                return response()->json([
                    'success' => false,
                    'message' => 'Room code wajib diisi'
                ], 422);
            }

            $room = Room::where('room_code', $request->room_code)
                ->where('status', Room::STATUS_ACTIVE)
                ->first();

            if (!$room) {
                return response()->json([
                    'success' => false,
                    'message' => 'Room tidak ditemukan atau nonaktif'
                ], 404);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | CREATE USER
        |--------------------------------------------------------------------------
        */

        $user = User::create([
            'name' => $request->name,

            'email' => $request->email,

            'password' => Hash::make($request->password),

            'role' => $request->role
        ]);

        /*
        |--------------------------------------------------------------------------
        | ADMIN CREATE ROOM
        |--------------------------------------------------------------------------
        */

        if ($user->role === User::ROLE_ADMIN) {

            do {
                $roomCode = 'ROOM-' . Str::upper(Str::random(6));
            } while (Room::where('room_code', $roomCode)->exists());

            Room::create([
                'admin_id' => $user->id,

                'room_name' => $user->name . "'s Room",

                'room_code' => $roomCode,

                'description' => null,

                'status' => Room::STATUS_ACTIVE
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | USER JOIN ROOM
        |--------------------------------------------------------------------------
        */

        if ($user->role === User::ROLE_USER) {

            RoomMember::create([
                'room_id' => $room->id,

                'user_id' => $user->id,

                'status' => RoomMember::STATUS_PENDING
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | CREATE TOKEN
        |--------------------------------------------------------------------------
        */

        $token = $user->createToken('auth-token')->plainTextToken;

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Register berhasil',
            'data' => [
                'token' => $token,
                'user' => $user
            ]
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | LOGIN
    |--------------------------------------------------------------------------
    */

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        // User tidak ditemukan
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah'
            ], 401);
        }

        // Password salah
        if (!Hash::check($request->password, $user->password)) {

            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah'
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | SINGLE DEVICE LOGIN
        |--------------------------------------------------------------------------
        */

        $user->tokens()->delete();

        /*
        |--------------------------------------------------------------------------
        | CREATE TOKEN
        |--------------------------------------------------------------------------
        */

        $token = $user->createToken('auth-token')->plainTextToken;

        /*
        |--------------------------------------------------------------------------
        | MEMBERSHIP STATUS
        |--------------------------------------------------------------------------
        */

        $membership = RoomMember::where('user_id', $user->id)
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'token' => $token,

                'user' => $user,

                'membership_status' => $membership?->status
            ]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | LOGOUT
    |--------------------------------------------------------------------------
    */

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ]);
    }
}