import React, { useState, useCallback } from 'react';
import { useDropzone } from 'react-dropzone';
import axios from 'axios';
import { Navbar } from '@common/ui/navigation/navbar/navbar';
import { Footer } from '@common/ui/footer/footer';
import { DefaultMetaTags } from '@common/seo/default-meta-tags';
import { Trans } from '@ui/i18n/trans';
import { Fragment } from 'react';

interface UploadFile {
  file: File;
  id: string;
  progress: number;
  status: 'pending' | 'uploading' | 'completed' | 'error';
  error?: string;
}

interface TransferOptions {
  senderEmail?: string;
  senderName?: string;
  recipientEmails?: string[];
  message?: string;
  password?: string;
  expiresInDays?: number;
  maxDownloads?: number;
}

const formatBytes = (bytes: number, decimals = 2) => {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const dm = decimals < 0 ? 0 : decimals;
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
};

export function TransferLandingPage() {
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

    setIsUploading(true);
    const formData = new FormData();

    // Add files to FormData
    files.forEach(({ file }) => {
      formData.append('files[]', file);
    });

    // Add transfer options
    Object.entries(transferOptions).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        if (Array.isArray(value)) {
          value.forEach((item, index) => {
            formData.append(`${key}[${index}]`, item);
          });
        } else {
          formData.append(key, value.toString());
        }
      }
    });

    try {
      const response = await axios.post('/api/v1/transfer', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
        onUploadProgress: (progressEvent) => {
          const progress = progressEvent.total 
            ? Math.round((progressEvent.loaded * 100) / progressEvent.total)
            : 0;
          setFiles(prev => prev.map(f => ({ ...f, progress, status: 'uploading' })));
        },
      });

      setFiles(prev => prev.map(f => ({ ...f, progress: 100, status: 'completed' })));
      setTransferResult(response.data);
    } catch (error: any) {
      console.error('Upload failed:', error);
      setFiles(prev => prev.map(f => ({
        ...f,
        status: 'error',
        error: error.response?.data?.message || 'Upload failed'
      })));
    } finally {
      setIsUploading(false);
    }
  };

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    // Show toast notification - you can implement this
    alert('Copied to clipboard!');
  };

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop,
    maxSize: 1024 * 1024 * 1024 * 2, // 2GB
    multiple: true,
  });

  if (transferResult) {
    return (
      <Fragment>
        <DefaultMetaTags />
        <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100">
          <Navbar color="transparent" className="flex-shrink-0" menuPosition="homepage-navbar" />
          
          <div className="container mx-auto px-4 py-20">
            <div className="max-w-2xl mx-auto">
              <div className="bg-white rounded-2xl shadow-lg p-8 text-center">
                <div className="text-6xl mb-6">✅</div>
                <h2 className="text-3xl font-bold text-gray-800 mb-6">Files uploaded successfully!</h2>
                
                <div className="space-y-4 mb-8 text-left">
                  <div className="flex justify-between py-2 border-b">
                    <span className="font-medium text-gray-600">Transfer ID:</span>
                    <span className="text-gray-800">{transferResult.transfer.hash}</span>
                  </div>
                  <div className="flex justify-between py-2 border-b">
                    <span className="font-medium text-gray-600">Files:</span>
                    <span className="text-gray-800">{transferResult.file_count}</span>
                  </div>
                  <div className="flex justify-between py-2 border-b">
                    <span className="font-medium text-gray-600">Total Size:</span>
                    <span className="text-gray-800">{formatBytes(transferResult.total_size)}</span>
                  </div>
                  <div className="flex justify-between py-2 border-b">
                    <span className="font-medium text-gray-600">Expires:</span>
                    <span className="text-gray-800">{new Date(transferResult.expires_at).toLocaleDateString()}</span>
                  </div>
                </div>

                <div className="space-y-4 mb-8">
                  <div className="bg-gray-50 p-4 rounded-lg">
                    <label className="block text-sm font-medium text-gray-700 mb-2">Share Link:</label>
                    <div className="flex gap-2">
                      <input 
                        type="text" 
                        value={transferResult.share_url} 
                        readOnly 
                        className="flex-1 p-2 border rounded-lg bg-white text-sm"
                      />
                      <button 
                        onClick={() => copyToClipboard(transferResult.share_url)}
                        className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm"
                      >
                        Copy
                      </button>
                    </div>
                  </div>
                  
                  <div className="bg-gray-50 p-4 rounded-lg">
                    <label className="block text-sm font-medium text-gray-700 mb-2">Download Link:</label>
                    <div className="flex gap-2">
                      <input 
                        type="text" 
                        value={transferResult.download_url} 
                        readOnly 
                        className="flex-1 p-2 border rounded-lg bg-white text-sm"
                      />
                      <button 
                        onClick={() => copyToClipboard(transferResult.download_url)}
                        className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm"
                      >
                        Copy
                      </button>
                    </div>
                  </div>
                </div>

                <button 
                  onClick={() => {
                    setFiles([]);
                    setTransferResult(null);
                    setTransferOptions({});
                  }}
                  className="w-full py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium"
                >
                  Start New Transfer
                </button>
              </div>
            </div>
          </div>
          
          <Footer className="mt-auto" />
        </div>
      </Fragment>
    );
  }

  return (
    <Fragment>
      <DefaultMetaTags />
      <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100">
        <Navbar color="transparent" className="flex-shrink-0" menuPosition="homepage-navbar" />
        
        <div className="container mx-auto px-4 py-20">
          <div className="text-center mb-12">
            <h1 className="text-5xl font-bold text-gray-800 mb-4">
              <Trans message="Send files like a pro" />
            </h1>
            <p className="text-xl text-gray-600 max-w-2xl mx-auto">
              <Trans message="Share large files easily and securely without any registration required." />
            </p>
          </div>

          <div className="max-w-4xl mx-auto">
            <div className="bg-white rounded-2xl shadow-lg p-8">
              {/* Upload Area */}
              <div
                {...getRootProps({
                  className: `relative border-2 border-dashed rounded-xl p-12 text-center transition-colors ${
                    isDragActive 
                      ? 'border-blue-400 bg-blue-50' 
                      : files.length > 0 
                        ? 'border-green-300 bg-green-50' 
                        : 'border-gray-300 hover:border-blue-400 hover:bg-blue-50'
                  }`,
                })}
              >
                <input {...getInputProps()} />
                {isDragActive ? (
                  <div className="space-y-4">
                    <div className="text-6xl">📁</div>
                    <p className="text-xl text-blue-600">Drop files here...</p>
                  </div>
                ) : (
                  <div className="space-y-4">
                    <div className="text-6xl">☁️</div>
                    <h3 className="text-2xl font-semibold text-gray-800">
                      <Trans message="Drag and drop files here" />
                    </h3>
                    <p className="text-gray-600">
                      <Trans message="or" /> <span className="text-blue-600 hover:underline cursor-pointer font-medium">browse</span>
                    </p>
                    <p className="text-sm text-gray-500">
                      <Trans message="Maximum file size: 2GB" />
                    </p>
                  </div>
                )}
              </div>

              {/* Files List */}
              {files.length > 0 && (
                <div className="mt-8">
                  <h4 className="text-lg font-semibold mb-4">Files to transfer ({files.length})</h4>
                  <div className="space-y-3">
                    {files.map((fileItem) => (
                      <div key={fileItem.id} className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div className="flex-1 min-w-0">
                          <div className="font-medium text-gray-800 truncate">{fileItem.file.name}</div>
                          <div className="text-sm text-gray-500">{formatBytes(fileItem.file.size)}</div>
                        </div>
                        
                        <div className="flex items-center gap-3">
                          {fileItem.status === 'uploading' && (
                            <div className="w-32 bg-gray-200 rounded-full h-2">
                              <div 
                                className="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                                style={{ width: `${fileItem.progress}%` }}
                              />
                            </div>
                          )}
                          {fileItem.status === 'completed' && <span className="text-green-600 text-xl">✅</span>}
                          {fileItem.status === 'error' && <span className="text-red-600 text-xl">❌</span>}
                          {fileItem.status === 'pending' && (
                            <button 
                              onClick={() => removeFile(fileItem.id)} 
                              className="text-red-500 hover:text-red-700 text-xl font-bold"
                            >
                              ×
                            </button>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Transfer Options */}
              {files.length > 0 && (
                <div className="mt-8">
                  <button 
                    type="button" 
                    onClick={() => setShowOptions(!showOptions)}
                    className="flex items-center gap-2 text-blue-600 hover:text-blue-700 font-medium"
                  >
                    <Trans message="Transfer Options" /> {showOptions ? '▼' : '▶'}
                  </button>
                  
                  {showOptions && (
                    <div className="mt-4 space-y-4 p-4 bg-gray-50 rounded-lg">
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <input
                          type="email"
                          placeholder="Your email (optional)"
                          value={transferOptions.senderEmail || ''}
                          onChange={(e) => setTransferOptions(prev => ({ ...prev, senderEmail: e.target.value }))}
                          className="p-3 border rounded-lg"
                        />
                        <input
                          type="text"
                          placeholder="Your name (optional)"
                          value={transferOptions.senderName || ''}
                          onChange={(e) => setTransferOptions(prev => ({ ...prev, senderName: e.target.value }))}
                          className="p-3 border rounded-lg"
                        />
                      </div>
                      <textarea
                        placeholder="Message to recipients (optional)"
                        value={transferOptions.message || ''}
                        onChange={(e) => setTransferOptions(prev => ({ ...prev, message: e.target.value }))}
                        className="w-full p-3 border rounded-lg"
                        rows={3}
                      />
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <input
                          type="password"
                          placeholder="Password protection (optional)"
                          value={transferOptions.password || ''}
                          onChange={(e) => setTransferOptions(prev => ({ ...prev, password: e.target.value }))}
                          className="p-3 border rounded-lg"
                        />
                        <select
                          value={transferOptions.expiresInDays || 7}
                          onChange={(e) => setTransferOptions(prev => ({ ...prev, expiresInDays: parseInt(e.target.value) }))}
                          className="p-3 border rounded-lg"
                        >
                          <option value={1}>1 day</option>
                          <option value={3}>3 days</option>
                          <option value={7}>1 week</option>
                          <option value={14}>2 weeks</option>
                          <option value={30}>1 month</option>
                        </select>
                      </div>
                    </div>
                  )}
                </div>
              )}

              {/* Upload Button */}
              {files.length > 0 && (
                <div className="mt-8">
                  <button 
                    onClick={uploadFiles} 
                    disabled={isUploading}
                    className={`w-full py-4 rounded-lg font-semibold text-lg transition-colors ${
                      isUploading 
                        ? 'bg-gray-400 text-gray-200 cursor-not-allowed' 
                        : 'bg-indigo-600 text-white hover:bg-indigo-700'
                    }`}
                  >
                    {isUploading ? 'Uploading...' : `Transfer ${files.length} file${files.length > 1 ? 's' : ''}`}
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>
        
        <Footer className="mt-auto" />
      </div>
    </Fragment>
  );
}
