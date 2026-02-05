import { Component, OnInit, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
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
    <h2 mat-dialog-title>Agregar a Coleccion</h2>
    <mat-dialog-content>
      <p class="subtitle">Selecciona una coleccion para agregar el Articulo {{ data.articleNumber }}</p>

      @if (collectionService.isLoading()) {
        <div class="loading">
          <mat-spinner diameter="40"></mat-spinner>
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
  `,
  styles: [`
    :host {
      display: block;
      background: white;
    }

    h2[mat-dialog-title] {
      margin: 0;
      padding: 16px 24px;
      background: white;
    }

    mat-dialog-content {
      background: white;
      min-width: 350px;
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
      justify-content: center;
      padding: 40px;
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
      background: white;
    }

    mat-list-option {
      background: white;
    }
  `]
})
export class AddToCollectionDialogComponent implements OnInit {
  collectionService = inject(CollectionService);
  private dialogRef = inject(MatDialogRef<AddToCollectionDialogComponent>);
  data: AddToCollectionDialogData = inject(MAT_DIALOG_DATA);

  ngOnInit(): void {
    this.collectionService.getCollections().subscribe();
  }

  isArticleInCollection(collection: Collection): boolean {
    return collection.articleIds.includes(this.data.articleId);
  }

  onSelect(event: any): void {
    const selected = event.options[0]?.value as Collection;
    if (selected && !this.isArticleInCollection(selected)) {
      this.dialogRef.close(selected);
    }
  }
}
