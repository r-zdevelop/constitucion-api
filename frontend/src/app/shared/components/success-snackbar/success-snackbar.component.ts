import { Component, Inject } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MAT_SNACK_BAR_DATA, MatSnackBarRef } from '@angular/material/snack-bar';
import { Router } from '@angular/router';

export interface SuccessSnackbarData {
  message: string;
  actionLabel?: string;
  actionRoute?: string;
  icon?: string;
}

@Component({
  selector: 'app-success-snackbar',
  standalone: true,
  imports: [MatButtonModule, MatIconModule],
  template: `
    <div class="snackbar-content">
      <div class="snackbar-icon">
        <mat-icon>{{ data.icon || 'check_circle' }}</mat-icon>
      </div>
      <span class="snackbar-message">{{ data.message }}</span>
      @if (data.actionLabel && data.actionRoute) {
        <button mat-button class="snackbar-action" (click)="onAction()">
          {{ data.actionLabel }}
          <mat-icon>arrow_forward</mat-icon>
        </button>
      }
      <button mat-icon-button class="snackbar-close" (click)="dismiss()">
        <mat-icon>close</mat-icon>
      </button>
    </div>
  `,
  styles: [`
    :host {
      display: block;
    }

    .snackbar-content {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 4px 0;
    }

    .snackbar-icon {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background-color: rgba(255, 255, 255, 0.15);
    }

    .snackbar-icon mat-icon {
      color: #4caf50;
      font-size: 20px;
      width: 20px;
      height: 20px;
    }

    .snackbar-message {
      flex: 1;
      font-size: 0.9375rem;
      color: white;
    }

    .snackbar-action {
      color: #81d4fa;
      font-weight: 500;
      text-transform: none;
      padding: 0 8px;
    }

    .snackbar-action mat-icon {
      font-size: 16px;
      width: 16px;
      height: 16px;
      margin-left: 4px;
    }

    .snackbar-action:hover {
      background-color: rgba(255, 255, 255, 0.1);
    }

    .snackbar-close {
      color: rgba(255, 255, 255, 0.7);
      width: 32px;
      height: 32px;
    }

    .snackbar-close mat-icon {
      font-size: 18px;
    }

    .snackbar-close:hover {
      color: white;
    }
  `]
})
export class SuccessSnackbarComponent {
  constructor(
    @Inject(MAT_SNACK_BAR_DATA) public data: SuccessSnackbarData,
    private snackBarRef: MatSnackBarRef<SuccessSnackbarComponent>,
    private router: Router
  ) {}

  onAction(): void {
    if (this.data.actionRoute) {
      this.router.navigate([this.data.actionRoute]);
      this.snackBarRef.dismiss();
    }
  }

  dismiss(): void {
    this.snackBarRef.dismiss();
  }
}
