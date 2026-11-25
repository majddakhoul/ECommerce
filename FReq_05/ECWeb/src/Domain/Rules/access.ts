import type { User } from "../Models/User";

export const ADMIN_ROLE = "admin";
export const USER_ROLE = "user";

export const isAdmin = (user: User | null | undefined): boolean => Boolean(user?.roles.includes(ADMIN_ROLE));

export const PROTECTED_ROLES: readonly string[] = [ADMIN_ROLE, USER_ROLE];

export const isProtectedRole = (name: string): boolean => PROTECTED_ROLES.includes(name);
