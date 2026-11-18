import type { Credentials, Registration, Session, User } from "../Models/User";

export interface ProfileChanges {
  name?: string;
  email?: string;
}

export interface PasswordChange {
  currentPassword: string;
  password: string;
  passwordConfirmation: string;
}

export interface AuthRepository {
  login(credentials: Credentials): Promise<Session>;
  register(registration: Registration): Promise<Session>;
  me(): Promise<User>;
  updateProfile(changes: ProfileChanges): Promise<User>;
  changePassword(change: PasswordChange): Promise<Session>;
  refresh(): Promise<Session>;
  logout(): Promise<void>;
}
