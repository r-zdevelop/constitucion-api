import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDividerModule } from '@angular/material/divider';
import { ArticleService } from '@app/core/services/article.service';
import { ArticleCardComponent } from '@app/shared/components/article-card/article-card.component';
import { SearchBarComponent } from '@app/shared/components/search-bar/search-bar.component';
import { PaginatorComponent, PageChangeEvent } from '@app/shared/components/paginator/paginator.component';
import { Article } from '@app/models';

@Component({
  selector: 'app-article-search',
  standalone: true,
  imports: [
    FormsModule,
    MatFormFieldModule,
    MatInputModule,
    MatIconModule,
    MatButtonModule,
    MatProgressSpinnerModule,
    MatDividerModule,
    ArticleCardComponent,
    SearchBarComponent,
    PaginatorComponent
  ],
  template: `
    <div class="search-container">
      <header class="search-header">
        <h1>Buscar Articulos</h1>
      </header>

      <!-- Busqueda por numero -->
      <section class="search-section">
        <h2 class="section-title">Buscar por Numero de Articulo</h2>
        <p class="description">Ingresa el numero del articulo que deseas consultar</p>
        <mat-form-field appearance="outline" class="number-field">
          <mat-label>Numero de articulo</mat-label>
          <mat-icon matPrefix>tag</mat-icon>
          <input
            matInput
            type="number"
            [(ngModel)]="articleNumber"
            (keyup.enter)="searchByNumber()"
            placeholder="Ej: 40"
            min="1"
          />
          @if (articleNumber) {
            <button
              matSuffix
              mat-icon-button
              aria-label="Limpiar"
              (click)="clearNumberSearch()"
            >
              <mat-icon>close</mat-icon>
            </button>
          }
          <button
            matSuffix
            mat-icon-button
            color="primary"
            aria-label="Buscar"
            (click)="searchByNumber()"
            [disabled]="!articleNumber || isSearchingByNumber()"
            class="search-btn"
          >
            @if (isSearchingByNumber()) {
              <mat-spinner diameter="20"></mat-spinner>
            } @else {
              <mat-icon>search</mat-icon>
            }
          </button>
        </mat-form-field>

        @if (numberSearched()) {
          @if (foundArticle(); as article) {
            <div class="number-result">
              <app-article-card [article]="article" />
            </div>
          } @else if (!isSearchingByNumber()) {
            <p class="no-results">No se encontro el articulo {{ searchedNumber() }}</p>
          }
        }
      </section>

      <mat-divider></mat-divider>

      <!-- Busqueda por palabras clave -->
      <section class="search-section">
        <h2 class="section-title">Buscar por Palabras Clave</h2>
        <p class="description">
          Ingresa palabras clave para buscar en el contenido de los articulos
        </p>

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
                  <app-paginator
                    [pagination]="pagination"
                    [pageSizeOptions]="[5, 10, 25]"
                    (pageChange)="onPageChange($event)"
                  />
                }
              }
            </div>
          }
        } @else {
          <div class="empty-state">
            <p>Ingresa al menos 2 caracteres para buscar</p>
          </div>
        }
      </section>
    </div>
  `,
  styles: [`
    .search-container {
      max-width: 900px;
      margin: 0 auto;
    }

    .search-header {
      text-align: center;
      margin-bottom: 24px;
    }

    h1 {
      margin: 0;
      font-size: 1.8rem;
      color: var(--lex-color-dark);
    }

    .search-section {
      margin: 24px 0;
    }

    .section-title {
      font-size: 1.1rem;
      color: var(--lex-color-dark);
      margin: 0 0 12px;
      font-weight: 500;
    }

    .description {
      color: var(--lex-color-secondary);
      margin: 0 0 16px;
      font-size: 0.9rem;
    }

    .number-field {
      width: 100%;
      max-width: 400px;
    }

    .number-field input {
      font-size: 1.1rem;
    }

    .number-field input::-webkit-outer-spin-button,
    .number-field input::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }

    .number-field input[type=number] {
      -moz-appearance: textfield;
    }

    .search-btn {
      background-color: var(--lex-color-primary);
      color: white;
      border-radius: 50%;
      margin-right: 4px;
    }

    .search-btn:disabled {
      background-color: var(--lex-color-muted);
    }

    .search-btn mat-spinner {
      margin: 0 auto;
    }

    .number-result {
      margin-top: 16px;
    }

    mat-divider {
      margin: 32px 0;
    }

    .loading {
      display: flex;
      justify-content: center;
      padding: 60px;
    }

    .results {
      margin-top: 24px;
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
      padding: 24px;
      font-size: 1rem;
    }

    app-paginator {
      margin-top: 24px;
    }
  `]
})
export class ArticleSearchComponent {
  articleService = inject(ArticleService);

  // Busqueda por numero
  articleNumber: number | null = null;
  foundArticle = signal<Article | null>(null);
  isSearchingByNumber = signal(false);
  numberSearched = signal(false);
  searchedNumber = signal<number | null>(null);

  // Busqueda por palabras clave
  searchQuery = '';
  hasSearched = false;
  private currentPage = 1;
  private pageSize = 10;

  searchByNumber(): void {
    if (!this.articleNumber) return;

    this.isSearchingByNumber.set(true);
    this.numberSearched.set(false);
    this.searchedNumber.set(this.articleNumber);

    this.articleService.getArticleByNumber(this.articleNumber).subscribe({
      next: (article) => {
        this.foundArticle.set(article);
        this.isSearchingByNumber.set(false);
        this.numberSearched.set(true);
      },
      error: () => {
        this.foundArticle.set(null);
        this.isSearchingByNumber.set(false);
        this.numberSearched.set(true);
      }
    });
  }

  clearNumberSearch(): void {
    this.articleNumber = null;
    this.foundArticle.set(null);
    this.numberSearched.set(false);
    this.searchedNumber.set(null);
  }

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

  onPageChange(event: PageChangeEvent): void {
    this.currentPage = event.page;
    this.pageSize = event.pageSize;
    this.performSearch();
  }
}
