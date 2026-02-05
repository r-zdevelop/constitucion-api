import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, tap, catchError, throwError } from 'rxjs';
import {
  Collection,
  CollectionListResponse,
  CollectionArticlesResponse,
  CreateCollectionRequest,
  UpdateCollectionRequest,
  Article
} from '@app/models';
import { environment } from '@env/environment';

@Injectable({
  providedIn: 'root'
})
export class CollectionService {
  private readonly apiUrl = `${environment.apiUrl}/collections`;

  private collectionsSignal = signal<Collection[]>([]);
  private currentCollectionSignal = signal<Collection | null>(null);
  private collectionArticlesSignal = signal<Article[]>([]);
  private isLoadingSignal = signal<boolean>(false);
  private errorSignal = signal<string | null>(null);

  readonly collections = this.collectionsSignal.asReadonly();
  readonly currentCollection = this.currentCollectionSignal.asReadonly();
  readonly collectionArticles = this.collectionArticlesSignal.asReadonly();
  readonly isLoading = this.isLoadingSignal.asReadonly();
  readonly error = this.errorSignal.asReadonly();

  constructor(private http: HttpClient) {}

  getCollections(): Observable<CollectionListResponse> {
    this.isLoadingSignal.set(true);
    this.errorSignal.set(null);

    return this.http.get<CollectionListResponse>(this.apiUrl).pipe(
      tap(response => {
        this.collectionsSignal.set(response.collections);
        this.isLoadingSignal.set(false);
      }),
      catchError(error => {
        this.isLoadingSignal.set(false);
        this.errorSignal.set(error.error?.detail || 'Error loading collections');
        return throwError(() => error);
      })
    );
  }

  getCollection(id: string): Observable<Collection> {
    this.isLoadingSignal.set(true);
    this.errorSignal.set(null);

    return this.http.get<Collection>(`${this.apiUrl}/${id}`).pipe(
      tap(collection => {
        this.currentCollectionSignal.set(collection);
        this.isLoadingSignal.set(false);
      }),
      catchError(error => {
        this.isLoadingSignal.set(false);
        this.errorSignal.set(error.error?.detail || 'Error loading collection');
        return throwError(() => error);
      })
    );
  }

  getCollectionArticles(id: string): Observable<CollectionArticlesResponse> {
    this.isLoadingSignal.set(true);
    this.errorSignal.set(null);

    return this.http.get<CollectionArticlesResponse>(`${this.apiUrl}/${id}/articles`).pipe(
      tap(response => {
        this.collectionArticlesSignal.set(response.articles);
        this.isLoadingSignal.set(false);
      }),
      catchError(error => {
        this.isLoadingSignal.set(false);
        this.errorSignal.set(error.error?.detail || 'Error loading articles');
        return throwError(() => error);
      })
    );
  }

  createCollection(data: CreateCollectionRequest): Observable<Collection> {
    this.isLoadingSignal.set(true);
    this.errorSignal.set(null);

    return this.http.post<Collection>(this.apiUrl, data).pipe(
      tap(collection => {
        this.collectionsSignal.update(collections => [collection, ...collections]);
        this.isLoadingSignal.set(false);
      }),
      catchError(error => {
        this.isLoadingSignal.set(false);
        this.errorSignal.set(error.error?.detail || 'Error creating collection');
        return throwError(() => error);
      })
    );
  }

  updateCollection(id: string, data: UpdateCollectionRequest): Observable<Collection> {
    this.isLoadingSignal.set(true);
    this.errorSignal.set(null);

    return this.http.patch<Collection>(`${this.apiUrl}/${id}`, data).pipe(
      tap(updated => {
        this.collectionsSignal.update(collections =>
          collections.map(c => c.id === id ? updated : c)
        );
        if (this.currentCollectionSignal()?.id === id) {
          this.currentCollectionSignal.set(updated);
        }
        this.isLoadingSignal.set(false);
      }),
      catchError(error => {
        this.isLoadingSignal.set(false);
        this.errorSignal.set(error.error?.detail || 'Error updating collection');
        return throwError(() => error);
      })
    );
  }

  deleteCollection(id: string): Observable<void> {
    this.isLoadingSignal.set(true);
    this.errorSignal.set(null);

    return this.http.delete<void>(`${this.apiUrl}/${id}`).pipe(
      tap(() => {
        this.collectionsSignal.update(collections =>
          collections.filter(c => c.id !== id)
        );
        if (this.currentCollectionSignal()?.id === id) {
          this.currentCollectionSignal.set(null);
        }
        this.isLoadingSignal.set(false);
      }),
      catchError(error => {
        this.isLoadingSignal.set(false);
        this.errorSignal.set(error.error?.detail || 'Error deleting collection');
        return throwError(() => error);
      })
    );
  }

  addArticleToCollection(collectionId: string, articleId: string): Observable<Collection> {
    this.isLoadingSignal.set(true);
    this.errorSignal.set(null);

    return this.http.post<Collection>(`${this.apiUrl}/${collectionId}/articles`, { articleId }).pipe(
      tap(updated => {
        this.collectionsSignal.update(collections =>
          collections.map(c => c.id === collectionId ? updated : c)
        );
        if (this.currentCollectionSignal()?.id === collectionId) {
          this.currentCollectionSignal.set(updated);
        }
        this.isLoadingSignal.set(false);
      }),
      catchError(error => {
        this.isLoadingSignal.set(false);
        this.errorSignal.set(error.error?.detail || 'Error adding article');
        return throwError(() => error);
      })
    );
  }

  removeArticleFromCollection(collectionId: string, articleId: string): Observable<Collection> {
    this.isLoadingSignal.set(true);
    this.errorSignal.set(null);

    return this.http.delete<Collection>(`${this.apiUrl}/${collectionId}/articles/${articleId}`).pipe(
      tap(updated => {
        this.collectionsSignal.update(collections =>
          collections.map(c => c.id === collectionId ? updated : c)
        );
        if (this.currentCollectionSignal()?.id === collectionId) {
          this.currentCollectionSignal.set(updated);
        }
        this.collectionArticlesSignal.update(articles =>
          articles.filter(a => a.id !== articleId)
        );
        this.isLoadingSignal.set(false);
      }),
      catchError(error => {
        this.isLoadingSignal.set(false);
        this.errorSignal.set(error.error?.detail || 'Error removing article');
        return throwError(() => error);
      })
    );
  }

  clearCurrentCollection(): void {
    this.currentCollectionSignal.set(null);
    this.collectionArticlesSignal.set([]);
  }

  clearError(): void {
    this.errorSignal.set(null);
  }
}
