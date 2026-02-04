import { Component, inject, signal } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { MatSidenavModule } from '@angular/material/sidenav';
import { MatListModule } from '@angular/material/list';
import { MatDividerModule } from '@angular/material/divider';
import { AuthService } from '@app/core/auth/services/auth.service';

@Component({
  selector: 'app-header',
  standalone: true,
  imports: [
    RouterLink,
    RouterLinkActive,
    MatToolbarModule,
    MatButtonModule,
    MatIconModule,
    MatMenuModule,
    MatSidenavModule,
    MatListModule,
    MatDividerModule
  ],
  template: `
    <mat-toolbar color="primary">
      <!-- Mobile menu button -->
      <button mat-icon-button class="mobile-menu-btn" [matMenuTriggerFor]="mobileMenu">
        <mat-icon>menu</mat-icon>
      </button>

      <a routerLink="/" class="logo">
        <mat-icon>gavel</mat-icon>
        <span class="brand">LexEcuador</span>
      </a>

      <!-- Desktop nav -->
      <nav class="nav-links desktop-only">
        <a mat-button routerLink="/articles" routerLinkActive="active">
          <mat-icon>article</mat-icon>
          <span>Articulos</span>
        </a>
        <a mat-button routerLink="/search" routerLinkActive="active">
          <mat-icon>search</mat-icon>
          <span>Buscar</span>
        </a>
      </nav>

      <span class="spacer"></span>

      <!-- Desktop auth buttons -->
      <div class="auth-buttons desktop-only">
        @if (authService.isAuthenticated()) {
          <button mat-button [matMenuTriggerFor]="userMenu">
            <mat-icon>account_circle</mat-icon>
            <span class="user-email">{{ authService.currentUser()?.email }}</span>
            <mat-icon>arrow_drop_down</mat-icon>
          </button>
          <mat-menu #userMenu="matMenu">
            <button mat-menu-item (click)="logout()">
              <mat-icon>logout</mat-icon>
              <span>Cerrar sesion</span>
            </button>
          </mat-menu>
        } @else {
          <a mat-button routerLink="/auth/login">
            <mat-icon>login</mat-icon>
            <span>Iniciar sesion</span>
          </a>
          <a mat-flat-button routerLink="/auth/register">
            Registrarse
          </a>
        }
      </div>

      <!-- Mobile auth icon -->
      <div class="mobile-only">
        @if (authService.isAuthenticated()) {
          <button mat-icon-button [matMenuTriggerFor]="userMenuMobile">
            <mat-icon>account_circle</mat-icon>
          </button>
          <mat-menu #userMenuMobile="matMenu">
            <div class="menu-email">{{ authService.currentUser()?.email }}</div>
            <button mat-menu-item (click)="logout()">
              <mat-icon>logout</mat-icon>
              <span>Cerrar sesion</span>
            </button>
          </mat-menu>
        } @else {
          <a mat-icon-button routerLink="/auth/login">
            <mat-icon>login</mat-icon>
          </a>
        }
      </div>
    </mat-toolbar>

    <!-- Mobile menu -->
    <mat-menu #mobileMenu="matMenu">
      <a mat-menu-item routerLink="/">
        <mat-icon>home</mat-icon>
        <span>Inicio</span>
      </a>
      <a mat-menu-item routerLink="/articles">
        <mat-icon>article</mat-icon>
        <span>Articulos</span>
      </a>
      <a mat-menu-item routerLink="/search">
        <mat-icon>search</mat-icon>
        <span>Buscar</span>
      </a>
      @if (!authService.isAuthenticated()) {
        <mat-divider></mat-divider>
        <a mat-menu-item routerLink="/auth/login">
          <mat-icon>login</mat-icon>
          <span>Iniciar sesion</span>
        </a>
        <a mat-menu-item routerLink="/auth/register">
          <mat-icon>person_add</mat-icon>
          <span>Registrarse</span>
        </a>
      }
    </mat-menu>
  `,
  styles: [`
    mat-toolbar {
      position: sticky;
      top: 0;
      z-index: 1000;
      gap: 8px;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      color: inherit;
    }

    .brand {
      font-weight: 600;
      font-size: 1.2rem;
    }

    .nav-links {
      margin-left: 24px;
      display: flex;
      gap: 4px;
    }

    .nav-links a {
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .nav-links a.active {
      background-color: rgba(255, 255, 255, 0.1);
    }

    .spacer {
      flex: 1;
    }

    .auth-buttons {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .user-email {
      max-width: 150px;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .menu-email {
      padding: 8px 16px;
      font-size: 0.85rem;
      color: #666;
      border-bottom: 1px solid #eee;
      margin-bottom: 8px;
    }

    .mobile-menu-btn {
      display: none;
    }

    .mobile-only {
      display: none;
    }

    @media (max-width: 768px) {
      .mobile-menu-btn {
        display: flex;
      }

      .desktop-only {
        display: none !important;
      }

      .mobile-only {
        display: flex;
      }

      .brand {
        display: none;
      }
    }
  `]
})
export class HeaderComponent {
  authService = inject(AuthService);

  logout(): void {
    this.authService.logout();
  }
}
