import { Article } from './article.model';

export interface Collection {
  id: string;
  name: string;
  description: string | null;
  articleCount: number;
  articleIds: string[];
  createdAt: string;
  updatedAt: string;
}

export interface CollectionListResponse {
  count: number;
  collections: Collection[];
}

export interface CollectionArticlesResponse {
  count: number;
  articles: Article[];
}

export interface CreateCollectionRequest {
  name: string;
  description?: string;
}

export interface UpdateCollectionRequest {
  name?: string;
  description?: string;
}

export interface AddArticleToCollectionRequest {
  articleId: string;
}
