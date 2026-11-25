import type { Page } from "../Models/Pagination";
import type { Role, User, UserDraft, UserFilter } from "../Models/User";

export interface UserRepository {
  list(filter: UserFilter): Promise<Page<User>>;
  get(id: number): Promise<User>;
  create(draft: UserDraft): Promise<User>;
  update(id: number, changes: Partial<UserDraft>): Promise<User>;
  remove(id: number): Promise<void>;
  unlock(id: number): Promise<User>;
  assignRole(userId: number, roleId: number): Promise<User>;
  removeRole(userId: number, roleId: number): Promise<User>;
}

export interface RoleRepository {
  list(): Promise<Role[]>;
  create(name: string): Promise<Role>;
  update(id: number, name: string): Promise<Role>;
  remove(id: number): Promise<void>;
  users(id: number, page: number): Promise<Page<User>>;
}
