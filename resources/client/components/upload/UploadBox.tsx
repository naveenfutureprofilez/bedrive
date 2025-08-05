import React, { useCallback, useState } from 'react';
import { useDropzone } from 'react-dropzone';
import { motion, AnimatePresence } from 'framer-motion';
import { UploadIcon } from '@ui/icons/material/Upload';
import { FolderIcon } from '@ui/icons/material/Folder';
import { Trans } from '@ui/i18n/trans';
import { useFileUploadStore } from '@common/uploads/uploader/file-upload-provider';
import clsx from 'clsx';

interface UploadBoxProps {
  className?: string;
  maxFiles?: number;
  maxSize?: number; // in bytes
  accept?: Record<string, string[]>;
  onFilesSelected?: (files: File[]) => void;
  disabled?: boolean;
  compact?: boolean;
}

export function UploadBox({
  className,
  maxFiles = 50,
  maxSize = 2 * 1024 * 1024 * 1024, // 2GB default
  accept,
  onFilesSelected,
  disabled = false,
  compact = false,
}: UploadBoxProps) {
  const [dragCount, setDragCount] = useState(0);
  const uploadMultiple = useFileUploadStore(s => s.uploadMultiple);

  const onDrop = useCallback((acceptedFiles: File[], rejectedFiles: any[]) => {
    if (acceptedFiles.length > 0) {
      onFilesSelected?.(acceptedFiles);
      // Auto-upload using existing Bedrive upload system
      uploadMultiple(acceptedFiles);
    }
  }, [onFilesSelected, uploadMultiple]);

  const {
    getRootProps,
    getInputProps,
    isDragActive,
    isDragReject,
    fileRejections,
  } = useDropzone({
    onDrop,
    maxFiles,
    maxSize,
    accept,
    disabled,
    onDragEnter: () => setDragCount(prev => prev + 1),
    onDragLeave: () => setDragCount(prev => prev - 1),
  });

  const hasErrors = fileRejections.length > 0;

  return (
    <div className={clsx('relative', className)}>
      <motion.div
        {...getRootProps()}
        className={clsx(
          'relative border-2 border-dashed rounded-xl transition-all cursor-pointer',
          'focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2',
          {
            // Size variants
            'p-8': compact,
            'p-12': !compact,
            
            // State variants
            'border-primary bg-primary/5': isDragActive && !isDragReject,
            'border-danger bg-danger/5': isDragReject || hasErrors,
            'border-gray-300 hover:border-primary/50 hover:bg-primary/2': 
              !isDragActive && !isDragReject && !hasErrors && !disabled,
            'border-gray-200 bg-gray-50 cursor-not-allowed opacity-60': disabled,
            
            // Animation states
            'scale-[1.02]': isDragActive,
          }
        )}
        whileHover={!disabled ? { scale: 1.01 } : {}}
        whileTap={!disabled ? { scale: 0.99 } : {}}
        animate={{
          borderColor: isDragActive 
            ? 'rgb(59 130 246)' // blue-500
            : isDragReject || hasErrors
            ? 'rgb(239 68 68)' // red-500
            : 'rgb(209 213 219)', // gray-300
        }}
      >
        <input {...getInputProps()} />
        
        <div className="text-center">
          <AnimatePresence mode="wait">
            {isDragActive ? (
              <motion.div
                key="drag-active"
                initial={{ scale: 0.8, opacity: 0 }}
                animate={{ scale: 1, opacity: 1 }}
                exit={{ scale: 0.8, opacity: 0 }}
                className="space-y-4"
              >
                <div className="mx-auto w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center">
                  <FolderIcon className="w-8 h-8 text-primary" />
                </div>
                <div>
                  <h3 className="text-lg font-semibold text-primary mb-2">
                    <Trans message="Drop files here" />
                  </h3>
                  <p className="text-sm text-muted">
                    <Trans message="Release to upload" />
                  </p>
                </div>
              </motion.div>
            ) : (
              <motion.div
                key="default"
                initial={{ scale: 0.8, opacity: 0 }}
                animate={{ scale: 1, opacity: 1 }}
                exit={{ scale: 0.8, opacity: 0 }}
                className="space-y-4"
              >
                <div className="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center">
                  <UploadIcon className="w-8 h-8 text-gray-400" />
                </div>
                <div>
                  {!compact ? (
                    <>
                      <h3 className="text-xl font-semibold text-gray-900 mb-2">
                        <Trans message="Drag and drop files here" />
                      </h3>
                      <p className="text-gray-600 mb-4">
                        <Trans message="or" /> 
                        <span className="text-primary hover:text-primary/80 font-medium cursor-pointer ml-1">
                          <Trans message="browse files" />
                        </span>
                      </p>
                      <div className="flex flex-col sm:flex-row gap-2 text-sm text-gray-500 justify-center">
                        <span>
                          <Trans 
                            message="Maximum {maxFiles} files" 
                            values={{ maxFiles }} 
                          />
                        </span>
                        <span className="hidden sm:inline">•</span>
                        <span>
                          <Trans 
                            message="Up to {maxSize}" 
                            values={{ maxSize: formatBytes(maxSize) }} 
                          />
                        </span>
                      </div>
                    </>
                  ) : (
                    <div>
                      <p className="text-gray-600 font-medium">
                        <Trans message="Drop files or browse" />
                      </p>
                      <p className="text-xs text-gray-500 mt-1">
                        <Trans 
                          message="Max {maxSize}" 
                          values={{ maxSize: formatBytes(maxSize) }} 
                        />
                      </p>
                    </div>
                  )}
                </div>
              </motion.div>
            )}
          </AnimatePresence>
        </div>

        {/* Error overlay */}
        <AnimatePresence>
          {hasErrors && (
            <motion.div
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -10 }}
              className="absolute inset-x-4 bottom-4 bg-danger text-white p-3 rounded-lg text-sm"
            >
              <div className="font-medium mb-1">
                <Trans message="Upload errors:" />
              </div>
              {fileRejections.map(({ file, errors }, index) => (
                <div key={index} className="text-xs opacity-90">
                  {file.name}: {errors.map(e => e.message).join(', ')}
                </div>
              ))}
            </motion.div>
          )}
        </AnimatePresence>
      </motion.div>
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
