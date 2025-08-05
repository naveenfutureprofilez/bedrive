import React, { useState, useRef } from 'react';
import { useParams } from 'react-router';
import { Trans } from '@ui/i18n/trans';
import { ProgressCircle } from '@ui/progress/progress-circle';
import { FileDownloadIcon } from '@ui/icons/material/FileDownload';
import { ErrorIcon } from '@ui/icons/material/Error';
import { CheckCircleIcon } from '@ui/icons/material/CheckCircle';
import { Button } from '@ui/buttons/button';
import { formatBytes } from '@app/uploads/format-bytes';
import { useDownloadPage } from './use-download-page';

interface FileItem {
  id: string;
  name: string;
  size: number;
  mime: string;
  hash: string;
}

interface DownloadInfo {
  id: string;
  slug: string;
  files: FileItem[];
  totalSize: number;
  allowDownload: boolean;
  expiresAt?: string;
  title?: string;
}

interface DownloadProgress {
  fileIndex: number;
  fileName: string;
  loaded: number;
  total: number;
  percentage: number;
  isComplete: boolean;
  error?: string;
}

export function DownloadPage() {
  const { slug } = useParams<{ slug: string }>();
  const { data: downloadInfo, isLoading: loading, error: queryError } = useDownloadPage(slug || '');
  const [downloading, setDownloading] = useState(false);
  const [progress, setProgress] = useState<DownloadProgress | null>(null);
  const abortControllerRef = useRef<AbortController | null>(null);
  
  const error = queryError ? 'Failed to load download information' : null;

  const startDownload = async () => {
    if (!downloadInfo) return;
    
    try {
      setDownloading(true);
      setProgress(null);
      abortControllerRef.current = new AbortController();

      if (downloadInfo.files.length === 1) {
        // Single file download
        await downloadSingleFile(downloadInfo.files[0]);
      } else {
        // Multiple files - create zip with streaming
        await downloadMultipleFiles();
      }
    } catch (err: any) {
      if (err.name !== 'AbortError') {
        setError('Download failed: ' + (err.message || 'Unknown error'));
      }
    } finally {
      setDownloading(false);
      setProgress(null);
    }
  };

  const downloadSingleFile = async (file: FileItem) => {
    const response = await fetch(`/api/v1/file-entries/download/${file.hash}`, {
      signal: abortControllerRef.current?.signal,
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const reader = response.body?.getReader();
    if (!reader) {
      throw new Error('No reader available');
    }

    const contentLength = parseInt(response.headers.get('content-length') || '0', 10);
    let receivedLength = 0;
    const chunks: Uint8Array[] = [];

    setProgress({
      fileIndex: 0,
      fileName: file.name,
      loaded: 0,
      total: contentLength,
      percentage: 0,
      isComplete: false,
    });

    while (true) {
      const { done, value } = await reader.read();
      
      if (done) break;
      
      chunks.push(value);
      receivedLength += value.length;
      
      const percentage = contentLength > 0 ? Math.round((receivedLength / contentLength) * 100) : 0;
      
      setProgress(prev => prev ? {
        ...prev,
        loaded: receivedLength,
        percentage,
      } : null);
    }

    // Create blob and download
    const blob = new Blob(chunks, { type: file.mime });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = file.name;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);

    setProgress(prev => prev ? { ...prev, isComplete: true, percentage: 100 } : null);
  };

  const downloadMultipleFiles = async () => {
    const hashes = downloadInfo!.files.map(f => f.hash).join(',');
    const response = await fetch(`/api/v1/file-entries/download/${hashes}`, {
      signal: abortControllerRef.current?.signal,
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const reader = response.body?.getReader();
    if (!reader) {
      throw new Error('No reader available');
    }

    const contentLength = parseInt(response.headers.get('content-length') || '0', 10);
    let receivedLength = 0;
    const chunks: Uint8Array[] = [];

    setProgress({
      fileIndex: 0,
      fileName: `${downloadInfo!.files.length} files`,
      loaded: 0,
      total: contentLength,
      percentage: 0,
      isComplete: false,
    });

    while (true) {
      const { done, value } = await reader.read();
      
      if (done) break;
      
      chunks.push(value);
      receivedLength += value.length;
      
      const percentage = contentLength > 0 ? Math.round((receivedLength / contentLength) * 100) : 0;
      
      setProgress(prev => prev ? {
        ...prev,
        loaded: receivedLength,
        percentage,
      } : null);
    }

    // Create blob and download
    const blob = new Blob(chunks, { type: 'application/zip' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `download-${Date.now()}.zip`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);

    setProgress(prev => prev ? { ...prev, isComplete: true, percentage: 100 } : null);
  };

  const cancelDownload = () => {
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
      setDownloading(false);
      setProgress(null);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 flex items-center justify-center">
        <div className="text-center">
          <ProgressCircle isIndeterminate />
          <p className="mt-4 text-gray-600">
            <Trans message="Loading download information..." />
          </p>
        </div>
      </div>
    );
  }

  if (error || !downloadInfo) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 flex items-center justify-center">
        <div className="text-center">
          <ErrorIcon className="w-16 h-16 text-red-500 mx-auto mb-4" />
          <h1 className="text-2xl font-bold text-gray-900 mb-2">
            <Trans message="Download Not Found" />
          </h1>
          <p className="text-gray-600 mb-6">
            {error || <Trans message="The requested download could not be found." />}
          </p>
          <Button variant="flat" onClick={() => window.location.href = '/'}>
            <Trans message="Go Home" />
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
      <div className="container mx-auto px-4 py-8">
        <div className="max-w-2xl mx-auto">
          {/* Header */}
          <div className="text-center mb-8">
            <div className="w-20 h-20 bg-blue-500 rounded-full flex items-center justify-center mx-auto mb-4">
              <FileDownloadIcon className="w-10 h-10 text-white" />
            </div>
            <h1 className="text-3xl font-bold text-gray-900 mb-2">
              {downloadInfo.title || <Trans message="Download Files" />}
            </h1>
            <p className="text-gray-600">
              <Trans message="Your files are ready for download" />
            </p>
          </div>

          {/* Download Info Card */}
          <div className="bg-white rounded-2xl shadow-lg p-8 mb-6">
            {/* Stats */}
            <div className="grid grid-cols-3 gap-4 mb-6 p-4 bg-gray-50 rounded-lg">
              <div className="text-center">
                <div className="text-2xl font-bold text-gray-900">{downloadInfo.files.length}</div>
                <div className="text-sm text-gray-500">
                  <Trans message="Files" />
                </div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-gray-900">{formatBytes(downloadInfo.totalSize)}</div>
                <div className="text-sm text-gray-500">
                  <Trans message="Total Size" />
                </div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-gray-900">
                  {downloadInfo.expiresAt ? new Date(downloadInfo.expiresAt).toLocaleDateString() : '∞'}
                </div>
                <div className="text-sm text-gray-500">
                  <Trans message="Expires" />
                </div>
              </div>
            </div>

            {/* File List */}
            <div className="mb-8">
              <h3 className="text-lg font-medium text-gray-900 mb-4">
                <Trans message="Files" />
              </h3>
              <div className="space-y-2">
                {downloadInfo.files.map((file, index) => (
                  <div
                    key={file.id}
                    className="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                  >
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium text-gray-900 truncate" title={file.name}>
                        {file.name}
                      </p>
                      <p className="text-xs text-gray-500">{formatBytes(file.size)}</p>
                    </div>
                    {progress && progress.fileIndex === index && (
                      <div className="ml-3 text-sm text-blue-600">
                        {progress.percentage}%
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </div>

            {/* Progress Bar */}
            {progress && (
              <div className="mb-6">
                <div className="flex items-center justify-between mb-2">
                  <span className="text-sm font-medium text-gray-700">
                    <Trans message="Downloading: :fileName" values={{ fileName: progress.fileName }} />
                  </span>
                  <span className="text-sm text-gray-500">
                    {formatBytes(progress.loaded)} / {formatBytes(progress.total)} ({progress.percentage}%)
                  </span>
                </div>
                <div className="w-full bg-gray-200 rounded-full h-2">
                  <div
                    className="bg-blue-600 h-2 rounded-full transition-all duration-300"
                    style={{ width: `${progress.percentage}%` }}
                  />
                </div>
                {progress.isComplete && (
                  <div className="flex items-center mt-2 text-green-600">
                    <CheckCircleIcon className="w-4 h-4 mr-1" />
                    <span className="text-sm">
                      <Trans message="Download complete!" />
                    </span>
                  </div>
                )}
              </div>
            )}

            {/* Download Button */}
            <div className="text-center">
              {!downloading ? (
                <Button
                  variant="flat"
                  color="primary"
                  size="lg"
                  startIcon={<FileDownloadIcon />}
                  onClick={startDownload}
                  disabled={!downloadInfo.allowDownload}
                >
                  <Trans message="Start Download" />
                </Button>
              ) : (
                <Button
                  variant="outline"
                  color="danger"
                  size="lg"
                  onClick={cancelDownload}
                >
                  <Trans message="Cancel Download" />
                </Button>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
