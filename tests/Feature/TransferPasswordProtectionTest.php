<?php

namespace Tests\Feature;

use App\Models\Transfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransferPasswordProtectionTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
    }

    /** @test */
    public function it_can_create_a_password_protected_transfer()
    {
        $file = UploadedFile::fake()->create('test.txt', 100);
        $password = 'secure123';

        $response = $this->postJson('/api/v1/transfer', [
            'files' => [$file],
            'password' => $password,
            'password_protect' => true,
            'expires_in_days' => 7,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('transfers', [
            'is_password_protected' => true,
        ]);

        $transfer = Transfer::where('hash', $response->json('transfer.hash'))->first();
        $this->assertTrue($transfer->isPasswordProtected());
        $this->assertTrue($transfer->verifyPassword($password));
        $this->assertFalse($transfer->verifyPassword('wrongpassword'));
    }

    /** @test */
    public function it_can_create_a_transfer_without_password_protection()
    {
        $file = UploadedFile::fake()->create('test.txt', 100);

        $response = $this->postJson('/api/v1/transfer', [
            'files' => [$file],
            'password_protect' => false,
            'expires_in_days' => 7,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('transfers', [
            'is_password_protected' => false,
        ]);

        $transfer = Transfer::where('hash', $response->json('transfer.hash'))->first();
        $this->assertFalse($transfer->isPasswordProtected());
    }

    /** @test */
    public function it_requires_password_verification_for_protected_transfers()
    {
        $transfer = Transfer::factory()->create([
            'password_hash' => bcrypt('secret123'),
            'is_password_protected' => true,
        ]);

        // Attempt to access without password verification should fail
        $response = $this->getJson("/api/v1/transfer/{$transfer->hash}/download");
        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'password_required',
        ]);
    }

    /** @test */
    public function it_can_verify_password_for_protected_transfer()
    {
        $password = 'secret123';
        $transfer = Transfer::factory()->create([
            'password_hash' => bcrypt($password),
            'is_password_protected' => true,
        ]);

        $response = $this->postJson("/api/v1/transfer/{$transfer->hash}/verify-password", [
            'password' => $password,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'access_token',
            'session_token',
        ]);
    }

    /** @test */
    public function it_rejects_invalid_passwords()
    {
        $transfer = Transfer::factory()->create([
            'password_hash' => bcrypt('secret123'),
            'is_password_protected' => true,
        ]);

        $response = $this->postJson("/api/v1/transfer/{$transfer->hash}/verify-password", [
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Invalid password',
        ]);
    }

    /** @test */
    public function it_rate_limits_password_attempts()
    {
        $transfer = Transfer::factory()->create([
            'password_hash' => bcrypt('secret123'),
            'is_password_protected' => true,
        ]);

        // Make 6 failed attempts (limit is 5)
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson("/api/v1/transfer/{$transfer->hash}/verify-password", [
                'password' => 'wrongpassword',
            ]);
        }

        // The 6th attempt should be rate limited
        $response->assertStatus(429);
        $response->assertJsonStructure([
            'message',
            'retry_after',
        ]);
    }

    /** @test */
    public function it_allows_access_to_non_protected_transfers()
    {
        $transfer = Transfer::factory()->create([
            'is_password_protected' => false,
        ]);

        $response = $this->getJson("/api/v1/transfer/{$transfer->hash}");
        $response->assertStatus(200);
        $response->assertJson([
            'is_password_protected' => false,
        ]);
    }

    /** @test */
    public function it_validates_password_requirements()
    {
        $file = UploadedFile::fake()->create('test.txt', 100);

        $response = $this->postJson('/api/v1/transfer', [
            'files' => [$file],
            'password' => '123', // Too short
            'password_protect' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    /** @test */
    public function password_is_required_when_protection_is_enabled()
    {
        $file = UploadedFile::fake()->create('test.txt', 100);

        $response = $this->postJson('/api/v1/transfer', [
            'files' => [$file],
            'password_protect' => true,
            // No password provided
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }
}
