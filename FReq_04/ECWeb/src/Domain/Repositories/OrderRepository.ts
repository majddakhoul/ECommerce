import type { Order, OrderFilter, OrderLine, OrderStatus } from "../Models/Order";
import type { Page } from "../Models/Pagination";

export interface OrderRepository {
  listAll(filter: OrderFilter): Promise<Page<Order>>;
  listMine(filter: OrderFilter): Promise<Page<Order>>;
  get(id: number): Promise<Order>;
  place(lines: OrderLine[]): Promise<Order>;
  updateStatus(id: number, status: OrderStatus): Promise<Order>;
  cancel(id: number): Promise<Order>;
  remove(id: number): Promise<void>;
}
