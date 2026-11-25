export const API_MODES = ["sync", "async"] as const;

export type ApiMode = (typeof API_MODES)[number];

export const isApiMode = (value: unknown): value is ApiMode => API_MODES.includes(value as ApiMode);

export const describeMode = (mode: ApiMode): string =>
  mode === "async" ? "Asynchronous: RabbitMQ queue and live updates" : "Synchronous: direct processing, no live updates";
