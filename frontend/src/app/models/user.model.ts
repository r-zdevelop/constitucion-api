export type Role = 'FREE' | 'PREMIUM' | 'ADMIN';

export interface User {
  id: string;
  email: string;
  role: Role;
}
