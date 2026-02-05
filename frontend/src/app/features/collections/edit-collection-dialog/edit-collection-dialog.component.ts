import { Component, inject } from '@angular/core';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { Collection } from '@app/models';

@Component({
  selector: 'app-edit-collection-dialog',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule
  ],
  template: `
    <h2 mat-dialog-title>Editar Coleccion</h2>
    <mat-dialog-content>
      <form [formGroup]="form" class="collection-form">
        <mat-form-field appearance="outline" class="full-width">
          <mat-label>Nombre</mat-label>
          <input matInput formControlName="name" placeholder="Ej: Derechos Fundamentales" maxlength="100">
          @if (form.get('name')?.hasError('required') && form.get('name')?.touched) {
            <mat-error>El nombre es requerido</mat-error>
          }
          @if (form.get('name')?.hasError('maxlength')) {
            <mat-error>Maximo 100 caracteres</mat-error>
          }
          <mat-hint>{{ form.get('name')?.value?.length || 0 }}/100</mat-hint>
        </mat-form-field>

        <mat-form-field appearance="outline" class="full-width">
          <mat-label>Descripcion (opcional)</mat-label>
          <textarea
            matInput
            formControlName="description"
            placeholder="Describe el proposito de esta coleccion..."
            rows="3"
            maxlength="500"
          ></textarea>
          @if (form.get('description')?.hasError('maxlength')) {
            <mat-error>Maximo 500 caracteres</mat-error>
          }
          <mat-hint>{{ form.get('description')?.value?.length || 0 }}/500</mat-hint>
        </mat-form-field>
      </form>
    </mat-dialog-content>
    <mat-dialog-actions align="end">
      <button mat-button mat-dialog-close>Cancelar</button>
      <button
        mat-raised-button
        color="primary"
        [disabled]="form.invalid || !hasChanges()"
        (click)="submit()"
      >
        Guardar
      </button>
    </mat-dialog-actions>
  `,
  styles: [`
    :host {
      display: block;
      background: white;
    }

    .collection-form {
      display: flex;
      flex-direction: column;
      gap: 16px;
      min-width: 300px;
      background: white;
    }

    .full-width {
      width: 100%;
    }

    mat-dialog-content {
      padding-top: 16px;
      background: white;
    }

    mat-dialog-actions {
      background: white;
    }
  `]
})
export class EditCollectionDialogComponent {
  private fb = inject(FormBuilder);
  private dialogRef = inject(MatDialogRef<EditCollectionDialogComponent>);
  private data: Collection = inject(MAT_DIALOG_DATA);

  form: FormGroup = this.fb.group({
    name: [this.data.name, [Validators.required, Validators.maxLength(100)]],
    description: [this.data.description || '', [Validators.maxLength(500)]]
  });

  hasChanges(): boolean {
    return this.form.value.name !== this.data.name ||
           (this.form.value.description || null) !== this.data.description;
  }

  submit(): void {
    if (this.form.valid && this.hasChanges()) {
      const value = this.form.value;
      const result: Record<string, string> = {};

      if (value.name !== this.data.name) {
        result['name'] = value.name;
      }
      if ((value.description || null) !== this.data.description) {
        result['description'] = value.description || '';
      }

      this.dialogRef.close(result);
    }
  }
}
