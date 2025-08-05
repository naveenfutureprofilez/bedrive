<?php

namespace Database\Factories;

use App\Models\TransferFile;
use App\Models\Transfer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TransferFileFactory extends Factory
{
    protected $model = TransferFile::class;

    public function definition(): array
    {
        $extensions = ['pdf', 'jpg', 'png', 'doc', 'docx', 'txt', 'zip'];
        $extension = $this->faker->randomElement($extensions);
        $filename = $this->faker->slug() . '.' . $extension;

        return [
            'transfer_id' => Transfer::factory(),
            'original_name' => $filename,
            'size' => $this->faker->numberBetween(1024, 10485760), // 1KB to 10MB
            'mime_type' => $this->getMimeType($extension),
            'storage_path' => 'transfers/' . Str::random(40) . '/' . $filename,
            'upload_key' => Str::random(32),
        ];
    }

    /**
     * Create a small file for testing.
     */
    public function small(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'size' => $this->faker->numberBetween(1024, 102400), // 1KB to 100KB
            ];
        });
    }

    /**
     * Create a large file for testing.
     */
    public function large(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'size' => $this->faker->numberBetween(104857600, 1073741824), // 100MB to 1GB
            ];
        });
    }

    /**
     * Create a PDF file.
     */
    public function pdf(): Factory
    {
        return $this->state(function (array $attributes) {
            $filename = $this->faker->slug() . '.pdf';
            return [
                'original_name' => $filename,
                'mime_type' => 'application/pdf',
                'storage_path' => 'transfers/' . Str::random(40) . '/' . $filename,
            ];
        });
    }

    /**
     * Create an image file.
     */
    public function image(): Factory
    {
        return $this->state(function (array $attributes) {
            $extension = $this->faker->randomElement(['jpg', 'png', 'gif']);
            $filename = $this->faker->slug() . '.' . $extension;
            return [
                'original_name' => $filename,
                'mime_type' => 'image/' . ($extension === 'jpg' ? 'jpeg' : $extension),
                'storage_path' => 'transfers/' . Str::random(40) . '/' . $filename,
            ];
        });
    }

    /**
     * Get MIME type for file extension.
     */
    private function getMimeType(string $extension): string
    {
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain',
            'zip' => 'application/zip',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
}
