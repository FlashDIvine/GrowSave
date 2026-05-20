<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | UPDATE PROFIL
    |--------------------------------------------------------------------------
    */

    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | UPDATE NAMA & DATA KONTAK
        |--------------------------------------------------------------------------
        */

        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('whatsapp_number')) {
            $user->whatsapp_number = $request->whatsapp_number;
        }

        if ($request->has('house_block')) {
            $user->house_block = $request->house_block;
        }

        if ($request->has('house_number')) {
            $user->house_number = $request->house_number;
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE PASSWORD
        |--------------------------------------------------------------------------
        */

        if ($request->filled('password')) {

            $user->password = Hash::make($request->password);
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE FOTO PROFIL
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('profile_photo')) {

            // Hapus foto lama jika ada
            if ($user->profile_photo) {

                Storage::disk('public')->delete($user->profile_photo);
            }

            // Simpan foto baru
            $path = $request->file('profile_photo')
                ->store('profile-photos', 'public');

            $user->profile_photo = $path;
        }

        /*
        |--------------------------------------------------------------------------
        | SIMPAN PERUBAHAN
        |--------------------------------------------------------------------------
        */

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui',
            'data' => $user
        ]);
    }
}
