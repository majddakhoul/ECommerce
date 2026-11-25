import type { ApiMode } from "../../Domain/Models/ApiMode";
import { normalizeError, type HttpClient } from "../../Services/Http";
import { apiBaseFor } from "../../Services/Config";
import type { BenchmarkRepository, BenchmarkSample } from "../../Domain/Repositories/BenchmarkRepository";

export class BenchmarkRepositoryImpl implements BenchmarkRepository {
  constructor(
    private readonly http: HttpClient,
    private readonly origin: string,
    private readonly clock: () => number = () => performance.now(),
  ) {}

  async placeOrder(mode: ApiMode, productId: number): Promise<BenchmarkSample> {
    const startedAt = this.clock();
    try {
      const response = await this.http.post(
        "/orders",
        { items: [{ product_id: productId, quantity: 1 }] },
        { baseURL: apiBaseFor(this.origin, mode) },
      );
      return { mode, ok: true, status: response.status, milliseconds: this.clock() - startedAt };
    } catch (error) {
      return { mode, ok: false, status: normalizeError(error).status, milliseconds: this.clock() - startedAt };
    }
  }
}
