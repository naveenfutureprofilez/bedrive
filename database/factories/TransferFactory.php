<?php

namespace Database\Factories;

use App\Models\Transfer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TransferFactory extends Factory
{
    protected $model = Transfer::class;

    public function definition(): array
    {
        return [
            'uuid' => $this->faker->uuid(),
            'hash' => Str::random(12),
            'sender_email' => $this->faker->email(),
            'sender_name' => $this->faker->name(),
            'recipient_emails' => [$this->faker->email(), $this->faker->email()],
            'message' => $this->faker->sentence(),
            'password_hash' => null,
            'expiry_at' => $this->faker->dateTimeBetween('+1 day', '+7 days'),
            'download_count' => 0,
            'max_downloads' => $this->faker->numberBetween(1, 10),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'is_password_protected' => false,
            'total_size' => $this->faker->numberBetween(1024, 1073741824), // 1KB to 1GB
            'status' => 'completed',
            'completed_at' => now(),
        ];
    }

    /**
     * Indicate that the transfer is expired.
     */
    public function expired(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'expiry_at' => $this->faker->dateTimeBetween('-7 days', '-1 day'),
            ];
        });
    }

    /**
     * Indicate that the transfer is password protected.
     */
    public function passwordProtected(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'password_hash' => bcrypt('secret'),
                'is_password_protected' => true,
            ];
        });
    }

    /**
     * Indicate that the transfer is active (not expired).
     */
    public function active(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'expiry_at' => $this->faker->dateTimeBetween('+1 day', '+7 days'),
            ];
        });
    }
}
