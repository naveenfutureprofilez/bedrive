import {useMutation} from '@tanstack/react-query';
import {apiClient} from '@common/http/query-client';
import {UseFormReturn} from 'react-hook-form';
import {onFormQueryError} from '@common/errors/on-form-query-error';
import {BackendResponse} from '@common/http/backend-response/backend-response';

export interface VerifyTransferPasswordPayload {
  password: string;
}

interface VerifyTransferPasswordResponse extends BackendResponse {
  token?: string;
}

export function useVerifyTransferPassword(
  transferHash: string,
  form?: UseFormReturn<VerifyTransferPasswordPayload>,
) {
  return useMutation({
    mutationFn: (payload: VerifyTransferPasswordPayload) =>
      verifyPassword(transferHash, payload),
    onError: (err) => {
      if (form) {
        onFormQueryError(err, form);
      }
    },
  });
}

function verifyPassword(
  transferHash: string,
  payload: VerifyTransferPasswordPayload
): Promise<VerifyTransferPasswordResponse> {
  return apiClient
    .post(`transfer/${transferHash}/verify-password`, payload)
    .then(response => response.data);
}
