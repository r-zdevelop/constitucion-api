import { Component } from '@angular/core';

@Component({
  selector: 'app-footer',
  standalone: true,
  template: `
    <footer>
      <div class="footer-content">
        <p>&copy; {{ currentYear }} LexEcuador - Constitucion de la Republica del Ecuador</p>
        <p class="disclaimer">
          Este sitio es solo para fines informativos y no constituye asesoramiento legal.
        </p>
      </div>
    </footer>
  `,
  styles: [`
    footer {
      background-color: #333;
      color: #fff;
      padding: 24px;
      margin-top: auto;
    }

    .footer-content {
      max-width: 1200px;
      margin: 0 auto;
      text-align: center;
    }

    .disclaimer {
      font-size: 0.85rem;
      color: #999;
      margin-top: 8px;
    }
  `]
})
export class FooterComponent {
  currentYear = new Date().getFullYear();
}
