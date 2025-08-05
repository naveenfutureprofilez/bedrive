import { useMutation, useQuery } from '@tanstack/react-query';
import { apiClient } from '@common/http/query-client';

interface FileHash {
  hash: string;
}

interface CreateDownloadPageRequest {
  title?: string;
  file_hashes: string[];
  expires_at?: string;
}

interface DownloadPageResponse {
  id: string;
  slug: string;
  files: Array<{
    id: string;
    name: string;
    size: number;
    mime: string;
    hash: string;
  }>;
  totalSize: number;
  allowDownload: boolean;
  expiresAt?: string;
  title?: string;
  url?: string;
}

export function useCreateDownloadPage() {
  return useMutation({
    mutationFn: (data: CreateDownloadPageRequest): Promise<DownloadPageResponse> =>
      apiClient.post('download', data).then(response => response.data),
  });
}

export function useDownloadPage(slug: string) {
  return useQuery({
    queryKey: ['download-page', slug],
    queryFn: (): Promise<DownloadPageResponse> =>
      apiClient.get(`download/${slug}`).then(response => response.data),
    enabled: !!slug,
  });
}
