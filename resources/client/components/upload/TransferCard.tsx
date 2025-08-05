import React from 'react';
import { ProgressBar } from './ProgressBar'; // Assume ProgressBar component is in the same directory
import clsx from 'clsx';

interface TransferCardProps {
  id: string;
  fileName: string;
  fileSize: number; // in bytes
  progress: number; // between 0 and 100
  status: 'pending' | 'uploading' | 'completed' | 'error';
  errorMessage?: string;
  onRemove?: (id: string) => void;
}

export function TransferCard({
  id,
  fileName,
  fileSize,
  progress,
  status,
  errorMessage,
  onRemove,
}: TransferCardProps) {
  return (
    <div className="p-4 bg-white shadow rounded-lg flex items-center justify-between space-x-4">
      <div className="flex-1 w-0">
        <div className="text-sm font-medium text-gray-900 truncate">{fileName}</div>
        <div className="text-sm text-gray-500">
          {formatBytes(fileSize)}
        </div>
        <div className="mt-2">
          <ProgressBar
            progress={progress}
            variant={status === 'error' ? 'error' : 'default'}
            showPercentage
            size="lg"
          />
        </div>
      </div>
      <div className="flex-shrink-0">
        <button
          onClick={() => onRemove?.(id)}
          className={clsx(
            "text-red-500 hover:text-red-700 focus:outline-none",
            status === 'completed' ? 'hidden' : 'block'
          )}
          disabled={status === 'completed'}
        >
          x
        </button>
      </div>
      {status === 'error' && errorMessage && (
        <div className="text-xs text-red-500 mt-2">{errorMessage}</div>
      )}
    </div>
  );
}

function formatBytes(bytes: number, decimals = 1): string {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(decimals)) + ' ' + sizes[i];
}

