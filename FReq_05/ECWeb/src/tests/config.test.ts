import { describe, expect, it } from "vitest";
import { MODE_STORAGE_KEY, apiBaseFor, createConfig } from "../Services/Config";
import { cleanParams } from "../Services/Http";

const storage = (value: string | null) => ({ getItem: () => value, setItem: () => undefined });

describe("config", () => {
  it("builds the versioned base path for each mode", () => {
    expect(apiBaseFor("http://x", "sync")).toBe("http://x/api/v4/sync");
    expect(apiBaseFor("http://x", "async")).toBe("http://x/api/v4/async");
  });

  it("defaults to async and trims trailing slashes", () => {
    const config = createConfig({ VITE_API_URL: "http://api.test///", VITE_REVERB_APP_KEY: "k" });
    expect(config.apiBase).toBe("http://api.test/api/v4/async");
    expect(config.realtimeEnabled).toBe(true);
  });

  it("lets the stored mode override the environment and disables realtime in sync", () => {
    expect(MODE_STORAGE_KEY).toBeTruthy();
    const config = createConfig({ VITE_API_MODE: "async", VITE_REVERB_APP_KEY: "k" }, storage("sync"));
    expect(config.apiMode).toBe("sync");
    expect(config.realtimeEnabled).toBe(false);
  });

  it("ignores invalid stored modes and disables realtime without a key", () => {
    const config = createConfig({ VITE_API_MODE: "sync" }, storage("bogus"));
    expect(config.apiMode).toBe("sync");
    expect(createConfig({}).realtimeEnabled).toBe(false);
  });

  it("drops empty params and converts booleans", () => {
    expect(cleanParams({ a: "", b: undefined, c: null, d: true, e: "x" })).toEqual({ d: 1, e: "x" });
  });
});
