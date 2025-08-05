import React, { useState } from 'react';
import { Button } from '@ui/buttons/button';
import { TextField } from '@ui/forms/input-field/text-field/text-field';
import { Trans } from '@ui/i18n/trans';
import { useCreateDownloadPage } from './use-download-page';
import { toast } from '@ui/toast/toast';

export function CreateDownloadDemo() {
  const [title, setTitle] = useState('My Download Package');
  const [fileHashes, setFileHashes] = useState('');
  const createDownloadPage = useCreateDownloadPage();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    const hashes = fileHashes.split(',').map(h => h.trim()).filter(Boolean);
    
    if (hashes.length === 0) {
      toast.danger('Please provide at least one file hash');
      return;
    }

    try {
      const result = await createDownloadPage.mutateAsync({
        title,
        file_hashes: hashes,
        expires_at: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString(), // 7 days
      });

      toast.positive('Download page created successfully!');
      
      // Copy URL to clipboard
      if (result.url && navigator.clipboard) {
        await navigator.clipboard.writeText(result.url);
        toast.positive('URL copied to clipboard!');
      }

      // Reset form
      setTitle('My Download Package');
      setFileHashes('');
      
    } catch (error) {
      toast.danger('Failed to create download page');
      console.error('Error creating download page:', error);
    }
  };

  return (
    <div className="max-w-md mx-auto p-6 bg-white rounded-lg shadow-md">
      <h2 className="text-xl font-bold mb-4">
        <Trans message="Create Download Page" />
      </h2>
      
      <form onSubmit={handleSubmit} className="space-y-4">
        <TextField
          label={<Trans message="Title" />}
          value={title}
          onChange={(e) => setTitle(e.target.value)}
          placeholder="My Download Package"
        />
        
        <TextField
          label={<Trans message="File Hashes (comma-separated)" />}
          value={fileHashes}
          onChange={(e) => setFileHashes(e.target.value)}
          placeholder="hash1, hash2, hash3"
          description={<Trans message="Enter file hashes separated by commas" />}
        />
        
        <Button 
          type="submit" 
          variant="flat" 
          color="primary"
          disabled={createDownloadPage.isPending}
          className="w-full"
        >
          {createDownloadPage.isPending ? (
            <Trans message="Creating..." />
          ) : (
            <Trans message="Create Download Page" />
          )}
        </Button>
      </form>

      {createDownloadPage.data && (
        <div className="mt-6 p-4 bg-green-50 rounded-lg">
          <h3 className="font-semibold text-green-800 mb-2">
            <Trans message="Download Page Created!" />
          </h3>
          <p className="text-sm text-green-700 mb-2">
            <Trans message="Slug: :slug" values={{ slug: createDownloadPage.data.slug }} />
          </p>
          <p className="text-sm text-green-700 mb-2">
            <Trans message="Files: :count" values={{ count: createDownloadPage.data.files.length }} />
          </p>
          {createDownloadPage.data.url && (
            <div className="mt-2">
              <label className="block text-xs font-medium text-green-800 mb-1">
                <Trans message="URL:" />
              </label>
              <input
                type="text"
                value={createDownloadPage.data.url}
                readOnly
                className="w-full px-2 py-1 text-xs bg-white border border-green-300 rounded"
                onFocus={(e) => e.target.select()}
              />
            </div>
          )}
        </div>
      )}
    </div>
  );
}
