import { describe, expect, it } from "vitest";
import { toCart, toPage, toProduct, toStockEvent } from "../Data/Mappers/mappers";
import type { ProductDto, StockEventDto } from "../Data/DTOs/ApiDtos";

describe("mappers", () => {
  it("normalizes numeric strings and missing fields on products", () => {
    const dto = { id: 1, name: "A", price: "12.50", stock: "-3" } as unknown as ProductDto;
    expect(toProduct(dto)).toMatchObject({ id: 1, price: 12.5, stock: 0, inStock: false, description: null, image: null });
  });

  it("maps a null cart to an empty one", () => {
    expect(toCart(null)).toEqual({ items: [], totalQuantity: 0, totalPrice: 0 });
  });

  it("maps pagination metadata with sensible defaults", () => {
    const page = toPage([1, 2], undefined, (value: number) => value * 2);
    expect(page.items).toEqual([2, 4]);
    expect(page.page).toBe(1);
  });

  it("maps stock events from the wire format", () => {
    const dto = { event_id: "e1", product_id: 7, stock: 3, deleted: false, reason: "reserved", version: 99, occurred_at: "t" } as unknown as StockEventDto;
    expect(toStockEvent(dto)).toMatchObject({ eventId: "e1", productId: 7, stock: 3, reason: "reserved", version: 99 });
  });
});
