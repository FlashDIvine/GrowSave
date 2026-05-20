<?php

namespace App\Http\Middleware;

use App\Models\Room;
use App\Models\RoomMember;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApprovedUserMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | BELUM LOGIN
        |--------------------------------------------------------------------------
        */

        if (!$user) {

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | ADMIN BYPASS
        |--------------------------------------------------------------------------
        */

        if ($user->role === 'admin') {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | CEK MEMBERSHIP
        |--------------------------------------------------------------------------
        */

        $membership = RoomMember::with('room')
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        /*
        |--------------------------------------------------------------------------
        | BELUM PUNYA ROOM
        |--------------------------------------------------------------------------
        */

        if (!$membership) {

            return response()->json([
                'success' => false,
                'message' => 'Anda belum memiliki room'
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | STATUS PENDING
        |--------------------------------------------------------------------------
        */

        if ($membership->status === RoomMember::STATUS_PENDING) {

            return response()->json([
                'success' => false,
                'message' => 'Menunggu persetujuan admin'
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | STATUS REJECTED
        |--------------------------------------------------------------------------
        */

        if ($membership->status === RoomMember::STATUS_REJECTED) {

            return response()->json([
                'success' => false,
                'message' => 'Permintaan masuk ditolak admin'
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | ROOM TIDAK ADA
        |--------------------------------------------------------------------------
        */

        if (!$membership->room) {

            return response()->json([
                'success' => false,
                'message' => 'Room tidak ditemukan'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | ROOM NONAKTIF
        |--------------------------------------------------------------------------
        */

        if ($membership->room->status !== Room::STATUS_ACTIVE) {

            return response()->json([
                'success' => false,
                'message' => 'Room nonaktif'
            ], 403);
        }

        return $next($request);
    }
}