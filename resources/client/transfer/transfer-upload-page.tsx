import React, { useState, useCallback, useRef, useEffect } from 'react';
import { useDropzone } from 'react-dropzone';
import * as tus from 'tus-js-client';
import { AnimatePresence, motion } from 'framer-motion';
import { Trans } from '@ui/i18n/trans';
import { UploadIcon } from '@ui/icons/material/Upload';
import { CloseIcon } from '@ui/icons/material/Close';
import { CheckIcon } from '@ui/icons/material/Check';
import { ContentCopyIcon } from '@ui/icons/material/ContentCopy';
import { DownloadIcon } from '@ui/icons/material/Download';
import { LockIcon } from '@ui/icons/material/Lock';
import { AccessTimeIcon } from '@ui/icons/material/AccessTime';
import { FolderIcon } from '@ui/icons/material/Folder';
import { PauseCircleIcon } from '@ui/icons/material/PauseCircle';
import { PlayCircleIcon } from '@ui/icons/material/PlayCircle';
import { LazyImagePreview } from '../components/LazyImagePreview';

interface UploadFile {
  file: File;
  id: string;
  progress: number;
  status: 'pending' | 'uploading' | 'completed' | 'error';
  error?: string;
}

interface TransferOptions {
  password?: string;
  passwordProtect?: boolean;
  expiresInDays?: number;
  maxDownloads?: number;
}

const formatBytes = (bytes: number, decimals = 2) => {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const dm = decimals < 0 ? 0 : decimals;
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
};

export function TransferUploadPage() {
  const [files, setFiles] = useState<UploadFile[]>([]);
  const [isUploading, setIsUploading] = useState(false);
  const [transferResult, setTransferResult] = useState<any>(null);
  const [transferOptions, setTransferOptions] = useState<TransferOptions>({});
  const [showOptions, setShowOptions] = useState(false);

  const onDrop = useCallback((acceptedFiles: File[]) => {
    const newFiles = acceptedFiles.map(file => ({
      file,
      id: Math.random().toString(36).substr(2, 9),
      progress: 0,
      status: 'pending' as const,
    }));
    setFiles(prev => [...prev, ...newFiles]);
  }, []);

  const removeFile = (id: string) => {
    setFiles(prev => prev.filter(f => f.id !== id));
  };

const uploadFiles = async () => {
    if (files.length === 0) return;

    // Validate password if protection is enabled
    if (transferOptions.passwordProtect && !transferOptions.password) {
      alert('Please enter a password to protect this transfer.');
      return;
    }

    setIsUploading(true);

    try {
      // Create FormData for multipart upload
      const formData = new FormData();
      
      // Add all files
      files.forEach(fileItem => {
        formData.append('files[]', fileItem.file);
      });
      
      // Add transfer options
      if (transferOptions.passwordProtect && transferOptions.password) {
        formData.append('password_protect', 'true');
        formData.append('password', transferOptions.password);
      }
      
      formData.append('expires_in_days', (transferOptions.expiresInDays || 7).toString());
      
      if (transferOptions.maxDownloads) {
        formData.append('max_downloads', transferOptions.maxDownloads.toString());
      }

      // Upload with progress tracking
      const xhr = new XMLHttpRequest();
      
      xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
          const progress = Math.round((e.loaded / e.total) * 100);
          // Update all files progress equally for simplicity
          setFiles(prev => prev.map(f => ({
            ...f,
            progress,
            status: progress === 100 ? 'completed' : 'uploading'
          })));
        }
      });

      xhr.addEventListener('load', () => {
        if (xhr.status === 201) {
          const response = JSON.parse(xhr.responseText);
          setTransferResult({
            ...response.transfer,
            share_url: response.share_url,
            download_url: response.download_url,
            expires_at: response.expiry_at,
            total_size: response.total_size,
            file_count: response.file_count
          });
          setFiles(prev => prev.map(f => ({ ...f, status: 'completed', progress: 100 })));
        } else {
          throw new Error(`Upload failed with status ${xhr.status}`);
        }
        setIsUploading(false);
      });

      xhr.addEventListener('error', () => {
        setFiles(prev => prev.map(f => ({
          ...f,
          status: 'error',
          error: 'Upload failed'
        })));
        setIsUploading(false);
      });

      xhr.open('POST', '/api/v1/transfer');
      xhr.send(formData);
      
    } catch (error) {
      console.error('Upload failed:', error);
      setFiles(prev => prev.map(f => ({
        ...f,
        status: 'error',
        error: (error as Error).message || 'Upload failed'
      })));
      setIsUploading(false);
    }
  };

  const copyToClipboard = async (text: string) => {
    try {
      await navigator.clipboard.writeText(text);
      // You can add a toast notification here
      alert('Copied to clipboard!');
    } catch (err) {
      console.error('Failed to copy: ', err);
    }
  };

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop,
    maxSize: 1024 * 1024 * 1024 * 2, // 2GB
    multiple: true,
    accept: {
      '*/*': []
    }
  });

  // Success Screen
  if (transferResult) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
        <div className="container mx-auto px-4 py-8">
          <div className="max-w-2xl mx-auto">
            {/* Header */}
            <div className="text-center mb-8">
              <motion.div
                initial={{ scale: 0 }}
                animate={{ scale: 1 }}
                className="w-20 h-20 bg-green-500 rounded-full flex items-center justify-center mx-auto mb-4"
              >
                <CheckIcon className="w-10 h-10 text-white" />
              </motion.div>
              <h1 className="text-3xl font-bold text-gray-900 mb-2">
                <Trans message="Files uploaded successfully!" />
              </h1>
              <p className="text-gray-600">
                <Trans message="Your files are ready to share" />
              </p>
            </div>

            {/* Transfer Details Card */}
            <motion.div
              initial={{ y: 20, opacity: 0 }}
              animate={{ y: 0, opacity: 1 }}
              className="bg-white rounded-2xl shadow-lg p-8 mb-6"
            >
              {/* Stats */}
              <div className="grid grid-cols-3 gap-4 mb-6 p-4 bg-gray-50 rounded-lg">
                <div className="text-center">
                  <div className="text-2xl font-bold text-gray-900">{transferResult.file_count}</div>
                  <div className="text-sm text-gray-500">Files</div>
                </div>
                <div className="text-center">
                  <div className="text-2xl font-bold text-gray-900">{formatBytes(transferResult.total_size)}</div>
                  <div className="text-sm text-gray-500">Total Size</div>
                </div>
                <div className="text-center">
                  <div className="text-2xl font-bold text-gray-900">
                    {new Date(transferResult.expires_at).toLocaleDateString()}
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
                      value={transferResult.share_url} 
                      readOnly 
                      className="flex-1 px-4 py-3 bg-gray-50 border-0 text-sm"
                    />
                    <button 
                      onClick={() => copyToClipboard(transferResult.share_url)}
                      className="px-4 py-3 bg-blue-600 text-white hover:bg-blue-700 transition-colors"
                    >
                      <ContentCopyIcon className="w-4 h-4" />
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
                      value={transferResult.download_url} 
                      readOnly 
                      className="flex-1 px-4 py-3 bg-gray-50 border-0 text-sm"
                    />
                    <button 
                      onClick={() => copyToClipboard(transferResult.download_url)}
                      className="px-4 py-3 bg-green-600 text-white hover:bg-green-700 transition-colors"
                    >
                      <DownloadIcon className="w-4 h-4" />
                    </button>
                  </div>
                </div>
              </div>
            </motion.div>

            {/* Actions */}
            <div className="text-center">
              <button 
                onClick={() => {
                  setFiles([]);
                  setTransferResult(null);
                  setTransferOptions({});
                }}
                className="px-8 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium"
              >
                <Trans message="Send Another" />
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  // Upload Screen
  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
      <div className="container mx-auto px-4 py-8">
        <div className="max-w-4xl mx-auto">
          {/* Header */}
          <div className="text-center mb-12">
            <h1 className="text-4xl md:text-5xl font-bold text-gray-900 mb-4">
              <Trans message="Share files like a pro" />
            </h1>
            <p className="text-xl text-gray-600 max-w-2xl mx-auto">
              <Trans message="Send large files quickly and securely. No registration required." />
            </p>
          </div>

          {/* Main Upload Card */}
          <motion.div
            initial={{ y: 20, opacity: 0 }}
            animate={{ y: 0, opacity: 1 }}
            className="bg-white rounded-2xl shadow-lg p-8 mb-6"
          >
            {/* Upload Area */}
            <div
              {...getRootProps({
                className: `relative border-2 border-dashed rounded-2xl p-12 text-center transition-all cursor-pointer ${
                  isDragActive 
                    ? 'border-blue-400 bg-blue-50' 
                    : files.length > 0 
                      ? 'border-green-300 bg-green-50' 
                      : 'border-gray-300 hover:border-blue-400 hover:bg-blue-50'
                }`,
              })}
            >
              <input {...getInputProps()} />
              <motion.div
                animate={isDragActive ? { scale: 1.05 } : { scale: 1 }}
                className="space-y-4"
              >
                <div className="w-16 h-16 mx-auto bg-blue-100 rounded-full flex items-center justify-center">
                  <UploadIcon className="w-8 h-8 text-blue-600" />
                </div>
                {isDragActive ? (
                  <div>
                    <h3 className="text-xl font-semibold text-blue-600 mb-2">
                      <Trans message="Drop files here" />
                    </h3>
                    <p className="text-gray-500">
                      <Trans message="Release to upload" />
                    </p>
                  </div>
                ) : (
                  <div>
                    <h3 className="text-xl font-semibold text-gray-900 mb-2">
                      <Trans message="Drag and drop files here" />
                    </h3>
                    <p className="text-gray-500 mb-4">
                      <Trans message="or click to browse" />
                    </p>
                    <div className="text-sm text-gray-400">
                      <Trans message="Maximum file size: 2GB per file" />
                    </div>
                  </div>
                )}
              </motion.div>
            </div>

            {/* Files List */}
            <AnimatePresence>
              {files.length > 0 && (
                <motion.div
                  initial={{ height: 0, opacity: 0 }}
                  animate={{ height: 'auto', opacity: 1 }}
                  exit={{ height: 0, opacity: 0 }}
                  className="mt-8"
                >
                  <h4 className="text-lg font-semibold mb-4 text-gray-900">
                    <Trans message="Files to upload" /> ({files.length})
                  </h4>
                  <div className="space-y-3">
                    {files.map((fileItem) => (
                      <motion.div
                        key={fileItem.id}
                        initial={{ x: -20, opacity: 0 }}
                        animate={{ x: 0, opacity: 1 }}
                        exit={{ x: 20, opacity: 0 }}
                        className="flex items-center justify-between p-4 bg-gray-50 rounded-xl border"
                      >
                        <div className="flex items-center space-x-3 flex-1 min-w-0 mr-4">
                          {/* File Preview */}
                          <div className="flex-shrink-0">
                            <LazyImagePreview
                              file={fileItem.file}
                              className="w-12 h-12"
                              maxWidth={48}
                              maxHeight={48}
                              showFallback={true}
                            />
                          </div>
                          
                          {/* File Info */}
                          <div className="flex-1 min-w-0">
                            <div className="font-medium text-gray-900 truncate">
                              {fileItem.file.name}
                            </div>
                            <div className="text-sm text-gray-500">
                              {formatBytes(fileItem.file.size)}
                            </div>
                          </div>
                        </div>
                        
                        <div className="flex items-center gap-3">
                          {fileItem.status === 'uploading' && (
                            <div className="w-32">
                              <div className="w-full bg-gray-200 rounded-full h-2">
                                <motion.div 
                                  className="bg-blue-600 h-2 rounded-full" 
                                  initial={{ width: 0 }}
                                  animate={{ width: `${fileItem.progress}%` }}
                                  transition={{ duration: 0.3 }}
                                />
                              </div>
                              <div className="text-xs text-gray-500 mt-1">
                                {fileItem.progress}%
                              </div>
                            </div>
                          )}
                          {fileItem.status === 'completed' && (
                            <div className="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                              <CheckIcon className="w-5 h-5 text-green-600" />
                            </div>
                          )}
                          {fileItem.status === 'error' && (
                            <div className="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                              <CloseIcon className="w-5 h-5 text-red-600" />
                            </div>
                          )}
                          {fileItem.status === 'pending' && (
                            <button 
                              onClick={() => removeFile(fileItem.id)} 
                              className="w-8 h-8 hover:bg-gray-200 rounded-full flex items-center justify-center transition-colors"
                            >
                              <CloseIcon className="w-5 h-5 text-gray-400" />
                            </button>
                          )}
                        </div>
                      </motion.div>
                    ))}
                  </div>
                </motion.div>
              )}
            </AnimatePresence>

            {/* Transfer Options */}
            {files.length > 0 && (
              <motion.div
                initial={{ height: 0, opacity: 0 }}
                animate={{ height: 'auto', opacity: 1 }}
                className="mt-8"
              >
                <button 
                  type="button" 
                  onClick={() => setShowOptions(!showOptions)}
                  className="flex items-center gap-2 text-gray-600 hover:text-gray-900 font-medium mb-4"
                >
                  <Trans message="Transfer Options" />
                  <motion.div
                    animate={{ rotate: showOptions ? 90 : 0 }}
                    transition={{ duration: 0.2 }}
                  >
                    ▶
                  </motion.div>
                </button>
                
                <AnimatePresence>
                  {showOptions && (
                    <motion.div
                      initial={{ height: 0, opacity: 0 }}
                      animate={{ height: 'auto', opacity: 1 }}
                      exit={{ height: 0, opacity: 0 }}
                      className="space-y-4 p-6 bg-gray-50 rounded-xl"
                    >
                      {/* Password Protection Section */}
                      <div className="col-span-full mb-4">
                        <div className="flex items-center mb-2">
                          <input
                            id="password-protect"
                            type="checkbox"
                            onChange={() => setTransferOptions(prev => ({ ...prev, passwordProtect: !prev.passwordProtect }))}
                            checked={transferOptions.passwordProtect || false}
                            className="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500"
                          />
                          <label htmlFor="password-protect" className="ml-2 text-sm font-medium text-gray-700">
                            <LockIcon className="w-4 h-4 inline mr-1" />
                            <Trans message="Password Protection" />
                          </label>
                        </div>
                        {transferOptions.passwordProtect && (
                          <input
                            type="password"
                            placeholder="Enter password"
                            value={transferOptions.password || ''}
onChange={(e) => setTransferOptions(prev => ({ ...prev, password: e.target.value }))}
minLength={1}
maxLength={128}
required
                            className="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                          />
                        )}
                      </div>
                      <div className="grid grid-cols-1 md:grid-cols-1 gap-4">
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2">
                            <AccessTimeIcon className="w-4 h-4 inline mr-1" />
                            <Trans message="Expires After" />
                          </label>
                          <select
                            value={transferOptions.expiresInDays || 7}
                            onChange={(e) => setTransferOptions(prev => ({ ...prev, expiresInDays: parseInt(e.target.value) }))}
                            className="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                          >
                            <option value={1}>1 day</option>
                            <option value={3}>3 days</option>
                            <option value={7}>1 week</option>
                            <option value={14}>2 weeks</option>
                            <option value={30}>1 month</option>
                          </select>
                        </div>
                      </div>
                    </motion.div>
                  )}
                </AnimatePresence>
              </motion.div>
            )}
            {/* Upload Button */}
            {files.length > 0 && (
              <motion.div
                initial={{ y: 20, opacity: 0 }}
                animate={{ y: 0, opacity: 1 }}
                className="mt-8"
              >
                <button 
                  onClick={uploadFiles} 
                  disabled={isUploading}
                  className={`w-full py-4 rounded-xl font-semibold text-lg transition-all ${
                    isUploading 
                      ? 'bg-gray-400 text-gray-200 cursor-not-allowed' 
                      : 'bg-blue-600 text-white hover:bg-blue-700 hover:shadow-lg'
                  }`}
                >
                  {isUploading ? (
                    <div className="flex items-center justify-center gap-2">
                      <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                      <Trans message="Uploading..." />
                    </div>
                  ) : (
                    <Trans message="Upload {count} {count, plural, one {file} other {files}}" values={{ count: files.length }} />
                  )}
                </button>
              </motion.div>
            )}
          </motion.div>
        </div>
      </div>
    </div>
  );
}
