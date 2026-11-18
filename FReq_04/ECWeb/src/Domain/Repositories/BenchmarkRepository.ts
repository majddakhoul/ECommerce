import type { ApiMode } from "../Models/ApiMode";

export interface BenchmarkSample {
  mode: ApiMode;
  ok: boolean;
  status: number;
  milliseconds: number;
}

export interface BenchmarkRepository {
  placeOrder(mode: ApiMode, productId: number): Promise<BenchmarkSample>;
}
