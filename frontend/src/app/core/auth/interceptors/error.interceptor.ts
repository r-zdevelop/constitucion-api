import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { MatSnackBar } from '@angular/material/snack-bar';
import { ApiError } from '@app/models';
import { StorageService } from '@app/core/services/storage.service';

export const errorInterceptor: HttpInterceptorFn = (req, next) => {
  const router = inject(Router);
  const snackBar = inject(MatSnackBar);
  const storageService = inject(StorageService);

  return next(req).pipe(
    catchError((error: HttpErrorResponse) => {
      let errorMessage = 'An unexpected error occurred';

      if (error.error && typeof error.error === 'object') {
        const apiError = error.error as ApiError;

        if (apiError.detail) {
          errorMessage = apiError.detail;
        } else if (apiError.title) {
          errorMessage = apiError.title;
        }

        if (apiError.violations && apiError.violations.length > 0) {
          errorMessage = apiError.violations
            .map(v => `${v.propertyPath}: ${v.message}`)
            .join(', ');
        }
      }

      switch (error.status) {
        case 401:
          storageService.removeToken();
          router.navigate(['/auth/login']);
          errorMessage = 'Session expired. Please login again.';
          break;
        case 403:
          errorMessage = 'You do not have permission to perform this action.';
          break;
        case 404:
          errorMessage = 'Resource not found.';
          break;
        case 0:
          errorMessage = 'Unable to connect to the server.';
          break;
      }

      snackBar.open(errorMessage, 'Close', {
        duration: 5000,
        horizontalPosition: 'end',
        verticalPosition: 'top',
        panelClass: ['error-snackbar']
      });

      return throwError(() => error);
    })
  );
};
