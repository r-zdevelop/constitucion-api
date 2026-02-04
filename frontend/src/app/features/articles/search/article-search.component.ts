import { Component, inject } from '@angular/core';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import { ArticleService } from '@app/core/services/article.service';
import { ArticleCardComponent } from '@app/shared/components/article-card/article-card.component';
import { SearchBarComponent } from '@app/shared/components/search-bar/search-bar.component';

@Component({
  selector: 'app-article-search',
  standalone: true,
  imports: [
    MatProgressSpinnerModule,
    MatPaginatorModule,
    ArticleCardComponent,
    SearchBarComponent
  ],
  template: `
    <div class="search-container">
      <header class="search-header">
        <h1>Buscar Articulos</h1>
        <p class="description">
          Ingresa palabras clave para buscar en el contenido de los articulos
        </p>
      </header>

      <app-search-bar
        placeholder="Buscar en la Constitucion..."
        [minLength]="2"
        [debounceMs]="400"
        (search)="onSearch($event)"
      />

      @if (hasSearched) {
        @if (articleService.isLoading()) {
          <div class="loading">
            <mat-spinner></mat-spinner>
          </div>
        } @else {
          <div class="results">
            @if (articleService.pagination(); as pagination) {
              <p class="results-count">
                {{ pagination.total }} resultado(s) encontrado(s)
              </p>
            }

            <div class="articles-grid">
              @for (article of articleService.articles(); track article.id) {
                <app-article-card [article]="article" [searchTerm]="searchQuery" />
              } @empty {
                <p class="no-results">
                  No se encontraron articulos con "{{ searchQuery }}"
                </p>
              }
            </div>

            @if (articleService.pagination(); as pagination) {
              @if (pagination.total > 0) {
                <mat-paginator
                  [length]="pagination.total"
                  [pageSize]="pagination.itemsPerPage"
                  [pageIndex]="pagination.currentPage - 1"
                  [pageSizeOptions]="[5, 10, 25]"
                  (page)="onPageChange($event)"
                  showFirstLastButtons
                ></mat-paginator>
              }
            }
          </div>
        }
      } @else {
        <div class="empty-state">
          <p>Ingresa al menos 2 caracteres para buscar</p>
        </div>
      }
    </div>
  `,
  styles: [`
    .search-container {
      max-width: 900px;
      margin: 0 auto;
    }

    .search-header {
      text-align: center;
      margin-bottom: 32px;
    }

    h1 {
      margin: 0 0 8px;
      font-size: 1.8rem;
      color: var(--lex-color-dark);
    }

    .description {
      color: var(--lex-color-secondary);
      margin: 0;
    }

    .loading {
      display: flex;
      justify-content: center;
      padding: 60px;
    }

    .results {
      margin-top: 32px;
    }

    .results-count {
      color: var(--lex-color-secondary);
      margin-bottom: 16px;
    }

    .articles-grid {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .no-results,
    .empty-state p {
      text-align: center;
      color: var(--lex-color-secondary);
      padding: 40px;
      font-size: 1.1rem;
    }

    mat-paginator {
      margin-top: 24px;
    }
  `]
})
export class ArticleSearchComponent {
  articleService = inject(ArticleService);

  searchQuery = '';
  hasSearched = false;
  private currentPage = 1;
  private pageSize = 10;

  onSearch(query: string): void {
    this.searchQuery = query;

    if (query.length >= 2) {
      this.hasSearched = true;
      this.currentPage = 1;
      this.performSearch();
    } else {
      this.hasSearched = false;
    }
  }

  performSearch(): void {
    this.articleService.searchArticles(
      this.searchQuery,
      this.currentPage,
      this.pageSize
    ).subscribe();
  }

  onPageChange(event: PageEvent): void {
    this.currentPage = event.pageIndex + 1;
    this.pageSize = event.pageSize;
    this.performSearch();
  }
}
