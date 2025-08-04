import React, { useState, useCallback } from 'react';
import { useDropzone } from 'react-dropzone';
import axios from 'axios';
import { formatBytes } from './format-bytes';

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

const UploadComponent: React.FC = () => {
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
    // Show toast notification
  };

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop,
    maxSize: 1024 * 1024 * 1024 * 2, // 2GB
    multiple: true,
  });

  if (transferResult) {
    return (
      <div className="transfer-complete">
        <div className="success-icon">✅</div>
        <h2>Files uploaded successfully!</h2>
        <div className="transfer-info">
          <p><strong>Transfer ID:</strong> {transferResult.transfer.hash}</p>
          <p><strong>Files:</strong> {transferResult.file_count}</p>
          <p><strong>Total Size:</strong> {formatBytes(transferResult.total_size)}</p>
          <p><strong>Expires:</strong> {new Date(transferResult.expires_at).toLocaleDateString()}</p>
        </div>
        <div className="share-links">
          <div className="link-item">
            <label>Share Link:</label>
            <div className="link-input">
              <input type="text" value={transferResult.share_url} readOnly />
              <button onClick={() => copyToClipboard(transferResult.share_url)}>Copy</button>
            </div>
          </div>
          <div className="link-item">
            <label>Download Link:</label>
            <div className="link-input">
              <input type="text" value={transferResult.download_url} readOnly />
              <button onClick={() => copyToClipboard(transferResult.download_url)}>Copy</button>
            </div>
          </div>
        </div>
        <button onClick={() => {
          setFiles([]);
          setTransferResult(null);
          setTransferOptions({});
        }}>Start New Transfer</button>
      </div>
    );
  }

  return (
    <div className="upload-container">
      <div className="upload-area">
        <div
          {...getRootProps({
            className: `dropzone ${isDragActive ? 'active' : ''} ${files.length > 0 ? 'has-files' : ''}`,
          })}
        >
          <input {...getInputProps()} />
          {isDragActive ? (
            <div className="drop-message">
              <div className="upload-icon">📁</div>
              <p>Drop files here...</p>
            </div>
          ) : (
            <div className="upload-message">
              <div className="upload-icon">☁️</div>
              <h3>Send files like a pro</h3>
              <p>Drag and drop files here, or <span className="browse-link">browse</span></p>
              <p className="size-limit">Maximum file size: 2GB</p>
            </div>
          )}
        </div>

        {files.length > 0 && (
          <div className="files-list">
            <h4>Files to transfer ({files.length})</h4>
            {files.map((fileItem) => (
              <div key={fileItem.id} className="file-item">
                <div className="file-info">
                  <span className="file-name">{fileItem.file.name}</span>
                  <span className="file-size">{formatBytes(fileItem.file.size)}</span>
                </div>
                <div className="file-actions">
                  {fileItem.status === 'uploading' && (
                    <div className="progress-bar">
                      <div 
                        className="progress-fill" 
                        style={{ width: `${fileItem.progress}%` }}
                      />
                    </div>
                  )}
                  {fileItem.status === 'completed' && <span className="status completed">✅</span>}
                  {fileItem.status === 'error' && <span className="status error">❌</span>}
                  {fileItem.status === 'pending' && (
                    <button onClick={() => removeFile(fileItem.id)} className="remove-btn">×</button>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}

        {files.length > 0 && (
          <div className="transfer-options">
            <button 
              type="button" 
              onClick={() => setShowOptions(!showOptions)}
              className="options-toggle"
            >
              Transfer Options {showOptions ? '▼' : '▶'}
            </button>
            
            {showOptions && (
              <div className="options-form">
                <div className="form-row">
                  <input
                    type="email"
                    placeholder="Your email (optional)"
                    value={transferOptions.senderEmail || ''}
                    onChange={(e) => setTransferOptions(prev => ({ ...prev, senderEmail: e.target.value }))}
                  />
                  <input
                    type="text"
                    placeholder="Your name (optional)"
                    value={transferOptions.senderName || ''}
                    onChange={(e) => setTransferOptions(prev => ({ ...prev, senderName: e.target.value }))}
                  />
                </div>
                <textarea
                  placeholder="Message to recipients (optional)"
                  value={transferOptions.message || ''}
                  onChange={(e) => setTransferOptions(prev => ({ ...prev, message: e.target.value }))}
                />
                <div className="form-row">
                  <input
                    type="password"
                    placeholder="Password protection (optional)"
                    value={transferOptions.password || ''}
                    onChange={(e) => setTransferOptions(prev => ({ ...prev, password: e.target.value }))}
                  />
                  <select
                    value={transferOptions.expiresInDays || 7}
                    onChange={(e) => setTransferOptions(prev => ({ ...prev, expiresInDays: parseInt(e.target.value) }))}
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

        {files.length > 0 && (
          <div className="upload-actions">
            <button 
              onClick={uploadFiles} 
              disabled={isUploading}
              className="upload-btn"
            >
              {isUploading ? 'Uploading...' : `Transfer ${files.length} file${files.length > 1 ? 's' : ''}`}
            </button>
          </div>
        )}
      </div>
    </div>
  );
};

export default UploadComponent;
