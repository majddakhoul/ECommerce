import { describe, expect, it } from "vitest";
import type { Order } from "../Domain/Models/Order";
import { buildDashboard } from "../Domain/UseCases/Dashboard/dashboard";
import { improvement, summarize } from "../Domain/UseCases/Lab/benchmark";

const now = new Date(2026, 5, 10, 12);

const order = (id: number, status: Order["status"], total: number, daysAgo: number, productId = 1, quantity = 1): Order => ({
  id,
  userId: 1,
  user: null,
  status,
  totalPrice: total,
  items: [{ id, productId, productName: `P${productId}`, quantity, price: total / quantity, subtotal: total }],
  deliveredAt: null,
  createdAt: new Date(2026, 5, 10 - daysAgo, 9).toISOString(),
  updatedAt: "",
});

describe("dashboard", () => {
  const stats = buildDashboard({
    orders: [order(1, "pending", 100, 0), order(2, "delivered", 50, 1, 2, 3), order(3, "cancelled", 999, 1), order(4, "confirmed", 30, 20, 3)],
    totalOrders: 4,
    products: [],
    totalProducts: 0,
    totalUsers: 3,
    now,
  });

  it("excludes cancelled orders from revenue", () => {
    expect(stats.revenue).toBe(180);
    expect(stats.averageOrderValue).toBe(60);
  });

  it("counts orders by status and open orders", () => {
    expect(stats.byStatus).toMatchObject({ pending: 1, delivered: 1, cancelled: 1, confirmed: 1 });
    expect(stats.openOrders).toBe(2);
  });

  it("buckets the last seven days and ignores older orders", () => {
    expect(stats.last7Days).toHaveLength(7);
    expect(stats.last7Days[6].orders).toBe(1);
    expect(stats.last7Days[5].orders).toBe(2);
    expect(stats.last7Days.reduce((sum, day) => sum + day.orders, 0)).toBe(3);
  });

  it("ranks products by quantity sold", () => {
    expect(stats.topProducts[0]).toMatchObject({ productId: 2, quantity: 3 });
  });
});

describe("benchmark", () => {
  const sample = (ms: number, ok = true) => ({ mode: "sync" as const, ok, status: ok ? 201 : 500, milliseconds: ms });

  it("summarizes successful samples and counts errors", () => {
    const stats = summarize([sample(100), sample(200), sample(300), sample(999, false)]);
    expect(stats).toMatchObject({ count: 4, errors: 1, averageMs: 200, medianMs: 200, minMs: 100, maxMs: 300 });
  });

  it("returns zeros for empty input", () => {
    expect(summarize([])).toMatchObject({ count: 0, averageMs: 0, p95Ms: 0 });
  });

  it("computes the improvement of async over sync", () => {
    expect(improvement(summarize([sample(200)]), summarize([sample(50)]))).toBe(75);
    expect(improvement(summarize([]), summarize([sample(50)]))).toBe(0);
  });
});
