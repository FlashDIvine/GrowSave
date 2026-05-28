<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Set the admin secret code explicitly for testing
        Config::set('app.admin_secret_code', 'growsave2026');
    }

    /**
     * Test admin registration with invalid admin code.
     */
    public function test_register_admin_with_invalid_code_fails(): void
    {
        $payload = [
            'name' => 'John Admin',
            'email' => 'john.admin@example.com',
            'password' => 'password123',
            'admin_code' => 'wrongcode',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Admin code tidak valid',
            ]);

        // Assert database is empty
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * Test admin registration with correct admin code.
     */
    public function test_register_admin_with_correct_code_succeeds(): void
    {
        $payload = [
            'name' => 'John Admin',
            'email' => 'john.admin@example.com',
            'password' => 'password123',
            'admin_code' => 'growsave2026',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Register berhasil',
            ])
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role',
                    ]
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john.admin@example.com',
            'role' => User::ROLE_ADMIN,
        ]);

        $user = User::where('email', 'john.admin@example.com')->first();

        // Assert room was automatically created for admin
        $this->assertDatabaseHas('rooms', [
            'admin_id' => $user->id,
            'room_name' => "John Admin's Room",
            'status' => Room::STATUS_ACTIVE,
        ]);
    }

    /**
     * Test user registration with missing room code.
     */
    public function test_register_user_without_room_code_fails(): void
    {
        $payload = [
            'name' => 'Jane User',
            'email' => 'jane.user@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Room code wajib diisi',
            ]);

        // Assert database is empty
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * Test user registration with invalid room code.
     */
    public function test_register_user_with_invalid_room_code_fails(): void
    {
        $payload = [
            'name' => 'Jane User',
            'email' => 'jane.user@example.com',
            'password' => 'password123',
            'room_code' => 'ROOM-INVALID',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Room tidak ditemukan atau nonaktif',
            ]);

        // Assert database is empty
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * Test user registration with valid room code.
     */
    public function test_register_user_with_valid_room_code_succeeds(): void
    {
        // 1. Create an admin and their room first
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        $room = Room::create([
            'admin_id' => $admin->id,
            'room_name' => "Admin's Room",
            'room_code' => 'ROOM-ABC123',
            'status' => Room::STATUS_ACTIVE,
        ]);

        // 2. Register a new user trying to join the room
        $payload = [
            'name' => 'Jane User',
            'email' => 'jane.user@example.com',
            'password' => 'password123',
            'room_code' => 'ROOM-ABC123',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Register berhasil',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'jane.user@example.com',
            'role' => User::ROLE_USER,
        ]);

        $user = User::where('email', 'jane.user@example.com')->first();

        // Assert user was registered as pending member in the room
        $this->assertDatabaseHas('room_members', [
            'room_id' => $room->id,
            'user_id' => $user->id,
            'status' => RoomMember::STATUS_PENDING,
        ]);
    }
}
