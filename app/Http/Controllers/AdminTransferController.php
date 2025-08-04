<?php

namespace App\Http\Controllers;

use App\Models\Transfer;
use Common\Core\BaseController;
use Common\Database\Datasource\Datasource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminTransferController extends BaseController
{
    public function __construct(protected Request $request, protected Transfer $transfer)
    {
        $this->middleware('auth');
        $this->middleware('isAdmin');
    }

    /**
     * Display a listing of transfers for admin
     */
    public function index()
    {
        $this->authorize('index', Transfer::class);

        $params = $this->request->all();
        $dataSource = new Datasource($this->transfer->with(['files']), $params);
        
        $pagination = $dataSource->paginate();

        return $this->success(['pagination' => $pagination]);
    }

    /**
     * Show transfer details
     */
    public function show(Transfer $transfer)
    {
        $this->authorize('show', $transfer);

        $transfer->load(['files']);

        return $this->success([
            'transfer' => $transfer,
            'files' => $transfer->files,
            'analytics' => [
                'total_downloads' => $transfer->download_count,
                'file_count' => $transfer->files->count(),
                'total_size' => $transfer->total_size,
                'expires_at' => $transfer->expires_at,
                'created_at' => $transfer->created_at,
            ]
        ]);
    }

    /**
     * Delete a transfer and its files
     */
    public function destroy($transferIds)
    {
        $transferIds = is_string($transferIds) ? explode(',', $transferIds) : $transferIds;
        
        $this->validate($this->request, [
            'transferIds' => 'array|exists:transfers,id',
        ]);

        $transfers = $this->transfer->whereIn('id', $transferIds)->get();
        
        foreach ($transfers as $transfer) {
            $this->authorize('destroy', $transfer);
            
            // Delete physical files
            foreach ($transfer->files as $file) {
                if ($file->disk_prefix) {
                    Storage::disk($file->disk)->delete($file->disk_prefix);
                }
            }
            
            // Delete transfer and its files
            $transfer->delete();
        }

        return $this->success(['message' => 'Transfers deleted successfully']);
    }

    /**
     * Get transfer analytics
     */
    public function analytics()
    {
        $this->authorize('index', Transfer::class);

        $stats = [
            'total_transfers' => Transfer::count(),
            'active_transfers' => Transfer::active()->count(),
            'expired_transfers' => Transfer::expired()->count(),
            'total_downloads' => Transfer::sum('download_count'),
            'total_size' => Transfer::sum('total_size'),
            'transfers_today' => Transfer::whereDate('created_at', today())->count(),
            'downloads_today' => Transfer::whereDate('updated_at', today())->sum('download_count'),
        ];

        // Recent transfers
        $recentTransfers = Transfer::with(['files'])
            ->latest()
            ->take(10)
            ->get();

        // Top downloads
        $topDownloads = Transfer::with(['files'])
            ->orderBy('download_count', 'desc')
            ->take(10)
            ->get();

        return $this->success([
            'stats' => $stats,
            'recent_transfers' => $recentTransfers,
            'top_downloads' => $topDownloads,
        ]);
    }

    /**
     * Cleanup expired transfers
     */
    public function cleanup()
    {
        $this->authorize('index', Transfer::class);

        $expiredTransfers = Transfer::expired()->get();
        $deletedCount = 0;

        foreach ($expiredTransfers as $transfer) {
            // Delete physical files
            foreach ($transfer->files as $file) {
                if ($file->disk_prefix) {
                    Storage::disk($file->disk)->delete($file->disk_prefix);
                }
            }
            
            $transfer->delete();
            $deletedCount++;
        }

        return $this->success([
            'message' => "Cleaned up {$deletedCount} expired transfers",
            'deleted_count' => $deletedCount
        ]);
    }
}
