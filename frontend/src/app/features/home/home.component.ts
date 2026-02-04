import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatCardModule } from '@angular/material/card';

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [RouterLink, MatButtonModule, MatIconModule, MatCardModule],
  template: `
    <div class="home-container">
      <section class="hero">
        <mat-icon class="hero-icon">gavel</mat-icon>
        <h1>Constitucion de la Republica del Ecuador</h1>
        <p class="subtitle">
          Accede y explora todos los articulos de la Constitucion del Ecuador de manera
          rapida y sencilla
        </p>
        <div class="cta-buttons">
          <a mat-raised-button color="primary" routerLink="/articles">
            <mat-icon>article</mat-icon>
            Ver Articulos
          </a>
          <a mat-stroked-button color="primary" routerLink="/search">
            <mat-icon>search</mat-icon>
            Buscar
          </a>
        </div>
      </section>

      <section class="features">
        <mat-card>
          <mat-card-header>
            <mat-icon mat-card-avatar>article</mat-icon>
            <mat-card-title>Todos los Articulos</mat-card-title>
          </mat-card-header>
          <mat-card-content>
            <p>Navega por los 444 articulos de la Constitucion organizados por capitulos y secciones.</p>
          </mat-card-content>
        </mat-card>

        <mat-card>
          <mat-card-header>
            <mat-icon mat-card-avatar>search</mat-icon>
            <mat-card-title>Busqueda Inteligente</mat-card-title>
          </mat-card-header>
          <mat-card-content>
            <p>Encuentra articulos por palabras clave con nuestra busqueda de texto completo.</p>
          </mat-card-content>
        </mat-card>

        <mat-card>
          <mat-card-header>
            <mat-icon mat-card-avatar>link</mat-icon>
            <mat-card-title>Concordancias</mat-card-title>
          </mat-card-header>
          <mat-card-content>
            <p>Descubre las relaciones entre articulos a traves de las concordancias.</p>
          </mat-card-content>
        </mat-card>
      </section>
    </div>
  `,
  styles: [`
    .home-container {
      max-width: 1000px;
      margin: 0 auto;
    }

    .hero {
      text-align: center;
      padding: 60px 20px;
    }

    .hero-icon {
      font-size: 80px;
      width: 80px;
      height: 80px;
      color: #1976d2;
    }

    h1 {
      font-size: 2.5rem;
      margin: 24px 0 16px;
      color: #333;
    }

    .subtitle {
      font-size: 1.2rem;
      color: #666;
      max-width: 600px;
      margin: 0 auto 32px;
      line-height: 1.6;
    }

    .cta-buttons {
      display: flex;
      gap: 16px;
      justify-content: center;
      flex-wrap: wrap;
    }

    .cta-buttons a {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .features {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 24px;
      padding: 20px;
    }

    .features mat-card {
      text-align: center;
    }

    .features mat-card-header {
      justify-content: center;
    }

    .features mat-icon[mat-card-avatar] {
      font-size: 40px;
      width: 40px;
      height: 40px;
      color: #1976d2;
    }

    .features mat-card-content p {
      color: #666;
      line-height: 1.5;
    }

    @media (max-width: 600px) {
      h1 {
        font-size: 1.8rem;
      }

      .subtitle {
        font-size: 1rem;
      }

      .hero {
        padding: 40px 16px;
      }
    }
  `]
})
export class HomeComponent {}
