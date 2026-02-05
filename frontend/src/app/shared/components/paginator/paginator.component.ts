import { Component, Input, Output, EventEmitter, computed } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatSelectModule } from '@angular/material/select';
import { MatFormFieldModule } from '@angular/material/form-field';
import { FormsModule } from '@angular/forms';
import { PaginationMeta } from '@app/models';

export interface PageChangeEvent {
  page: number;
  pageSize: number;
}

@Component({
  selector: 'app-paginator',
  standalone: true,
  imports: [
    MatButtonModule,
    MatIconModule,
    MatSelectModule,
    MatFormFieldModule,
    FormsModule
  ],
  template: `
    <div class="paginator">
      <div class="paginator-info">
        <span class="range-label">
          {{ rangeStart() }} - {{ rangeEnd() }} de {{ pagination.total }}
        </span>
      </div>

      <div class="paginator-pages">
        <button
          mat-icon-button
          [disabled]="!pagination.hasPreviousPage"
          (click)="goToPage(1)"
          aria-label="Primera página"
          class="nav-button"
        >
          <mat-icon>first_page</mat-icon>
        </button>

        <button
          mat-icon-button
          [disabled]="!pagination.hasPreviousPage"
          (click)="goToPage(pagination.currentPage - 1)"
          aria-label="Página anterior"
          class="nav-button"
        >
          <mat-icon>chevron_left</mat-icon>
        </button>

        <div class="page-numbers">
          @for (page of visiblePages(); track page) {
            @if (page === '...') {
              <span class="ellipsis">...</span>
            } @else {
              <button
                mat-mini-fab
                [class.active]="page === pagination.currentPage"
                [color]="page === pagination.currentPage ? 'primary' : undefined"
                (click)="goToPage(+page)"
                [attr.aria-current]="page === pagination.currentPage ? 'page' : null"
              >
                {{ page }}
              </button>
            }
          }
        </div>

        <button
          mat-icon-button
          [disabled]="!pagination.hasNextPage"
          (click)="goToPage(pagination.currentPage + 1)"
          aria-label="Página siguiente"
          class="nav-button"
        >
          <mat-icon>chevron_right</mat-icon>
        </button>

        <button
          mat-icon-button
          [disabled]="!pagination.hasNextPage"
          (click)="goToPage(pagination.pages)"
          aria-label="Última página"
          class="nav-button"
        >
          <mat-icon>last_page</mat-icon>
        </button>
      </div>

      <div class="paginator-size">
        <mat-form-field appearance="outline" subscriptSizing="dynamic">
          <mat-select
            [value]="pagination.itemsPerPage"
            (selectionChange)="onPageSizeChange($event.value)"
          >
            @for (size of pageSizeOptions; track size) {
              <mat-option [value]="size">{{ size }} / pág</mat-option>
            }
          </mat-select>
        </mat-form-field>
      </div>
    </div>
  `,
  styles: [`
    .paginator {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      padding: 16px 0;
      flex-wrap: wrap;
    }

    .paginator-info {
      min-width: 120px;
    }

    .range-label {
      color: var(--lex-color-secondary, #666);
      font-size: 0.875rem;
    }

    .paginator-pages {
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .page-numbers {
      display: flex;
      align-items: center;
      gap: 4px;
      margin: 0 8px;
    }

    .page-numbers button {
      width: 36px;
      height: 36px;
      font-size: 0.875rem;
      box-shadow: none !important;
      background-color: transparent;
      color: var(--lex-color-dark, #333);
      transition: all 0.2s ease;
    }

    .page-numbers button:hover:not(.active) {
      background-color: rgba(0, 0, 0, 0.04);
    }

    .page-numbers button.active {
      background-color: var(--lex-color-primary, #0d6e6e);
      color: white;
    }

    .ellipsis {
      padding: 0 8px;
      color: var(--lex-color-secondary, #666);
    }

    .nav-button {
      color: var(--lex-color-secondary, #666);
    }

    .nav-button:disabled {
      opacity: 0.4;
    }

    .paginator-size mat-form-field {
      width: 100px;
    }

    ::ng-deep .paginator-size .mat-mdc-form-field-infix {
      padding-top: 8px !important;
      padding-bottom: 8px !important;
      min-height: 36px;
    }

    ::ng-deep .paginator-size .mat-mdc-select-value {
      font-size: 0.875rem;
    }

    @media (max-width: 600px) {
      .paginator {
        justify-content: center;
      }

      .paginator-info {
        order: 3;
        width: 100%;
        text-align: center;
      }

      .paginator-pages {
        order: 1;
      }

      .paginator-size {
        order: 2;
      }

      .page-numbers button {
        width: 32px;
        height: 32px;
        font-size: 0.75rem;
      }
    }
  `]
})
export class PaginatorComponent {
  @Input({ required: true }) pagination!: PaginationMeta;
  @Input() pageSizeOptions = [5, 10, 25, 50];

  @Output() pageChange = new EventEmitter<PageChangeEvent>();

  rangeStart = computed(() => {
    if (this.pagination.total === 0) return 0;
    return (this.pagination.currentPage - 1) * this.pagination.itemsPerPage + 1;
  });

  rangeEnd = computed(() => {
    return Math.min(
      this.pagination.currentPage * this.pagination.itemsPerPage,
      this.pagination.total
    );
  });

  visiblePages = computed(() => {
    const total = this.pagination.pages;
    const current = this.pagination.currentPage;
    const pages: (number | string)[] = [];

    if (total <= 7) {
      for (let i = 1; i <= total; i++) {
        pages.push(i);
      }
    } else {
      pages.push(1);

      if (current > 3) {
        pages.push('...');
      }

      const start = Math.max(2, current - 1);
      const end = Math.min(total - 1, current + 1);

      for (let i = start; i <= end; i++) {
        pages.push(i);
      }

      if (current < total - 2) {
        pages.push('...');
      }

      pages.push(total);
    }

    return pages;
  });

  goToPage(page: number): void {
    if (page < 1 || page > this.pagination.pages || page === this.pagination.currentPage) {
      return;
    }
    this.pageChange.emit({
      page,
      pageSize: this.pagination.itemsPerPage
    });
  }

  onPageSizeChange(size: number): void {
    this.pageChange.emit({
      page: 1,
      pageSize: size
    });
  }
}
