import { Injectable, computed, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { Observable, tap, catchError, throwError } from 'rxjs';
import { StorageService } from '@app/core/services/storage.service';
import { AuthResponse, LoginRequest, RegisterRequest, TokenPayload, User } from '@app/models';
import { environment } from '@env/environment';

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private readonly apiUrl = `${environment.apiUrl}/auth`;

  private currentUserSignal = signal<User | null>(null);
  private isLoadingSignal = signal<boolean>(false);

  readonly currentUser = this.currentUserSignal.asReadonly();
  readonly isLoading = this.isLoadingSignal.asReadonly();
  readonly isAuthenticated = computed(() => this.currentUserSignal() !== null);

  constructor(
    private http: HttpClient,
    private storageService: StorageService,
    private router: Router
  ) {
    this.loadUserFromToken();
  }

  login(credentials: LoginRequest): Observable<AuthResponse> {
    this.isLoadingSignal.set(true);
    return this.http.post<AuthResponse>(`${this.apiUrl}/login`, credentials).pipe(
      tap(response => {
        this.storageService.setToken(response.token);
        this.setUserFromResponse(response);
        this.isLoadingSignal.set(false);
      }),
      catchError(error => {
        this.isLoadingSignal.set(false);
        return throwError(() => error);
      })
    );
  }

  register(data: RegisterRequest): Observable<AuthResponse> {
    this.isLoadingSignal.set(true);
    return this.http.post<AuthResponse>(`${this.apiUrl}/register`, data).pipe(
      tap(response => {
        this.storageService.setToken(response.token);
        this.setUserFromResponse(response);
        this.isLoadingSignal.set(false);
      }),
      catchError(error => {
        this.isLoadingSignal.set(false);
        return throwError(() => error);
      })
    );
  }

  logout(): void {
    this.storageService.removeToken();
    this.currentUserSignal.set(null);
    this.router.navigate(['/']);
  }

  private setUserFromResponse(response: AuthResponse): void {
    const user: User = {
      id: response.user.id,
      email: response.user.email,
      role: response.user.role as User['role']
    };
    this.currentUserSignal.set(user);
  }

  private loadUserFromToken(): void {
    const token = this.storageService.getToken();
    if (!token) return;

    try {
      const payload = this.decodeToken(token);
      if (this.isTokenExpired(payload)) {
        this.storageService.removeToken();
        return;
      }

      const user: User = {
        id: payload.sub,
        email: payload.email,
        role: payload.role as User['role']
      };
      this.currentUserSignal.set(user);
    } catch {
      this.storageService.removeToken();
    }
  }

  private decodeToken(token: string): TokenPayload {
    const parts = token.split('.');
    if (parts.length !== 3) {
      throw new Error('Invalid token format');
    }
    const payload = JSON.parse(atob(parts[1]));
    return payload;
  }

  private isTokenExpired(payload: TokenPayload): boolean {
    const now = Math.floor(Date.now() / 1000);
    return payload.exp < now;
  }
}
