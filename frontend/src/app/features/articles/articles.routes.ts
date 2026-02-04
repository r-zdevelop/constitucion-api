import { Routes } from '@angular/router';

export const ARTICLES_ROUTES: Routes = [
  {
    path: '',
    loadComponent: () => import('./list/article-list.component').then(m => m.ArticleListComponent)
  },
  {
    path: ':number',
    loadComponent: () => import('./detail/article-detail.component').then(m => m.ArticleDetailComponent)
  }
];
