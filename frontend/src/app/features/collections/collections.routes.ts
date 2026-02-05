import { Routes } from '@angular/router';
import { authGuard } from '@app/core/auth/guards/auth.guard';

export const COLLECTIONS_ROUTES: Routes = [
  {
    path: '',
    canActivate: [authGuard],
    loadComponent: () => import('./list/collection-list.component').then(m => m.CollectionListComponent)
  },
  {
    path: ':id',
    canActivate: [authGuard],
    loadComponent: () => import('./detail/collection-detail.component').then(m => m.CollectionDetailComponent)
  }
];
