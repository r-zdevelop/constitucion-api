export interface Concordance {
  referencedLaw: string;
  referencedArticles: string[];
  sourceArticleNumber: number;
  createdAt: string;
}

export interface Article {
  id: string;
  documentId: string;
  articleNumber: number;
  title: string | null;
  content: string;
  chapter: string;
  status: string;
  concordances: Concordance[];
  createdAt: string;
  updatedAt: string;
}

export interface ArticleListResponse {
  data: Article[];
  meta: PaginationMeta;
}

export interface ArticleByNumberResponse {
  count: number;
  articles: Article[];
}

export interface PaginationMeta {
  total: number;
  pages: number;
  currentPage: number;
  itemsPerPage: number;
  hasNextPage: boolean;
  hasPreviousPage: boolean;
}
