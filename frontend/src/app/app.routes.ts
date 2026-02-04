import { Routes } from '@angular/router';
import { MainLayoutComponent } from '@app/layout/main-layout/main-layout.component';

export const routes: Routes = [
  {
    path: '',
    component: MainLayoutComponent,
    children: [
      {
        path: '',
        loadComponent: () => import('./features/home/home.component').then(m => m.HomeComponent)
      },
      {
        path: 'auth',
        loadChildren: () => import('./features/auth/auth.routes').then(m => m.AUTH_ROUTES)
      },
      {
        path: 'articles',
        loadChildren: () => import('./features/articles/articles.routes').then(m => m.ARTICLES_ROUTES)
      },
      {
        path: 'search',
        loadComponent: () => import('./features/articles/search/article-search.component').then(m => m.ArticleSearchComponent)
      }
    ]
  },
  {
    path: '**',
    redirectTo: ''
  }
];
