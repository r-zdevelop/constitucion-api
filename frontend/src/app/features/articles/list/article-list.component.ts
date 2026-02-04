import { Component, OnInit, inject, AfterViewInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { MatSelectModule } from '@angular/material/select';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import { FormsModule } from '@angular/forms';
import { ArticleService } from '@app/core/services/article.service';
import { ChapterService } from '@app/core/services/chapter.service';
import { ArticleCardComponent } from '@app/shared/components/article-card/article-card.component';

@Component({
  selector: 'app-article-list',
  standalone: true,
  imports: [
    FormsModule,
    MatSelectModule,
    MatFormFieldModule,
    MatProgressSpinnerModule,
    MatPaginatorModule,
    ArticleCardComponent
  ],
  template: `
    <div class="article-list-container">
      <header class="list-header">
        <h1>Articulos de la Constitucion</h1>
        <mat-form-field appearance="outline" class="chapter-filter">
          <mat-label>Filtrar por capitulo</mat-label>
          <mat-select [(ngModel)]="selectedChapter" (selectionChange)="onChapterChange()">
            <mat-option [value]="null">Todos los capitulos</mat-option>
            @for (chapter of chapterService.chapters(); track chapter) {
              <mat-option [value]="chapter">
                {{ chapter }}
              </mat-option>
            }
          </mat-select>
        </mat-form-field>
      </header>

      @if (articleService.isLoading()) {
        <div class="loading">
          <mat-spinner></mat-spinner>
        </div>
      } @else {
        <div class="articles-grid">
          @for (article of articleService.articles(); track article.id) {
            <app-article-card [article]="article" />
          } @empty {
            <p class="no-results">No se encontraron articulos.</p>
          }
        </div>

        @if (articleService.pagination(); as pagination) {
          <mat-paginator
            [length]="pagination.total"
            [pageSize]="pagination.itemsPerPage"
            [pageIndex]="pagination.currentPage - 1"
            [pageSizeOptions]="[5, 10, 25, 50]"
            (page)="onPageChange($event)"
            showFirstLastButtons
          ></mat-paginator>
        }
      }
    </div>
  `,
  styles: [`
    .article-list-container {
      max-width: 900px;
      margin: 0 auto;
    }

    .list-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 16px;
      margin-bottom: 24px;
    }

    h1 {
      margin: 0;
      font-size: 1.8rem;
      color: var(--lex-color-dark);
    }

    .chapter-filter {
      min-width: 300px;
    }

    .loading {
      display: flex;
      justify-content: center;
      padding: 60px;
    }

    .articles-grid {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .no-results {
      text-align: center;
      color: var(--lex-color-secondary);
      padding: 40px;
      font-size: 1.1rem;
    }

    mat-paginator {
      margin-top: 24px;
    }

    @media (max-width: 600px) {
      .list-header {
        flex-direction: column;
        align-items: stretch;
      }

      .chapter-filter {
        min-width: 100%;
      }

      h1 {
        font-size: 1.4rem;
      }
    }
  `]
})
export class ArticleListComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  articleService = inject(ArticleService);
  chapterService = inject(ChapterService);

  selectedChapter: string | null = null;
  currentPage = 1;
  pageSize = 10;

  private scrollToArticle(articleNumber: number): void {
    requestAnimationFrame(() => {
      const el = document.getElementById(`article-${articleNumber}`);
      if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  }

  ngOnInit(): void {
    this.route.queryParams.subscribe(params => {
      this.currentPage = params['page'] ? +params['page'] : 1;
      this.pageSize = params['limit'] ? +params['limit'] : 10;
      this.selectedChapter = params['chapter'] || null;

      const scrollTo = params['scrollTo'] ? +params['scrollTo'] : null;

      this.articleService.getArticles(
        this.currentPage,
        this.pageSize,
        this.selectedChapter ?? undefined
      ).subscribe(() => {
        if (scrollTo) {
          this.scrollToArticle(scrollTo);
        }
      });
    });

    this.chapterService.getChapters().subscribe();
  }


  loadArticles(): void {
    this.articleService.getArticles(
      this.currentPage,
      this.pageSize,
      this.selectedChapter ?? undefined
    ).subscribe();
  }

  onChapterChange(): void {
    this.currentPage = 1;
    this.updateUrl();
  }

  onPageChange(event: PageEvent): void {
    this.currentPage = event.pageIndex + 1;
    this.pageSize = event.pageSize;
    this.updateUrl();
  }

  private updateUrl(): void {
    const queryParams: Record<string, string | number | null> = {
      page: this.currentPage > 1 ? this.currentPage : null,
      limit: this.pageSize !== 10 ? this.pageSize : null,
      chapter: this.selectedChapter
    };
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams,
      queryParamsHandling: 'merge',
      replaceUrl: true
    });
  }
}
