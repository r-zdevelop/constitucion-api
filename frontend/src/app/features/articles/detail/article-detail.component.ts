import { Component, OnInit, OnDestroy, inject, computed } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { MatCardModule } from '@angular/material/card';
import { MatChipsModule } from '@angular/material/chips';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDividerModule } from '@angular/material/divider';
import { ArticleService } from '@app/core/services/article.service';

@Component({
  selector: 'app-article-detail',
  standalone: true,
  imports: [
    RouterLink,
    MatCardModule,
    MatChipsModule,
    MatButtonModule,
    MatIconModule,
    MatProgressSpinnerModule,
    MatDividerModule
  ],
  template: `
    <div class="detail-container">
      <a 
        mat-button 
        routerLink="/articles" 
        [queryParams]="{ ...listQueryParams(), scrollTo: articleService.currentArticle()?.articleNumber }" 
        class="back-button"
      >
        <mat-icon>arrow_back</mat-icon>
        Volver a la lista
      </a>

      @if (articleService.isLoading()) {
        <div class="loading">
          <mat-spinner></mat-spinner>
        </div>
      } @else if (articleService.currentArticle(); as article) {
        <mat-card class="article-detail">
          <mat-card-header>
            <mat-card-title>
              Articulo {{ article.articleNumber }}
              @if (article.title) {
                <span class="article-title"> - {{ article.title }}</span>
              }
            </mat-card-title>
            <mat-card-subtitle>
              <mat-chip-set>
                <mat-chip color="primary" highlighted>
                  {{ article.chapter }}
                </mat-chip>
              </mat-chip-set>
            </mat-card-subtitle>
          </mat-card-header>

          <mat-card-content>
            <div class="article-content">
              {{ article.content }}
            </div>

            @if (article.concordances && article.concordances.length > 0) {
              <mat-divider></mat-divider>
              <section class="concordances">
                <h3>
                  <mat-icon>link</mat-icon>
                  Concordancias
                </h3>
                <div class="concordance-list">
                  @for (concordance of article.concordances; track concordance.referencedLaw) {
                    <mat-card class="concordance-card">
                      <mat-card-header>
                        <mat-card-title>{{ concordance.referencedLaw }}</mat-card-title>
                      </mat-card-header>
                      <mat-card-content>
                        <p>Articulos: {{ concordance.referencedArticles.join(', ') }}</p>
                      </mat-card-content>
                    </mat-card>
                  }
                </div>
              </section>
            }
          </mat-card-content>

          <mat-card-actions>
            <div class="navigation-buttons">
              @if (article.articleNumber > 1) {
                <a mat-stroked-button [routerLink]="['/articles', article.articleNumber - 1]">
                  <mat-icon>chevron_left</mat-icon>
                  Art. {{ article.articleNumber - 1 }}
                </a>
              }
              <span class="spacer"></span>
              <a mat-stroked-button [routerLink]="['/articles', article.articleNumber + 1]">
                Art. {{ article.articleNumber + 1 }}
                <mat-icon>chevron_right</mat-icon>
              </a>
            </div>
          </mat-card-actions>
        </mat-card>
      } @else {
        <div class="not-found">
          <mat-icon>error_outline</mat-icon>
          <p>Articulo no encontrado</p>
          <a mat-raised-button color="primary" routerLink="/articles">
            Ver todos los articulos
          </a>
        </div>
      }
    </div>
  `,
  styles: [`
    .detail-container {
      max-width: 900px;
      margin: 0 auto;
    }

    .back-button {
      margin-bottom: 16px;
    }

    .loading {
      display: flex;
      justify-content: center;
      padding: 60px;
    }

    .article-detail {
      padding: 24px;
    }

    mat-card-title {
      font-size: 1.6rem;
    }

    .article-title {
      font-weight: normal;
      color: var(--lex-color-secondary);
    }

    mat-card-subtitle {
      margin-top: 12px;
    }

    .article-content {
      font-size: 1.1rem;
      line-height: 1.8;
      color: var(--lex-color-dark);
      white-space: pre-wrap;
      margin: 24px 0;
    }

    mat-divider {
      margin: 32px 0;
    }

    .concordances h3 {
      display: flex;
      align-items: center;
      gap: 8px;
      color: var(--lex-color-primary);
      margin-bottom: 16px;
    }

    .concordance-list {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 16px;
    }

    .concordance-card {
      background-color: var(--lex-color-light);
    }

    .concordance-card mat-card-title {
      font-size: 1rem;
      color: var(--lex-color-dark);
    }

    .concordance-card p {
      font-size: 0.9rem;
      color: var(--lex-color-secondary);
      margin: 0;
    }

    .navigation-buttons {
      display: flex;
      width: 100%;
      padding: 16px 0;
    }

    .navigation-buttons .spacer {
      flex: 1;
    }

    .not-found {
      text-align: center;
      padding: 60px;
    }

    .not-found mat-icon {
      font-size: 64px;
      width: 64px;
      height: 64px;
      color: var(--lex-color-muted);
    }

    .not-found p {
      font-size: 1.2rem;
      color: var(--lex-color-secondary);
      margin: 16px 0 24px;
    }
  `]
})
export class ArticleDetailComponent implements OnInit, OnDestroy {
  private route = inject(ActivatedRoute);
  articleService = inject(ArticleService);

  listQueryParams = computed(() => {
    const pagination = this.articleService.pagination();
    const chapter = this.articleService.currentChapter();

    const params: Record<string, string | number> = {};
    if (pagination && pagination.currentPage > 1) {
      params['page'] = pagination.currentPage;
    }
    if (pagination && pagination.itemsPerPage !== 10) {
      params['limit'] = pagination.itemsPerPage;
    }
    if (chapter) {
      params['chapter'] = chapter;
    }
    return params;
  });

  ngOnInit(): void {
    this.route.params.subscribe(params => {
      const articleNumber = +params['number'];
      if (articleNumber) {
        this.articleService.getArticleByNumber(articleNumber).subscribe();
      }
    });
  }

  ngOnDestroy(): void {
    this.articleService.clearCurrentArticle();
  }
}
