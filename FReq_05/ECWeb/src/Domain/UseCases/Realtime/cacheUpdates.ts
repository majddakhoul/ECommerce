import type { Cart } from "../../Models/Cart";
import type { Page } from "../../Models/Pagination";
import type { Product } from "../../Models/Product";
import type { StockEvent } from "../../Models/Realtime";

export const applyStockToProduct = (product: Product, event: StockEvent): Product =>
  product.id === event.productId ? { ...product, stock: event.stock, inStock: event.stock > 0 } : product;

export const applyStockToPage = (page: Page<Product> | undefined, event: StockEvent): Page<Product> | undefined =>
  page ? { ...page, items: page.items.map((product) => applyStockToProduct(product, event)) } : page;

export const applyStockToCart = (cart: Cart | undefined, event: StockEvent): Cart | undefined =>
  cart
    ? {
        ...cart,
        items: cart.items.map((item) => (item.productId === event.productId ? { ...item, availableStock: event.stock } : item)),
      }
    : cart;

export const upsertProductInPage = (page: Page<Product> | undefined, product: Product): Page<Product> | undefined =>
  page ? { ...page, items: page.items.map((existing) => (existing.id === product.id ? product : existing)) } : page;

export const removeProductFromPage = (page: Page<Product> | undefined, productId: number): Page<Product> | undefined =>
  page
    ? {
        ...page,
        items: page.items.filter((product) => product.id !== productId),
        total: page.items.some((product) => product.id === productId) ? Math.max(0, page.total - 1) : page.total,
      }
    : page;

export interface CartShortage {
  name: string;
  wanted: number;
  available: number;
}

export const findCartShortage = (cart: Cart | undefined, event: StockEvent): CartShortage | null => {
  const item = cart?.items.find((entry) => entry.productId === event.productId);
  if (!item || event.stock >= item.quantity) {
    return null;
  }
  return { name: item.name, wanted: item.quantity, available: event.stock };
};

export class RecentKeys {
  private readonly keys = new Map<string, number>();

  constructor(
    private readonly ttlMs = 10_000,
    private readonly clock: () => number = Date.now,
  ) {}

  firstSeen(key: string): boolean {
    const now = this.clock();
    this.keys.forEach((seenAt, existing) => {
      if (now - seenAt > this.ttlMs) {
        this.keys.delete(existing);
      }
    });
    if (this.keys.has(key)) {
      return false;
    }
    this.keys.set(key, now);
    return true;
  }
}
