import React, {useState} from 'react';
import {useForm} from 'react-hook-form';
import {Trans} from '@ui/i18n/trans';
import {
  useVerifyTransferPassword,
  VerifyTransferPasswordPayload,
} from './requests/use-verify-transfer-password';
import {toast} from '@ui/toast/toast';

interface TransferPasswordFormProps {
  transferHash: string;
  isOpen: boolean;
  onClose: () => void;
  onSuccess: (token: string) => void;
}

export function TransferPasswordForm({
  transferHash,
  isOpen,
  onClose,
  onSuccess,
}: TransferPasswordFormProps) {
  const [attemptCount, setAttemptCount] = useState(0);
  const [isRateLimited, setIsRateLimited] = useState(false);
  const [retryAfter, setRetryAfter] = useState(0);

  const form = useForm<VerifyTransferPasswordPayload>();
  const verifyPassword = useVerifyTransferPassword(transferHash, form);

  const handleSubmit = form.handleSubmit(async (data) => {
    try {
      const response = await verifyPassword.mutateAsync(data);
      
      if (response.token) {
        // Store the token for authenticated access
        sessionStorage.setItem(`transfer_token_${transferHash}`, response.token);
        onSuccess(response.token);
        onClose();
        form.reset();
        setAttemptCount(0);
        setIsRateLimited(false);
        toast.positive('Password verified successfully');
      }
    } catch (error: any) {
      setAttemptCount(prev => prev + 1);
      
      // Handle rate limiting
      if (error.status === 429) {
        setIsRateLimited(true);
        setRetryAfter(error.response?.data?.retry_after || 60);
        
        toast.danger(
          error.response?.data?.message || 
          'Too many password attempts. Please try again later.'
        );
        
        // Start countdown timer
        const countdown = setInterval(() => {
          setRetryAfter(prev => {
            if (prev <= 1) {
              clearInterval(countdown);
              setIsRateLimited(false);
              return 0;
            }
            return prev - 1;
          });
        }, 1000);
      } else {
        // Show general error message for incorrect password
        toast.danger('Incorrect password. Please try again.');
      }
    }
  });

  const handleClose = () => {
    form.reset();
    setAttemptCount(0);
    onClose();
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50">
      <div className="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div className="flex items-center mb-6">
          <div className="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center mr-4">
            <svg 
              className="w-6 h-6 text-amber-600" 
              fill="none" 
              stroke="currentColor" 
              viewBox="0 0 24 24"
            >
              <path 
                strokeLinecap="round" 
                strokeLinejoin="round" 
                strokeWidth={2} 
                d="M12 15v2m-6 0h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" 
              />
            </svg>
          </div>
          <div>
            <h2 className="text-lg font-semibold text-gray-900">
              <Trans message="Password Required" />
            </h2>
            <p className="text-sm text-gray-600">
              <Trans message="This transfer is password protected" />
            </p>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              <Trans message="Password" />
            </label>
            <input
              {...form.register('password', {
                required: 'Password is required',
                minLength: {
                  value: 1,
                  message: 'Password cannot be empty',
                },
              })}
              type="password"
              placeholder="Enter password..."
              disabled={verifyPassword.isPending || isRateLimited}
              autoFocus
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            {form.formState.errors.password && (
              <p className="text-red-600 text-sm mt-1">
                {form.formState.errors.password.message}
              </p>
            )}
          </div>

          {attemptCount > 0 && !isRateLimited && (
            <div className="text-sm text-amber-600 bg-amber-50 p-3 rounded-md">
              <Trans 
                message="Incorrect password. :count attempts remaining." 
                values={{count: Math.max(0, 5 - attemptCount)}}
              />
            </div>
          )}

          {isRateLimited && (
            <div className="text-sm text-red-600 bg-red-50 p-3 rounded-md">
              <Trans 
                message="Too many failed attempts. Please try again in :seconds seconds." 
                values={{seconds: retryAfter}}
              />
            </div>
          )}

          <div className="flex justify-end space-x-3 pt-4">
            <button
              type="button"
              onClick={handleClose}
              disabled={verifyPassword.isPending}
              className="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 disabled:opacity-50"
            >
              <Trans message="Cancel" />
            </button>
            <button
              type="submit"
              disabled={verifyPassword.isPending || isRateLimited}
              className={`px-4 py-2 text-white rounded-md ${
                verifyPassword.isPending || isRateLimited 
                  ? 'bg-gray-400 cursor-not-allowed' 
                  : 'bg-blue-600 hover:bg-blue-700'
              }`}
            >
              {verifyPassword.isPending ? (
                <span className="flex items-center">
                  <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2" />
                  <Trans message="Verifying..." />
                </span>
              ) : (
                <Trans message="Verify Password" />
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
