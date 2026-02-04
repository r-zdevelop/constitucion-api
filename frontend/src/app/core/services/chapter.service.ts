import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, tap, of, map } from 'rxjs';
import { environment } from '@env/environment';

interface ChaptersResponse {
  count: number;
  chapters: string[];
}

@Injectable({
  providedIn: 'root'
})
export class ChapterService {
  private readonly apiUrl = `${environment.apiUrl}/articles/chapters`;

  private chaptersSignal = signal<string[]>([]);
  private isLoadingSignal = signal<boolean>(false);
  private cachedChapters: string[] | null = null;

  readonly chapters = this.chaptersSignal.asReadonly();
  readonly isLoading = this.isLoadingSignal.asReadonly();

  constructor(private http: HttpClient) {}

  getChapters(): Observable<string[]> {
    if (this.cachedChapters) {
      return of(this.cachedChapters);
    }

    this.isLoadingSignal.set(true);

    return this.http.get<ChaptersResponse>(this.apiUrl).pipe(
      map(response => response.chapters),
      tap(chapters => {
        this.cachedChapters = chapters;
        this.chaptersSignal.set(chapters);
        this.isLoadingSignal.set(false);
      })
    );
  }

  clearCache(): void {
    this.cachedChapters = null;
    this.chaptersSignal.set([]);
  }
}
