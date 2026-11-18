import { isApiMode, type ApiMode } from "../Domain/Models/ApiMode";

export interface ReverbConfig {
  key: string;
  host: string;
  port: number;
  scheme: "http" | "https";
}

export interface AppConfig {
  apiOrigin: string;
  apiMode: ApiMode;
  defaultMode: ApiMode;
  apiBase: string;
  reverb: ReverbConfig;
  realtimeEnabled: boolean;
}

export interface EnvSource {
  VITE_API_URL?: string;
  VITE_API_MODE?: string;
  VITE_REVERB_APP_KEY?: string;
  VITE_REVERB_HOST?: string;
  VITE_REVERB_PORT?: string;
  VITE_REVERB_SCHEME?: string;
}

export interface ModeStorage {
  getItem(key: string): string | null;
  setItem(key: string, value: string): void;
}

export const MODE_STORAGE_KEY = "ecweb.api-mode";

export const apiBaseFor = (origin: string, mode: ApiMode): string => `${origin}/api/v4/${mode}`;

const safeGet = (storage: ModeStorage | undefined, key: string): string | null => {
  try {
    return storage?.getItem(key) ?? null;
  } catch {
    return null;
  }
};

export const createConfig = (env: EnvSource, storage?: ModeStorage): AppConfig => {
  const apiOrigin = (env.VITE_API_URL ?? "http://localhost:8000").replace(/\/+$/, "");
  const defaultMode: ApiMode = isApiMode(env.VITE_API_MODE) ? env.VITE_API_MODE : "async";
  const stored = safeGet(storage, MODE_STORAGE_KEY);
  const apiMode: ApiMode = isApiMode(stored) ? stored : defaultMode;

  const reverb: ReverbConfig = {
    key: env.VITE_REVERB_APP_KEY ?? "",
    host: env.VITE_REVERB_HOST ?? "localhost",
    port: Number.parseInt(env.VITE_REVERB_PORT ?? "8080", 10) || 8080,
    scheme: env.VITE_REVERB_SCHEME === "https" ? "https" : "http",
  };

  return {
    apiOrigin,
    apiMode,
    defaultMode,
    apiBase: apiBaseFor(apiOrigin, apiMode),
    reverb,
    realtimeEnabled: apiMode === "async" && reverb.key !== "",
  };
};

export const persistMode = (mode: ApiMode, storage: ModeStorage | undefined): void => {
  try {
    storage?.setItem(MODE_STORAGE_KEY, mode);
  } catch {
    return;
  }
};

const browserStorage = (): ModeStorage | undefined => {
  try {
    return typeof window === "undefined" ? undefined : window.localStorage;
  } catch {
    return undefined;
  }
};

export const Config: AppConfig = createConfig(import.meta.env, browserStorage());

export const switchMode = (mode: ApiMode): void => {
  persistMode(mode, browserStorage());
  window.location.reload();
};
