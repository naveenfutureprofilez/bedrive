import {RouteObject} from 'react-router';
import {authGuard} from '@common/auth/guards/auth-route';
import {lazyAdminRoute} from '@common/admin/routes/lazy-admin-route';

export const appAdminRoutes: RouteObject[] = [
  // TRANSFERS
  {
    path: 'transfers',
    loader: () => authGuard({permission: 'admin.access'}),
    lazy: () => lazyAdminRoute('TransferDatatable'),
  },
  {
    path: 'transfers/:transferId',
    loader: () => authGuard({permission: 'admin.access'}),
    lazy: () => lazyAdminRoute('TransferDetailsPage'),
  },
];
