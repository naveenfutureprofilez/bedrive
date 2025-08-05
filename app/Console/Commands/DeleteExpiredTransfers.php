<?php

namespace App\Console\Commands;

use App\Models\Transfer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Events\TransferDeleted;

class DeleteExpiredTransfers extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'transfers:cleanup {--force : Force delete without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Delete expired transfer files and database records';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expiredTransfers = Transfer::where('expiry_at', '<', now())->get();
        $count = $expiredTransfers->count();

        if ($count === 0) {
            $this->info('No expired transfers found.');
            return 0;
        }

        if (!$this->option('force') && !$this->confirm("Delete {$count} expired transfers?")) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $metrics = [
            'total_size' => 0,
            'deleted_files' => 0,
            'failed_deletions' => 0,
            'r2_deletions' => 0,
            'local_deletions' => 0,
        ];

        foreach ($expiredTransfers as $transfer) {
            $this->info("Deleting transfer: {$transfer->hash}");
            
            $transferMetrics = $this->deleteTransferFiles($transfer);
            $metrics['total_size'] += $transferMetrics['total_size'];
            $metrics['deleted_files'] += $transferMetrics['deleted_files'];
            $metrics['failed_deletions'] += $transferMetrics['failed_deletions'];
            $metrics['r2_deletions'] += $transferMetrics['r2_deletions'];
            $metrics['local_deletions'] += $transferMetrics['local_deletions'];

            // Delete transfer record (this will cascade delete related files)
            $transfer->forceDelete();
            
            // Dispatch event for analytics
            event(new TransferDeleted($transfer, 'expired', $transferMetrics));
        }

        $this->displayResults($count, $metrics);
        return 0;
    }

    /**
     * Delete all files associated with a transfer
     */
    private function deleteTransferFiles(Transfer $transfer): array
    {
        $metrics = [
            'total_size' => 0,
            'deleted_files' => 0,
            'failed_deletions' => 0,
            'r2_deletions' => 0,
            'local_deletions' => 0,
        ];

        // Delete legacy files (file_entries table)
        foreach ($transfer->files as $file) {
            if ($file->disk_prefix) {
                $result = $this->deleteFile($file->disk_prefix, $file->disk ?? 'uploads', $file->size ?? 0);
                $this->updateMetrics($metrics, $result);
            }
        }

        // Delete TUS files (transfer_files table)
        foreach ($transfer->transferFiles as $file) {
            if ($file->storage_path) {
                $result = $this->deleteFile($file->storage_path, config('tus.disk', 'uploads'), $file->size ?? 0);
                $this->updateMetrics($metrics, $result);
            }
        }

        return $metrics;
    }

    /**
     * Delete a single file from storage
     */
    private function deleteFile(string $path, string $diskName, int $fileSize): array
    {
        $result = [
            'success' => false,
            'size' => $fileSize,
            'disk' => $diskName,
            'is_r2' => false,
        ];

        try {
            $disk = Storage::disk($diskName);
            
            // Check if this is an R2 disk (Cloudflare R2)
            $result['is_r2'] = $this->isCloudflareR2Disk($diskName);
            
            if ($disk->exists($path)) {
                $disk->delete($path);
                $result['success'] = true;
                
                if ($result['is_r2']) {
                    $this->line("  ✓ Deleted from Cloudflare R2: {$path}");
                } else {
                    $this->line("  ✓ Deleted from {$diskName}: {$path}");
                }
            } else {
                $this->warn("  ⚠ File not found on {$diskName}: {$path}");
            }
        } catch (\Exception $e) {
            $this->error("  ✗ Failed to delete from {$diskName}: {$path} - {$e->getMessage()}");
        }

        return $result;
    }

    /**
     * Check if a disk is configured for Cloudflare R2
     */
    private function isCloudflareR2Disk(string $diskName): bool
    {
        $diskConfig = config("filesystems.disks.{$diskName}");
        
        if (!$diskConfig || $diskConfig['driver'] !== 's3') {
            return false;
        }

        // Check if it's R2 by looking for R2-specific endpoint patterns
        $endpoint = $diskConfig['endpoint'] ?? '';
        return str_contains($endpoint, 'r2.cloudflarestorage.com') || 
               str_contains($endpoint, 'cloudflare') ||
               $diskName === 'r2';
    }

    /**
     * Update metrics with file deletion result
     */
    private function updateMetrics(array &$metrics, array $result): void
    {
        if ($result['success']) {
            $metrics['total_size'] += $result['size'];
            $metrics['deleted_files']++;
            
            if ($result['is_r2']) {
                $metrics['r2_deletions']++;
            } else {
                $metrics['local_deletions']++;
            }
        } else {
            $metrics['failed_deletions']++;
        }
    }

    /**
     * Display cleanup results
     */
    private function displayResults(int $transferCount, array $metrics): void
    {
        $this->info("\n=== Cleanup Results ===");
        $this->info("Transfers deleted: {$transferCount}");
        $this->info("Files deleted: {$metrics['deleted_files']}");
        $this->info("Total size freed: " . $this->formatBytes($metrics['total_size']));
        
        if ($metrics['r2_deletions'] > 0) {
            $this->info("Cloudflare R2 deletions: {$metrics['r2_deletions']}");
        }
        
        if ($metrics['local_deletions'] > 0) {
            $this->info("Local storage deletions: {$metrics['local_deletions']}");
        }
        
        if ($metrics['failed_deletions'] > 0) {
            $this->warn("Failed deletions: {$metrics['failed_deletions']}");
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
