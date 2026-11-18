import type { Cart } from "../Models/Cart";
import type { Order } from "../Models/Order";

export interface CartRepository {
  get(): Promise<Cart>;
  addItem(productId: number, quantity: number): Promise<Cart>;
  updateItem(productId: number, quantity: number): Promise<Cart>;
  removeItem(productId: number): Promise<Cart>;
  clear(): Promise<void>;
  checkout(): Promise<Order>;
}
