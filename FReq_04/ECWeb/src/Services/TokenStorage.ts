export interface TokenStore {
  get(): string | null;
  set(token: string): void;
  clear(): void;
}

const KEY = "ecweb.token";

export class BrowserTokenStore implements TokenStore {
  private memory: string | null = null;

  get(): string | null {
    try {
      return window.localStorage.getItem(KEY) ?? this.memory;
    } catch {
      return this.memory;
    }
  }

  set(token: string): void {
    this.memory = token;
    try {
      window.localStorage.setItem(KEY, token);
    } catch {
      return;
    }
  }

  clear(): void {
    this.memory = null;
    try {
      window.localStorage.removeItem(KEY);
    } catch {
      return;
    }
  }
}

export class MemoryTokenStore implements TokenStore {
  constructor(private token: string | null = null) {}

  get(): string | null {
    return this.token;
  }

  set(token: string): void {
    this.token = token;
  }

  clear(): void {
    this.token = null;
  }
}
