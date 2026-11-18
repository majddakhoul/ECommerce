import axios, { AxiosError, type AxiosInstance, type AxiosRequestConfig } from "axios";
import { ApiError, type FieldErrors } from "../Domain/Models/ApiError";
import type { TokenStore } from "./TokenStorage";

export interface HttpRequestConfig {
  params?: Record<string, unknown>;
  baseURL?: string;
  headers?: Record<string, string>;
  skipAuth?: boolean;
}

export interface HttpResponse<T> {
  data: T;
  status: number;
  headers: Record<string, string>;
}

export interface HttpClient {
  get<T>(url: string, config?: HttpRequestConfig): Promise<HttpResponse<T>>;
  post<T>(url: string, body?: unknown, config?: HttpRequestConfig): Promise<HttpResponse<T>>;
  put<T>(url: string, body?: unknown, config?: HttpRequestConfig): Promise<HttpResponse<T>>;
  delete<T>(url: string, config?: HttpRequestConfig): Promise<HttpResponse<T>>;
}

interface ErrorBody {
  message?: string | null;
  errors?: FieldErrors;
}

export const cleanParams = (params: Record<string, unknown> | undefined): Record<string, unknown> | undefined => {
  if (!params) {
    return undefined;
  }
  const entries = Object.entries(params).filter(([, value]) => value !== undefined && value !== null && value !== "");
  return Object.fromEntries(entries.map(([key, value]) => [key, typeof value === "boolean" ? Number(value) : value]));
};

export const normalizeError = (error: unknown): ApiError => {
  if (error instanceof ApiError) {
    return error;
  }
  if (axios.isAxiosError(error)) {
    const failure = error as AxiosError<ErrorBody>;
    if (!failure.response) {
      return new ApiError("The server is not reachable. Check your connection and try again.", 0);
    }
    const body = failure.response.data;
    return new ApiError(body?.message || failure.message, failure.response.status, body?.errors ?? {});
  }
  return new ApiError(error instanceof Error ? error.message : "Something went wrong.", 0);
};

export interface HttpOptions {
  baseURL: string;
  tokens: TokenStore;
  onUnauthorized?: () => void;
  timeout?: number;
}

export const createHttp = (options: HttpOptions): HttpClient => {
  const instance: AxiosInstance = axios.create({
    baseURL: options.baseURL,
    timeout: options.timeout ?? 20000,
    headers: { Accept: "application/json" },
  });

  instance.interceptors.request.use((config) => {
    const token = options.tokens.get();
    if (token && !(config.headers as Record<string, unknown>)["X-Skip-Auth"]) {
      config.headers.set("Authorization", `Bearer ${token}`);
    }
    config.headers.delete("X-Skip-Auth");
    return config;
  });

  instance.interceptors.response.use(
    (response) => response,
    (error) => {
      const normalized = normalizeError(error);
      if (normalized.isUnauthorized && options.tokens.get()) {
        options.onUnauthorized?.();
      }
      return Promise.reject(normalized);
    },
  );

  const send = async <T>(config: AxiosRequestConfig, request: HttpRequestConfig = {}): Promise<HttpResponse<T>> => {
    const headers: Record<string, string> = { ...(request.headers ?? {}) };
    if (request.skipAuth) {
      headers["X-Skip-Auth"] = "1";
    }
    const response = await instance.request<T>({
      ...config,
      baseURL: request.baseURL,
      params: cleanParams(request.params),
      headers,
    });
    const flat: Record<string, string> = {};
    Object.entries(response.headers).forEach(([key, value]) => {
      flat[key.toLowerCase()] = String(value);
    });
    return { data: response.data, status: response.status, headers: flat };
  };

  return {
    get: (url, config) => send({ method: "GET", url }, config),
    post: (url, body, config) => send({ method: "POST", url, data: body }, config),
    put: (url, body, config) => send({ method: "PUT", url, data: body }, config),
    delete: (url, config) => send({ method: "DELETE", url }, config),
  };
};
