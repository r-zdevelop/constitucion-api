import { Component, OnInit, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatListModule } from '@angular/material/list';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { CollectionService } from '@app/core/services/collection.service';
import { Collection } from '@app/models';

export interface AddToCollectionDialogData {
  articleId: string;
  articleNumber: number;
}

type DialogState = 'select' | 'saving' | 'success' | 'error';

@Component({
  selector: 'app-add-to-collection-dialog',
  standalone: true,
  imports: [
    RouterLink,
    MatDialogModule,
    MatButtonModule,
    MatListModule,
    MatIconModule,
    MatProgressSpinnerModule
  ],
  template: `
    @if (state() === 'success') {
      <div class="success-view">
        <div class="success-icon">
          <mat-icon>check_circle</mat-icon>
        </div>
        <h2>Articulo guardado</h2>
        <p>El Art. {{ data.articleNumber }} fue agregado a <strong>{{ savedCollection()?.name }}</strong></p>
        <div class="success-actions">
          <button mat-button (click)="dialogRef.close()">Cerrar</button>
          <button mat-flat-button color="primary" (click)="goToCollection()">
            Ver coleccion
          </button>
        </div>
      </div>
    } @else if (state() === 'error') {
      <div class="error-view">
        <div class="error-icon">
          <mat-icon>error</mat-icon>
        </div>
        <h2>Error</h2>
        <p>{{ errorMessage() }}</p>
        <div class="success-actions">
          <button mat-flat-button color="primary" (click)="state.set('select')">Reintentar</button>
        </div>
      </div>
    } @else {
      <h2 mat-dialog-title>Agregar a Coleccion</h2>
      <mat-dialog-content>
        <p class="subtitle">Selecciona una coleccion para guardar el Articulo {{ data.articleNumber }}</p>

        @if (collectionService.isLoading() || state() === 'saving') {
          <div class="loading">
            <mat-spinner diameter="40"></mat-spinner>
            @if (state() === 'saving') {
              <p>Guardando...</p>
            }
          </div>
        } @else if (collectionService.collections().length === 0) {
          <div class="empty-state">
            <mat-icon>folder_open</mat-icon>
            <p>No tienes colecciones</p>
            <p class="hint">Crea una coleccion primero para poder agregar articulos.</p>
          </div>
        } @else {
          <mat-selection-list [multiple]="false" (selectionChange)="onSelect($event)">
            @for (collection of collectionService.collections(); track collection.id) {
              <mat-list-option
                [value]="collection"
                [disabled]="isArticleInCollection(collection)"
              >
                <mat-icon matListItemIcon>folder</mat-icon>
                <div matListItemTitle>{{ collection.name }}</div>
                <div matListItemLine>
                  @if (isArticleInCollection(collection)) {
                    <span class="already-added">Ya esta en esta coleccion</span>
                  } @else {
                    {{ collection.articleCount }} articulos
                  }
                </div>
              </mat-list-option>
            }
          </mat-selection-list>
        }
      </mat-dialog-content>
      <mat-dialog-actions align="end">
        <button mat-button mat-dialog-close>Cancelar</button>
        <a mat-button color="primary" routerLink="/collections" mat-dialog-close>
          <mat-icon>add</mat-icon>
          Nueva coleccion
        </a>
      </mat-dialog-actions>
    }
  `,
  styles: [`
    :host {
      display: block;
      background: white;
      overflow: hidden;
    }

    h2[mat-dialog-title] {
      margin: 0;
      padding: 16px 24px;
      background: white;
    }

    mat-dialog-content {
      background: white;
      overflow-x: hidden;
    }

    mat-dialog-actions {
      background: white;
      padding: 8px 16px;
    }

    .subtitle {
      color: var(--lex-color-secondary);
      margin-bottom: 16px;
    }

    .loading {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 40px;
      gap: 16px;
    }

    .loading p {
      color: var(--lex-color-secondary);
      margin: 0;
    }

    .empty-state {
      text-align: center;
      padding: 32px 16px;
    }

    .empty-state mat-icon {
      font-size: 48px;
      width: 48px;
      height: 48px;
      color: var(--lex-color-muted);
    }

    .empty-state p {
      margin: 8px 0;
      color: var(--lex-color-secondary);
    }

    .empty-state .hint {
      font-size: 0.9rem;
      color: var(--lex-color-muted);
    }

    .already-added {
      color: var(--lex-color-muted);
      font-style: italic;
    }

    mat-selection-list {
      max-height: 300px;
      overflow-y: auto;
      overflow-x: hidden;
      background: white;
    }

    mat-list-option {
      background: white;
    }

    .success-view,
    .error-view {
      text-align: center;
      padding: 32px 24px;
    }

    .success-icon mat-icon {
      font-size: 64px;
      width: 64px;
      height: 64px;
      color: #4caf50;
    }

    .error-icon mat-icon {
      font-size: 64px;
      width: 64px;
      height: 64px;
      color: #f44336;
    }

    .success-view h2,
    .error-view h2 {
      margin: 16px 0 8px;
      font-size: 1.25rem;
      color: var(--lex-color-dark);
    }

    .success-view p,
    .error-view p {
      color: var(--lex-color-secondary);
      margin: 0 0 24px;
    }

    .success-view strong {
      color: var(--lex-color-primary);
    }

    .success-actions {
      display: flex;
      justify-content: center;
      gap: 12px;
    }
  `]
})
export class AddToCollectionDialogComponent implements OnInit {
  collectionService = inject(CollectionService);
  dialogRef = inject(MatDialogRef<AddToCollectionDialogComponent>);
  private router = inject(Router);
  data: AddToCollectionDialogData = inject(MAT_DIALOG_DATA);

  state = signal<DialogState>('select');
  savedCollection = signal<Collection | null>(null);
  errorMessage = signal<string>('');

  ngOnInit(): void {
    this.collectionService.getCollections().subscribe();
  }

  isArticleInCollection(collection: Collection): boolean {
    return collection.articleIds.includes(this.data.articleId);
  }

  onSelect(event: any): void {
    const selected = event.options[0]?.value as Collection;
    if (selected && !this.isArticleInCollection(selected)) {
      this.addToCollection(selected);
    }
  }

  private addToCollection(collection: Collection): void {
    this.state.set('saving');
    this.collectionService.addArticleToCollection(collection.id, this.data.articleId).subscribe({
      next: () => {
        this.savedCollection.set(collection);
        this.state.set('success');
      },
      error: (err) => {
        this.errorMessage.set(err.error?.detail || 'No se pudo agregar el articulo');
        this.state.set('error');
      }
    });
  }

  goToCollection(): void {
    const collection = this.savedCollection();
    if (collection) {
      this.dialogRef.close();
      this.router.navigate(['/collections', collection.id]);
    }
  }
}
