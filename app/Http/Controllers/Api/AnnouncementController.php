<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnnouncementRequest;
use App\Http\Requests\UpdateAnnouncementRequest;
use App\Models\Announcement;
use App\Models\RoomMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AnnouncementController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GET ROOM ID HELPER
    |--------------------------------------------------------------------------
    */

    /**
     * Ambil room_id berdasarkan role user.
     * Admin: dari relasi room.
     * User: dari membership yang approved.
     *
     * @return int|null
     */
    private function getRoomId($user)
    {
        if ($user->role === User::ROLE_ADMIN) {
            return $user->room?->id;
        }

        $membership = RoomMember::where('user_id', $user->id)
            ->where('status', RoomMember::STATUS_APPROVED)
            ->latest()
            ->first();

        return $membership?->room_id;
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX — DAFTAR PENGUMUMAN
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | AMBIL ROOM ID
        |--------------------------------------------------------------------------
        */

        $roomId = $this->getRoomId($user);

        if (!$roomId) {

            return response()->json([
                'success' => false,
                'message' => 'Room tidak ditemukan'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | AMBIL DATA PENGUMUMAN
        |--------------------------------------------------------------------------
        */

        $announcements = Announcement::with('creator:id,name')
            ->where('room_id', $roomId)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar pengumuman',
            'data' => $announcements
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW — DETAIL PENGUMUMAN
    |--------------------------------------------------------------------------
    */

    public function show(Request $request, $id)
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | AMBIL ROOM ID
        |--------------------------------------------------------------------------
        */

        $roomId = $this->getRoomId($user);

        if (!$roomId) {

            return response()->json([
                'success' => false,
                'message' => 'Room tidak ditemukan'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | CARI PENGUMUMAN
        |--------------------------------------------------------------------------
        */

        $announcement = Announcement::with('creator:id,name')
            ->where('id', $id)
            ->where('room_id', $roomId)
            ->first();

        if (!$announcement) {

            return response()->json([
                'success' => false,
                'message' => 'Pengumuman tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail pengumuman',
            'data' => $announcement
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE — BUAT PENGUMUMAN BARU
    |--------------------------------------------------------------------------
    */

    public function store(StoreAnnouncementRequest $request)
    {
        $admin = $request->user();

        /*
        |--------------------------------------------------------------------------
        | AMBIL ROOM ADMIN
        |--------------------------------------------------------------------------
        */

        $room = $admin->room;

        if (!$room) {

            return response()->json([
                'success' => false,
                'message' => 'Room tidak ditemukan'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | BUAT PENGUMUMAN
        |--------------------------------------------------------------------------
        */

        $data = [
            'room_id' => $room->id,
            'title' => $request->title,
            'content' => $request->content,
            'created_by' => $admin->id
        ];

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('announcements', 'public');
        }

        $announcement = Announcement::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Pengumuman berhasil dibuat',
            'data' => $announcement
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE — UBAH PENGUMUMAN
    |--------------------------------------------------------------------------
    */

    public function update(UpdateAnnouncementRequest $request, $id)
    {
        $admin = $request->user();

        /*
        |--------------------------------------------------------------------------
        | AMBIL ROOM ADMIN
        |--------------------------------------------------------------------------
        */

        $room = $admin->room;

        if (!$room) {

            return response()->json([
                'success' => false,
                'message' => 'Room tidak ditemukan'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | CARI PENGUMUMAN MILIK ROOM
        |--------------------------------------------------------------------------
        */

        $announcement = Announcement::where('id', $id)
            ->where('room_id', $room->id)
            ->first();

        if (!$announcement) {

            return response()->json([
                'success' => false,
                'message' => 'Pengumuman tidak ditemukan'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE DATA
        |--------------------------------------------------------------------------
        */

        $data = $request->validated();

        if ($request->hasFile('image')) {
            // Hapus gambar lama jika ada
            if ($announcement->image_path) {
                Storage::disk('public')->delete($announcement->image_path);
            }
            
            // Simpan gambar baru
            $data['image_path'] = $request->file('image')->store('announcements', 'public');
        }

        $announcement->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Pengumuman berhasil diperbarui',
            'data' => $announcement
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY — HAPUS PENGUMUMAN (SOFT DELETE)
    |--------------------------------------------------------------------------
    */

    public function destroy(Request $request, $id)
    {
        $admin = $request->user();

        /*
        |--------------------------------------------------------------------------
        | AMBIL ROOM ADMIN
        |--------------------------------------------------------------------------
        */

        $room = $admin->room;

        if (!$room) {

            return response()->json([
                'success' => false,
                'message' => 'Room tidak ditemukan'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | CARI PENGUMUMAN MILIK ROOM
        |--------------------------------------------------------------------------
        */

        $announcement = Announcement::where('id', $id)
            ->where('room_id', $room->id)
            ->first();

        if (!$announcement) {

            return response()->json([
                'success' => false,
                'message' => 'Pengumuman tidak ditemukan'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | SOFT DELETE
        |--------------------------------------------------------------------------
        */

        $announcement->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pengumuman berhasil dihapus'
        ]);
    }
}
