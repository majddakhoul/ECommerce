export interface User {
  id: number;
  name: string;
  email: string;
  isActive: boolean;
  isLocked: boolean;
  roles: string[];
  lastLoginAt: string | null;
  createdAt: string;
}

export interface Role {
  id: number;
  name: string;
  usersCount: number;
}

export interface Session {
  user: User;
  accessToken: string;
  expiresIn: number;
}

export interface UserFilter {
  search?: string;
  role?: string;
  isActive?: boolean;
  page?: number;
  perPage?: number;
}

export interface UserDraft {
  name: string;
  email: string;
  password?: string;
  isActive?: boolean;
  role?: string;
}

export interface Credentials {
  email: string;
  password: string;
}

export interface Registration extends Credentials {
  name: string;
  passwordConfirmation: string;
}
