import { Component, OnInit, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { MatCardModule } from '@angular/material/card';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { CollectionService } from '@app/core/services/collection.service';
import { CreateCollectionDialogComponent } from '../create-collection-dialog/create-collection-dialog.component';
import { Collection } from '@app/models';

@Component({
  selector: 'app-collection-list',
  standalone: true,
  imports: [
    RouterLink,
    MatCardModule,
    MatButtonModule,
    MatIconModule,
    MatProgressSpinnerModule,
    MatDialogModule,
    MatSnackBarModule
  ],
  template: `
    <div class="collections-container">
      <header class="list-header">
        <h1>Mis Colecciones</h1>
        <button mat-raised-button color="primary" (click)="openCreateDialog()">
          <mat-icon>add</mat-icon>
          Nueva Coleccion
        </button>
      </header>

      @if (collectionService.isLoading()) {
        <div class="loading">
          <mat-spinner></mat-spinner>
        </div>
      } @else {
        <div class="collections-grid">
          @for (collection of collectionService.collections(); track collection.id) {
            <mat-card class="collection-card">
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
                <button mat-button color="warn" (click)="deleteCollection(collection)">
                  <mat-icon>delete</mat-icon>
                  Eliminar
                </button>
                <a mat-button color="primary" [routerLink]="['/collections', collection.id]">
                  <mat-icon>visibility</mat-icon>
                  Ver
                </a>
              </mat-card-actions>
            </mat-card>
          } @empty {
            <div class="empty-state">
              <mat-icon>folder_open</mat-icon>
              <p>No tienes colecciones todavia</p>
              <p class="hint">Crea una coleccion para guardar y organizar articulos de la constitucion.</p>
              <button mat-raised-button color="primary" (click)="openCreateDialog()">
                <mat-icon>add</mat-icon>
                Crear mi primera coleccion
              </button>
            </div>
          }
        </div>
      }
    </div>
  `,
  styles: [`
    .collections-container {
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

    .loading {
      display: flex;
      justify-content: center;
      padding: 60px;
    }

    .collections-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 16px;
    }

    .collection-card {
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .collection-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .collection-card mat-icon[mat-card-avatar] {
      font-size: 40px;
      width: 40px;
      height: 40px;
      color: var(--lex-color-primary);
    }

    .collection-card mat-card-content p {
      color: var(--lex-color-secondary);
      margin: 0;
    }

    .empty-state {
      text-align: center;
      padding: 60px 20px;
      grid-column: 1 / -1;
    }

    .empty-state mat-icon {
      font-size: 80px;
      width: 80px;
      height: 80px;
      color: var(--lex-color-muted);
    }

    .empty-state p {
      font-size: 1.2rem;
      color: var(--lex-color-secondary);
      margin: 16px 0 8px;
    }

    .empty-state .hint {
      font-size: 0.95rem;
      color: var(--lex-color-muted);
      margin-bottom: 24px;
    }

    @media (max-width: 600px) {
      .list-header {
        flex-direction: column;
        align-items: stretch;
      }

      .list-header button {
        width: 100%;
      }

      h1 {
        font-size: 1.4rem;
      }

      .collections-grid {
        grid-template-columns: 1fr;
      }
    }
  `]
})
export class CollectionListComponent implements OnInit {
  collectionService = inject(CollectionService);
  private dialog = inject(MatDialog);
  private snackBar = inject(MatSnackBar);

  ngOnInit(): void {
    this.collectionService.getCollections().subscribe();
  }

  openCreateDialog(): void {
    const dialogRef = this.dialog.open(CreateCollectionDialogComponent, {
      width: '400px',
      maxWidth: '90vw',
      hasBackdrop: true
    });

    dialogRef.afterClosed().subscribe(result => {
      if (result) {
        this.collectionService.createCollection(result).subscribe({
          next: () => {
            this.snackBar.open('Coleccion creada exitosamente', 'Cerrar', {
              duration: 3000
            });
          },
          error: (err) => {
            this.snackBar.open(err.error?.detail || 'Error al crear coleccion', 'Cerrar', {
              duration: 5000
            });
          }
        });
      }
    });
  }

  deleteCollection(collection: Collection): void {
    if (confirm(`¿Estas seguro de eliminar la coleccion "${collection.name}"?`)) {
      this.collectionService.deleteCollection(collection.id).subscribe({
        next: () => {
          this.snackBar.open('Coleccion eliminada', 'Cerrar', {
            duration: 3000
          });
        },
        error: (err) => {
          this.snackBar.open(err.error?.detail || 'Error al eliminar coleccion', 'Cerrar', {
            duration: 5000
          });
        }
      });
    }
  }
}
