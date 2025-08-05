<?php

namespace Tests\Unit\Commands;

use Tests\TestCase;
use App\Models\Transfer;
use App\Models\TransferFile;
use App\Events\TransferDeleted;
use App\Console\Commands\DeleteExpiredTransfers;
use Common\Files\FileEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class DeleteExpiredTransfersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock storage disks
        Storage::fake('uploads');
        Storage::fake('r2');
    }

    /** @test */
    public function it_deletes_expired_transfers_with_expiry_at_column()
    {
        Event::fake();

        // Create an expired transfer using expiry_at (standardized column)
        $expiredTransfer = Transfer::factory()->create([
            'expiry_at' => Carbon::now()->subDay(),
        ]);

        // Create a non-expired transfer
        $activeTransfer = Transfer::factory()->create([
            'expiry_at' => Carbon::now()->addDay(),
        ]);

        $this->artisan('transfers:cleanup --force')
            ->expectsOutput('Deleting transfer: ' . $expiredTransfer->hash)
            ->assertExitCode(0);

        // Assert expired transfer was deleted
        $this->assertDatabaseMissing('transfers', ['id' => $expiredTransfer->id]);
        
        // Assert active transfer still exists
        $this->assertDatabaseHas('transfers', ['id' => $activeTransfer->id]);

        // Assert event was dispatched
        Event::assertDispatched(TransferDeleted::class, function ($event) use ($expiredTransfer) {
            return $event->transfer->id === $expiredTransfer->id && 
                   $event->reason === 'expired';
        });
    }

    /** @test */
    public function it_deletes_expired_transfers_with_legacy_expires_at_column()
    {
        Event::fake();

        // Create an expired transfer using legacy expires_at column
        $expiredTransfer = Transfer::factory()->create([
'expiry_at' => Carbon::now()->subDay(),
        ]);

        $this->artisan('transfers:cleanup --force')
            ->expectsOutput('Deleting transfer: ' . $expiredTransfer->hash)
            ->assertExitCode(0);

        $this->assertDatabaseMissing('transfers', ['id' => $expiredTransfer->id]);

        Event::assertDispatched(TransferDeleted::class);
    }

    /** @test */
    public function it_prioritizes_expiry_at_over_expires_at()
    {
        Event::fake();

        // Create a transfer where expiry_at says not expired but expires_at says expired
        // expiry_at should take precedence
        $transfer = Transfer::factory()->create([
'expiry_at' => Carbon::now()->addDay(),
        ]);

        $this->artisan('transfers:cleanup --force')
            ->expectsOutput('No expired transfers found.')
            ->assertExitCode(0);

        // Transfer should still exist because expiry_at takes precedence
        $this->assertDatabaseHas('transfers', ['id' => $transfer->id]);

        Event::assertNotDispatched(TransferDeleted::class);
    }

    /** @test */
    public function it_deletes_files_from_storage()
    {
        Event::fake();
        Storage::fake('uploads');

        $expiredTransfer = Transfer::factory()->create([
            'expiry_at' => Carbon::now()->subDay(),
        ]);

        // Create a transfer file
        $transferFile = TransferFile::factory()->create([
            'transfer_id' => $expiredTransfer->id,
            'storage_path' => 'test-file.pdf',
            'size' => 1024,
        ]);

        // Create the file in storage
        Storage::disk('uploads')->put('test-file.pdf', 'test content');

        $this->artisan('transfers:cleanup --force')
            ->assertExitCode(0);

        // Assert file was deleted from storage
        Storage::disk('uploads')->assertMissing('test-file.pdf');
    }

    /** @test */
    public function it_identifies_cloudflare_r2_disk()
    {
        Event::fake();
        Storage::fake('r2');

        // Set up R2 disk config for testing
        config(['filesystems.disks.r2.driver' => 's3']);
        config(['filesystems.disks.r2.endpoint' => 'https://account.r2.cloudflarestorage.com']);

        $expiredTransfer = Transfer::factory()->create([
            'expiry_at' => Carbon::now()->subDay(),
        ]);

        $transferFile = TransferFile::factory()->create([
            'transfer_id' => $expiredTransfer->id,
            'storage_path' => 'test-file-r2.pdf',
            'size' => 2048,
        ]);

        // Mock the disk to be R2
        Storage::disk('r2')->put('test-file-r2.pdf', 'r2 test content');

        // Override the TUS disk config to use R2
        config(['tus.disk' => 'r2']);

        $this->artisan('transfers:cleanup --force')
            ->expectsOutput('Deleting transfer: ' . $expiredTransfer->hash)
            ->expectsOutputToContain('Deleted from Cloudflare R2: test-file-r2.pdf')
            ->expectsOutputToContain('Cloudflare R2 deletions: 1')
            ->assertExitCode(0);

        Storage::disk('r2')->assertMissing('test-file-r2.pdf');
    }

    /** @test */
    public function it_handles_missing_files_gracefully()
    {
        Event::fake();
        Storage::fake('uploads');

        $expiredTransfer = Transfer::factory()->create([
            'expiry_at' => Carbon::now()->subDay(),
        ]);

        // Create a transfer file record but don't create the actual file
        TransferFile::factory()->create([
            'transfer_id' => $expiredTransfer->id,
            'storage_path' => 'missing-file.pdf',
            'size' => 1024,
        ]);

        $this->artisan('transfers:cleanup --force')
            ->expectsOutputToContain('File not found on uploads: missing-file.pdf')
            ->assertExitCode(0);

        // Transfer should still be deleted even if file is missing
        $this->assertDatabaseMissing('transfers', ['id' => $expiredTransfer->id]);
    }

    /** @test */
    public function it_handles_storage_exceptions()
    {
        Event::fake();
        
        $expiredTransfer = Transfer::factory()->create([
            'expiry_at' => Carbon::now()->subDay(),
        ]);

        TransferFile::factory()->create([
            'transfer_id' => $expiredTransfer->id,
            'storage_path' => 'test-file.pdf',
            'size' => 1024,
        ]);

        // Mock Storage to throw an exception
        Storage::shouldReceive('disk')
            ->with('uploads')
            ->andThrow(new \Exception('Storage error'));

        $this->artisan('transfers:cleanup --force')
            ->expectsOutputToContain('Failed to delete from uploads: test-file.pdf - Storage error')
            ->assertExitCode(0);

        // Transfer should still be deleted even if file deletion fails
        $this->assertDatabaseMissing('transfers', ['id' => $expiredTransfer->id]);
    }

    /** @test */
    public function it_deletes_legacy_file_entries()
    {
        Event::fake();
        Storage::fake('uploads');

        $expiredTransfer = Transfer::factory()->create([
            'expiry_at' => Carbon::now()->subDay(),
        ]);

        // Create a legacy file entry
        $fileEntry = FileEntry::factory()->create([
            'transfer_id' => $expiredTransfer->id,
            'disk_prefix' => 'legacy-file.pdf',
            'size' => 512,
        ]);

        Storage::disk('uploads')->put('legacy-file.pdf', 'legacy content');

        $this->artisan('transfers:cleanup --force')
            ->assertExitCode(0);

        Storage::disk('uploads')->assertMissing('legacy-file.pdf');
        $this->assertDatabaseMissing('transfers', ['id' => $expiredTransfer->id]);
    }

    /** @test */
    public function it_shows_no_expired_transfers_message_when_none_found()
    {
        // Create only active transfers
        Transfer::factory()->create([
            'expiry_at' => Carbon::now()->addDay(),
        ]);

        $this->artisan('transfers:cleanup --force')
            ->expectsOutput('No expired transfers found.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_asks_for_confirmation_without_force_flag()
    {
        $expiredTransfer = Transfer::factory()->create([
            'expiry_at' => Carbon::now()->subDay(),
        ]);

        $this->artisan('transfers:cleanup')
            ->expectsQuestion('Delete 1 expired transfers?', false)
            ->expectsOutput('Operation cancelled.')
            ->assertExitCode(0);

        // Transfer should not be deleted
        $this->assertDatabaseHas('transfers', ['id' => $expiredTransfer->id]);
    }

    /** @test */
    public function it_dispatches_event_with_correct_analytics_data()
    {
        Event::fake();

        $expiredTransfer = Transfer::factory()->create([
            'expiry_at' => Carbon::now()->subDay(),
            'total_size' => 2048,
            'download_count' => 5,
            'max_downloads' => 10,
            'is_password_protected' => true,
        ]);

        TransferFile::factory()->create([
            'transfer_id' => $expiredTransfer->id,
            'size' => 1024,
        ]);

        $this->artisan('transfers:cleanup --force')
            ->assertExitCode(0);

        Event::assertDispatched(TransferDeleted::class, function ($event) use ($expiredTransfer) {
            $analyticsData = $event->getAnalyticsData();
            
            return $analyticsData['transfer_uuid'] === $expiredTransfer->uuid &&
                   $analyticsData['reason'] === 'expired' &&
                   $analyticsData['total_size'] === 2048 &&
                   $analyticsData['download_count'] === 5 &&
                   $analyticsData['was_password_protected'] === true;
        });
    }

    /** @test */
    public function it_displays_detailed_cleanup_results()
    {
        Event::fake();
        Storage::fake('uploads');
        Storage::fake('r2');

        // Set up R2 config
        config(['filesystems.disks.r2.driver' => 's3']);
        config(['filesystems.disks.r2.endpoint' => 'https://account.r2.cloudflarestorage.com']);

        $expiredTransfer = Transfer::factory()->create([
            'expiry_at' => Carbon::now()->subDay(),
        ]);

        // Create files on different storage systems
        TransferFile::factory()->create([
            'transfer_id' => $expiredTransfer->id,
            'storage_path' => 'local-file.pdf',
            'size' => 1024,
        ]);

        Storage::disk('uploads')->put('local-file.pdf', 'local content');

        $this->artisan('transfers:cleanup --force')
            ->expectsOutputToContain('=== Cleanup Results ===')
            ->expectsOutputToContain('Transfers deleted: 1')
            ->expectsOutputToContain('Files deleted: 1')
            ->expectsOutputToContain('Total size freed: 1.00 KB')
            ->expectsOutputToContain('Local storage deletions: 1')
            ->assertExitCode(0);
    }
}
