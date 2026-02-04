import { Injectable, signal } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, tap, map } from 'rxjs';
import { Article, ArticleListResponse, ArticleByNumberResponse, PaginationMeta } from '@app/models';
import { environment } from '@env/environment';

@Injectable({
  providedIn: 'root'
})
export class ArticleService {
  private readonly apiUrl = `${environment.apiUrl}/articles`;

  private articlesSignal = signal<Article[]>([]);
  private paginationSignal = signal<PaginationMeta | null>(null);
  private isLoadingSignal = signal<boolean>(false);
  private currentArticleSignal = signal<Article | null>(null);

  readonly articles = this.articlesSignal.asReadonly();
  readonly pagination = this.paginationSignal.asReadonly();
  readonly isLoading = this.isLoadingSignal.asReadonly();
  readonly currentArticle = this.currentArticleSignal.asReadonly();

  constructor(private http: HttpClient) {}

  getArticles(page = 1, limit = 10, chapter?: string): Observable<ArticleListResponse> {
    this.isLoadingSignal.set(true);

    let params = new HttpParams()
      .set('page', page.toString())
      .set('limit', limit.toString());

    if (chapter) {
      params = params.set('chapter', chapter);
    }

    return this.http.get<ArticleListResponse>(this.apiUrl, { params }).pipe(
      tap(response => {
        this.articlesSignal.set(response.data);
        this.paginationSignal.set(response.meta);
        this.isLoadingSignal.set(false);
      })
    );
  }

  searchArticles(query: string, page = 1, limit = 10): Observable<ArticleListResponse> {
    this.isLoadingSignal.set(true);

    const params = new HttpParams()
      .set('q', query)
      .set('page', page.toString())
      .set('limit', limit.toString());

    return this.http.get<ArticleListResponse>(`${this.apiUrl}/search`, { params }).pipe(
      tap(response => {
        this.articlesSignal.set(response.data);
        this.paginationSignal.set(response.meta);
        this.isLoadingSignal.set(false);
      })
    );
  }

  getArticleByNumber(number: number): Observable<Article | null> {
    this.isLoadingSignal.set(true);

    return this.http.get<ArticleByNumberResponse>(`${this.apiUrl}/number/${number}`).pipe(
      map(response => response.articles[0] ?? null),
      tap(article => {
        this.currentArticleSignal.set(article);
        this.isLoadingSignal.set(false);
      })
    );
  }

  clearCurrentArticle(): void {
    this.currentArticleSignal.set(null);
  }
}
