import { Component, EventEmitter, Input, Output } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { DebounceInputDirective } from '@app/shared/directives/debounce-input.directive';

@Component({
  selector: 'app-search-bar',
  standalone: true,
  imports: [
    FormsModule,
    MatFormFieldModule,
    MatInputModule,
    MatIconModule,
    MatButtonModule,
    DebounceInputDirective
  ],
  template: `
    <mat-form-field appearance="outline" class="search-field">
      <mat-label>{{ placeholder }}</mat-label>
      <input
        matInput
        [ngModel]="value"
        (ngModelChange)="value = $event"
        appDebounceInput
        [debounceTime]="debounceMs"
        (debounceInput)="onSearch($event)"
        [placeholder]="placeholder"
      />
      <mat-icon matPrefix>search</mat-icon>
      @if (value) {
        <button
          matSuffix
          mat-icon-button
          aria-label="Clear"
          (click)="clearSearch()"
        >
          <mat-icon>close</mat-icon>
        </button>
      }
    </mat-form-field>
    @if (minLength > 0 && value && value.length < minLength) {
      <span class="hint">Ingrese al menos {{ minLength }} caracteres</span>
    }
  `,
  styles: [`
    :host {
      display: block;
      width: 100%;
    }

    .search-field {
      width: 100%;
    }

    .hint {
      font-size: 12px;
      color: var(--lex-color-secondary);
      margin-top: -16px;
      display: block;
    }
  `]
})
export class SearchBarComponent {
  @Input() placeholder = 'Buscar...';
  @Input() debounceMs = 300;
  @Input() minLength = 2;
  @Output() search = new EventEmitter<string>();

  value = '';

  onSearch(query: string): void {
    if (query.length === 0 || query.length >= this.minLength) {
      this.search.emit(query);
    }
  }

  clearSearch(): void {
    this.value = '';
    this.search.emit('');
  }
}
