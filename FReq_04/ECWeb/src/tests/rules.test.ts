import { describe, expect, it } from "vitest";
import { canMoveTo, isCancellable, isOpen, nextStatuses } from "../Domain/Rules/orderStatus";
import { LOW_STOCK_THRESHOLD, shouldApplyStockEvent, stockLevel } from "../Domain/Rules/stock";
import { formatMoney, toNumber } from "../Domain/Rules/money";
import { isAdmin, isProtectedRole } from "../Domain/Rules/access";
import type { User } from "../Domain/Models/User";

describe("order status rules", () => {
  it("allows only forward transitions", () => {
    expect(nextStatuses("pending")).toEqual(["confirmed", "processing", "cancelled"]);
    expect(canMoveTo("processing", "pending")).toBe(false);
    expect(nextStatuses("delivered")).toEqual([]);
  });

  it("knows which orders can be cancelled or are open", () => {
    expect(isCancellable("confirmed")).toBe(true);
    expect(isCancellable("delivered")).toBe(false);
    expect(isOpen("processing")).toBe(true);
    expect(isOpen("cancelled")).toBe(false);
  });
});

describe("stock rules", () => {
  it("classifies stock levels", () => {
    expect(stockLevel(0)).toBe("out");
    expect(stockLevel(LOW_STOCK_THRESHOLD)).toBe("low");
    expect(stockLevel(LOW_STOCK_THRESHOLD + 1)).toBe("ok");
  });

  it("applies only events that are not older than the last one", () => {
    expect(shouldApplyStockEvent(undefined, 5)).toBe(true);
    expect(shouldApplyStockEvent(10, 9)).toBe(false);
    expect(shouldApplyStockEvent(10, 10)).toBe(true);
    expect(shouldApplyStockEvent(10, 0)).toBe(true);
  });
});

describe("money and access", () => {
  it("formats and parses numbers defensively", () => {
    expect(formatMoney(1234.5)).toBe("$1,234.50");
    expect(formatMoney(Number.NaN)).toBe("$0.00");
    expect(toNumber("12.5")).toBe(12.5);
    expect(toNumber("abc", 3)).toBe(3);
  });

  it("detects admins and protected roles", () => {
    const user = { roles: ["admin"] } as User;
    expect(isAdmin(user)).toBe(true);
    expect(isAdmin(null)).toBe(false);
    expect(isProtectedRole("user")).toBe(true);
    expect(isProtectedRole("editor")).toBe(false);
  });
});
