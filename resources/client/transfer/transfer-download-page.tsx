import React from 'react';
import { Trans } from '@ui/i18n/trans';
import { CopyIcon } from '@ui/icons/material/ContentCopy';
import { DownloadIcon } from '@ui/icons/material/Download';

export function TransferDownloadPage({ transfer }) {
  const { file_count, total_size, expires_at, share_url, download_url } = transfer;

  const copyToClipboard = async (text) => {
    try {
      await navigator.clipboard.writeText(text);
      alert('Copied to clipboard!');
    } catch (err) {
      console.error('Failed to copy: ', err);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
      <div className="container mx-auto px-4 py-8">
        <div className="max-w-2xl mx-auto">
          {/* Header */}
          <div className="text-center mb-8">
            <div className="w-20 h-20 bg-blue-500 rounded-full flex items-center justify-center mx-auto mb-4">
              <DownloadIcon className="w-10 h-10 text-white" />
            </div>
            <h1 className="text-3xl font-bold text-gray-900 mb-2">
              <Trans message="Download Your Files" />
            </h1>
            <p className="text-gray-600">
              <Trans message="Your download is ready" />
            </p>
          </div>

          {/* Transfer Details Card */}
          <div className="bg-white rounded-2xl shadow-lg p-8 mb-6">
            {/* Stats */}
            <div className="grid grid-cols-3 gap-4 mb-6 p-4 bg-gray-50 rounded-lg">
              <div className="text-center">
                <div className="text-2xl font-bold text-gray-900">{file_count}</div>
                <div className="text-sm text-gray-500">Files</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-gray-900">{formatBytes(total_size)}</div>
                <div className="text-sm text-gray-500">Total Size</div>
              </div>
              <div className="text-center">
                <div className="text-2xl font-bold text-gray-900">
                  {new Date(expires_at).toLocaleDateString()}
                </div>
                <div className="text-sm text-gray-500">Expires</div>
              </div>
            </div>

            {/* Share Link */}
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  <Trans message="Share Link" />
                </label>
                <div className="flex rounded-lg border border-gray-200 overflow-hidden">
                  <input 
                    type="text" 
                    value={share_url} 
                    readOnly 
                    className="flex-1 px-4 py-3 bg-gray-50 border-0 text-sm"
                  />
                  <button 
                    onClick={() => copyToClipboard(share_url)}
                    className="px-4 py-3 bg-blue-600 text-white hover:bg-blue-700 transition-colors"
                  >
                    <CopyIcon className="w-4 h-4" />
                  </button>
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  <Trans message="Direct Download" />
                </label>
                <div className="flex rounded-lg border border-gray-200 overflow-hidden">
                  <input 
                    type="text" 
                    value={download_url} 
                    readOnly 
                    className="flex-1 px-4 py-3 bg-gray-50 border-0 text-sm"
                  />
                  <button 
                    onClick={() => copyToClipboard(download_url)}
                    className="px-4 py-3 bg-green-600 text-white hover:bg-green-700 transition-colors"
                  >
                    <DownloadIcon className="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
          </div>

          {/* Actions */}
          <div className="text-center">
            <button 
              onClick={() => window.location.href = share_url}
              className="px-8 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium"
            >
              <Trans message="Start New Transfer" />
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

const formatBytes = (bytes, decimals = 2) => {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const dm = decimals < 0 ? 0 : decimals;
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
};

