import { Component, Input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { MatCardModule } from '@angular/material/card';
import { MatChipsModule } from '@angular/material/chips';
import { MatButtonModule } from '@angular/material/button';
import { Article } from '@app/models';
import { TruncatePipe } from '@app/shared/pipes/truncate.pipe';
import { HighlightPipe } from '@app/shared/pipes/highlight.pipe';

@Component({
  selector: 'app-article-card',
  standalone: true,
  imports: [
    RouterLink,
    MatCardModule,
    MatChipsModule,
    MatButtonModule,
    TruncatePipe,
    HighlightPipe
  ],
  template: `
    <mat-card class="article-card" [attr.id]="'article-' + article.articleNumber">
      <mat-card-header>
        <mat-card-title>
          @if (searchTerm) {
            <span [innerHTML]="'Art. ' + article.articleNumber | highlight:searchTerm"></span>
          } @else {
            Art. {{ article.articleNumber }}
          }
          @if (article.title) {
            <span class="article-title">
              @if (searchTerm) {
                <span [innerHTML]="' - ' + article.title | highlight:searchTerm"></span>
              } @else {
                - {{ article.title }}
              }
            </span>
          }
        </mat-card-title>
        <mat-card-subtitle>
          <mat-chip-set>
            <mat-chip>{{ article.chapter }}</mat-chip>
          </mat-chip-set>
        </mat-card-subtitle>
      </mat-card-header>
      <mat-card-content>
        @if (searchTerm) {
          <p [innerHTML]="article.content | truncate:200 | highlight:searchTerm"></p>
        } @else {
          <p>{{ article.content | truncate:200 }}</p>
        }
      </mat-card-content>
      <mat-card-actions align="end">
        <a mat-button color="primary" [routerLink]="['/articles', article.articleNumber]">
          Ver completo
        </a>
      </mat-card-actions>
    </mat-card>
  `,
  styles: [`
    .article-card {
      margin-bottom: 16px;
    }

    .article-title {
      font-weight: normal;
      font-size: 0.9em;
      color: #666;
    }

    mat-card-subtitle {
      margin-top: 8px;
    }

    mat-chip-set {
      display: flex;
      flex-wrap: wrap;
      gap: 4px;
    }

    mat-card-content p {
      line-height: 1.6;
      color: #333;
    }

    :host ::ng-deep .highlight {
      background-color: #fff59d;
      padding: 0 2px;
      border-radius: 2px;
    }
  `]
})
export class ArticleCardComponent {
  @Input({ required: true }) article!: Article;
  @Input() searchTerm = '';
}
