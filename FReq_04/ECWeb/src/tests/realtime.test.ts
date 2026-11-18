import { describe, expect, it } from "vitest";
import type { Cart } from "../Domain/Models/Cart";
import type { Page } from "../Domain/Models/Pagination";
import type { Product } from "../Domain/Models/Product";
import type { StockEvent } from "../Domain/Models/Realtime";
import {
  RecentKeys,
  applyStockToCart,
  applyStockToPage,
  findCartShortage,
  removeProductFromPage,
  upsertProductInPage,
} from "../Domain/UseCases/Realtime/cacheUpdates";

const product = (id: number, stock = 5): Product => ({
  id,
  name: `P${id}`,
  description: null,
  price: 10,
  cost: null,
  stock,
  inStock: stock > 0,
  category: null,
  image: null,
  createdAt: "",
  updatedAt: "",
});

const page = (items: Product[]): Page<Product> => ({ items, page: 1, perPage: 10, total: items.length, lastPage: 1 });

const event = (productId: number, stock: number): StockEvent => ({
  eventId: "e",
  productId,
  stock,
  deleted: false,
  reason: "set",
  version: 1,
  occurredAt: "",
});

const cart: Cart = {
  items: [{ productId: 1, name: "P1", price: 10, quantity: 4, subtotal: 40, availableStock: 9 }],
  totalQuantity: 4,
  totalPrice: 40,
};

describe("cache updates", () => {
  it("applies stock changes to the matching product only", () => {
    const result = applyStockToPage(page([product(1), product(2)]), event(2, 0));
    expect(result?.items[0].stock).toBe(5);
    expect(result?.items[1]).toMatchObject({ stock: 0, inStock: false });
  });

  it("updates available stock inside the cart", () => {
    expect(applyStockToCart(cart, event(1, 2))?.items[0].availableStock).toBe(2);
    expect(applyStockToCart(undefined, event(1, 2))).toBeUndefined();
  });

  it("reports a shortage only when stock drops below the wanted quantity", () => {
    expect(findCartShortage(cart, event(1, 3))).toEqual({ name: "P1", wanted: 4, available: 3 });
    expect(findCartShortage(cart, event(1, 4))).toBeNull();
    expect(findCartShortage(cart, event(2, 0))).toBeNull();
  });

  it("replaces and removes products in a page", () => {
    const replaced = upsertProductInPage(page([product(1)]), { ...product(1), name: "New" });
    expect(replaced?.items[0].name).toBe("New");
    const removed = removeProductFromPage(page([product(1), product(2)]), 1);
    expect(removed?.items.map((item) => item.id)).toEqual([2]);
    expect(removed?.total).toBe(1);
  });
});

describe("recent keys", () => {
  it("suppresses repeats inside the window and forgets them after it", () => {
    let now = 0;
    const keys = new RecentKeys(1000, () => now);
    expect(keys.firstSeen("a")).toBe(true);
    expect(keys.firstSeen("a")).toBe(false);
    now = 1500;
    expect(keys.firstSeen("a")).toBe(true);
  });
});
