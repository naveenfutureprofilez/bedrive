import React, { useState, useEffect, useRef } from 'react';
import { FileEntry } from '@common/uploads/file-entry';
import { FileThumbnail } from '@common/uploads/components/file-type-icon/file-thumbnail';
import { ImageIcon } from '@ui/icons/material/Image';
import { BrokenImageIcon } from '@ui/icons/material/BrokenImage';
import { apiClient } from '@common/http/query-client';
import { useIntersectionObserver } from '../hooks/use-intersection-observer';

interface LazyImagePreviewProps {
  file: FileEntry | { 
    hash: string; 
    name: string; 
    mime: string; 
    extension?: string;
  } | File;
  className?: string;
  maxWidth?: number;
  maxHeight?: number;
  showFallback?: boolean;
  onError?: () => void;
  onLoad?: () => void;
}

interface ThumbnailResponse {
  thumbnail_url?: string;
  expires_at: string;
}

export function LazyImagePreview({
  file,
  className = '',
  maxWidth = 400,
  maxHeight = 400,
  showFallback = true,
  onError,
  onLoad,
}: LazyImagePreviewProps) {
  const [imageUrl, setImageUrl] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [hasError, setHasError] = useState(false);
  const [hasLoaded, setHasLoaded] = useState(false);
  
  const imgRef = useRef<HTMLImageElement>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  
  // Use intersection observer for lazy loading
  const { isIntersecting } = useIntersectionObserver(containerRef, {
    threshold: 0.1,
    rootMargin: '50px',
  });

  // Check if file is an image
  const isImage = file instanceof File 
    ? file.type?.startsWith('image/') 
    : file.mime?.startsWith('image/');
  
  // Check if this is a local File object
  const isLocalFile = file instanceof File;

  useEffect(() => {
    if (!isIntersecting || !isImage || imageUrl || hasError) {
      return;
    }

    if (isLocalFile) {
      const cleanup = loadLocalFilePreview();
      return cleanup;
    } else {
      loadThumbnail();
    }
  }, [isIntersecting, isImage, isLocalFile]);

  // Cleanup blob URLs on unmount
  useEffect(() => {
    return () => {
      if (imageUrl && imageUrl.startsWith('blob:')) {
        URL.revokeObjectURL(imageUrl);
      }
    };
  }, [imageUrl]);

  const loadLocalFilePreview = () => {
    try {
      setIsLoading(true);
      setHasError(false);
      
      if (file instanceof File) {
        const blobUrl = URL.createObjectURL(file);
        setImageUrl(blobUrl);
        
        // Clean up blob URL when component unmounts
        return () => URL.revokeObjectURL(blobUrl);
      }
    } catch (error) {
      console.warn(`Failed to create preview for ${file.name}:`, error);
      setHasError(true);
    } finally {
      setIsLoading(false);
    }
  };

  const loadThumbnail = async () => {
    try {
      setIsLoading(true);
      setHasError(false);

      // Type guard to ensure file has hash property
      if (file instanceof File) {
        throw new Error('Cannot load thumbnail for local File object');
      }

      const response = await apiClient.get<ThumbnailResponse>(
        `thumbnails/${file.hash}`
      );

      if (response.data.thumbnail_url) {
        setImageUrl(response.data.thumbnail_url);
      } else {
        // No thumbnail available, show fallback
        setHasError(true);
      }
    } catch (error) {
      console.warn(`Failed to load thumbnail for ${file.name}:`, error);
      setHasError(true);
    } finally {
      setIsLoading(false);
    }
  };

  const handleImageLoad = () => {
    setHasLoaded(true);
    onLoad?.();
  };

  const handleImageError = () => {
    setHasError(true);
    setImageUrl(null);
    onError?.();
  };

  // Transform file to FileThumbnail expected format
  const getFileThumbnailProps = () => {
    if (file instanceof File) {
      return {
        name: file.name,
        type: file.type.startsWith('image/') ? 'image' : 'file',
        hash: 'local-file',
        file_name: file.name,
      };
    } else {
      return {
        name: file.name,
        type: file.mime?.startsWith('image/') ? 'image' : 'file',
        hash: file.hash,
        file_name: file.name,
      };
    }
  };

  // Show file type icon for non-images or when thumbnail fails
  if (!isImage || (hasError && showFallback)) {
    return (
      <div
        ref={containerRef}
        className={`flex items-center justify-center bg-gray-50 rounded-lg ${className}`}
        style={{ maxWidth, maxHeight, minWidth: 80, minHeight: 80 }}
      >
        <FileThumbnail
          file={getFileThumbnailProps()}
          iconClassName="w-8 h-8 text-gray-400"
          showImage={false}
        />
      </div>
    );
  }

  // Loading state
  if (isLoading || (!imageUrl && !hasError)) {
    return (
      <div
        ref={containerRef}
        className={`flex items-center justify-center bg-gray-50 rounded-lg animate-pulse ${className}`}
        style={{ maxWidth, maxHeight, minWidth: 80, minHeight: 80 }}
      >
        <ImageIcon className="w-8 h-8 text-gray-300" />
      </div>
    );
  }

  // Error state
  if (hasError) {
    return (
      <div
        ref={containerRef}
        className={`flex items-center justify-center bg-gray-50 rounded-lg ${className}`}
        style={{ maxWidth, maxHeight, minWidth: 80, minHeight: 80 }}
      >
        <BrokenImageIcon className="w-8 h-8 text-gray-400" />
      </div>
    );
  }

  // Image preview
  return (
    <div
      ref={containerRef}
      className={`relative overflow-hidden rounded-lg bg-gray-50 ${className}`}
      style={{ maxWidth, maxHeight }}
    >
      <img
        ref={imgRef}
        src={imageUrl!}
        alt={file.name}
        className={`w-full h-full object-cover transition-opacity duration-300 ${
          hasLoaded ? 'opacity-100' : 'opacity-0'
        }`}
        onLoad={handleImageLoad}
        onError={handleImageError}
        loading="lazy"
      />
      
      {/* Loading overlay */}
      {!hasLoaded && (
        <div className="absolute inset-0 flex items-center justify-center bg-gray-50">
          <ImageIcon className="w-8 h-8 text-gray-300 animate-pulse" />
        </div>
      )}
    </div>
  );
}
