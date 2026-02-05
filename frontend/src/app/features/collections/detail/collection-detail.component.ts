import { Component, OnInit, OnDestroy, inject } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { SlicePipe } from '@angular/common';
import { MatCardModule } from '@angular/material/card';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatChipsModule } from '@angular/material/chips';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { CollectionService } from '@app/core/services/collection.service';
import { EditCollectionDialogComponent } from '../edit-collection-dialog/edit-collection-dialog.component';
import { forkJoin } from 'rxjs';

@Component({
  selector: 'app-collection-detail',
  standalone: true,
  imports: [
    RouterLink,
    SlicePipe,
    MatCardModule,
    MatButtonModule,
    MatIconModule,
    MatProgressSpinnerModule,
    MatChipsModule,
    MatSnackBarModule,
    MatDialogModule
  ],
  template: `
    <div class="detail-container">
      <a mat-button routerLink="/collections" class="back-button">
        <mat-icon>arrow_back</mat-icon>
        Volver a colecciones
      </a>

      @if (collectionService.isLoading()) {
        <div class="loading">
          <mat-spinner></mat-spinner>
        </div>
      } @else if (collectionService.currentCollection(); as collection) {
        <mat-card class="collection-header-card">
          <mat-card-header>
            <mat-icon mat-card-avatar>folder</mat-icon>
            <mat-card-title>{{ collection.name }}</mat-card-title>
            <mat-card-subtitle>
              {{ collection.articleCount }} {{ collection.articleCount === 1 ? 'articulo' : 'articulos' }}
            </mat-card-subtitle>
          </mat-card-header>
          @if (collection.description) {
            <mat-card-content>
              <p>{{ collection.description }}</p>
            </mat-card-content>
          }
          <mat-card-actions align="end">
            <button mat-button (click)="openEditDialog()">
              <mat-icon>edit</mat-icon>
              Editar
            </button>
          </mat-card-actions>
        </mat-card>

        <section class="articles-section">
          <h2>Articulos en esta coleccion</h2>

          @if (collectionService.collectionArticles().length === 0) {
            <div class="empty-articles">
              <mat-icon>article</mat-icon>
              <p>Esta coleccion no tiene articulos</p>
              <p class="hint">Navega a un articulo y agregalo a esta coleccion.</p>
              <a mat-raised-button color="primary" routerLink="/articles">
                <mat-icon>search</mat-icon>
                Explorar articulos
              </a>
            </div>
          } @else {
            <div class="articles-list">
              @for (article of collectionService.collectionArticles(); track article.id) {
                <mat-card class="article-card">
                  <mat-card-header>
                    <mat-card-title>
                      Art. {{ article.articleNumber }}
                      @if (article.title) {
                        <span class="article-title"> - {{ article.title }}</span>
                      }
                    </mat-card-title>
                    <mat-card-subtitle>
                      <mat-chip-set>
                        <mat-chip>{{ article.chapter }}</mat-chip>
                      </mat-chip-set>
                    </mat-card-subtitle>
                  </mat-card-header>
                  <mat-card-content>
                    <p class="article-content">{{ article.content | slice:0:200 }}{{ article.content.length > 200 ? '...' : '' }}</p>
                  </mat-card-content>
                  <mat-card-actions align="end">
                    <button mat-button color="warn" (click)="removeArticle(article.id)">
                      <mat-icon>remove_circle</mat-icon>
                      Quitar
                    </button>
                    <a mat-button color="primary"
                       [routerLink]="['/articles', article.articleNumber]"
                       [queryParams]="{ fromCollection: collection.id }">
                      <mat-icon>visibility</mat-icon>
                      Ver
                    </a>
                  </mat-card-actions>
                </mat-card>
              }
            </div>
          }
        </section>
      } @else {
        <div class="not-found">
          <mat-icon>error_outline</mat-icon>
          <p>Coleccion no encontrada</p>
          <a mat-raised-button color="primary" routerLink="/collections">
            Ver mis colecciones
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

    .collection-header-card {
      margin-bottom: 24px;
    }

    .collection-header-card mat-icon[mat-card-avatar] {
      font-size: 40px;
      width: 40px;
      height: 40px;
      color: var(--lex-color-primary);
    }

    .collection-header-card mat-card-content p {
      color: var(--lex-color-secondary);
      margin: 0;
    }

    .articles-section h2 {
      font-size: 1.4rem;
      color: var(--lex-color-dark);
      margin-bottom: 16px;
    }

    .articles-list {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .article-card {
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .article-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .article-title {
      font-weight: normal;
      font-size: 0.9em;
      color: var(--lex-color-secondary);
    }

    .article-content {
      line-height: 1.6;
      color: var(--lex-color-dark);
    }

    mat-card-subtitle {
      margin-top: 8px;
    }

    .empty-articles {
      text-align: center;
      padding: 40px 20px;
      background: var(--lex-color-light);
      border-radius: 8px;
    }

    .empty-articles mat-icon {
      font-size: 64px;
      width: 64px;
      height: 64px;
      color: var(--lex-color-muted);
    }

    .empty-articles p {
      font-size: 1.1rem;
      color: var(--lex-color-secondary);
      margin: 16px 0 8px;
    }

    .empty-articles .hint {
      font-size: 0.9rem;
      color: var(--lex-color-muted);
      margin-bottom: 24px;
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
export class CollectionDetailComponent implements OnInit, OnDestroy {
  private route = inject(ActivatedRoute);
  collectionService = inject(CollectionService);
  private snackBar = inject(MatSnackBar);
  private dialog = inject(MatDialog);

  ngOnInit(): void {
    this.route.params.subscribe(params => {
      const id = params['id'];
      if (id) {
        forkJoin([
          this.collectionService.getCollection(id),
          this.collectionService.getCollectionArticles(id)
        ]).subscribe();
      }
    });
  }

  ngOnDestroy(): void {
    this.collectionService.clearCurrentCollection();
  }

  removeArticle(articleId: string): void {
    const collection = this.collectionService.currentCollection();
    if (!collection) return;

    if (confirm('¿Estas seguro de quitar este articulo de la coleccion?')) {
      this.collectionService.removeArticleFromCollection(collection.id, articleId).subscribe({
        next: () => {
          this.snackBar.open('Articulo removido de la coleccion', 'Cerrar', {
            duration: 3000
          });
        },
        error: (err) => {
          this.snackBar.open(err.error?.detail || 'Error al remover articulo', 'Cerrar', {
            duration: 5000
          });
        }
      });
    }
  }

  openEditDialog(): void {
    const collection = this.collectionService.currentCollection();
    if (!collection) return;

    const dialogRef = this.dialog.open(EditCollectionDialogComponent, {
      width: '400px',
      maxWidth: '90vw',
      hasBackdrop: true,
      data: collection
    });

    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.collectionService.updateCollection(collection.id, result).subscribe({
          next: () => {
            this.snackBar.open('Coleccion actualizada', 'Cerrar', {
              duration: 3000
            });
          },
          error: (err) => {
            this.snackBar.open(err.error?.detail || 'Error al actualizar coleccion', 'Cerrar', {
              duration: 5000
            });
          }
        });
      }
    });
  }
}
