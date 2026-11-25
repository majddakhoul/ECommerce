import type { Cart } from "./Cart";
import type { Order, OrderStatus } from "./Order";
import type { Product } from "./Product";

export type ConnectionStatus = "disabled" | "connecting" | "connected" | "reconnecting" | "failed";

export type ProductAction = "created" | "updated" | "deleted";

export interface ProductEvent {
  action: ProductAction;
  productId: number;
  product: Product | null;
}

export interface StockEvent {
  eventId: string;
  productId: number;
  stock: number;
  deleted: boolean;
  reason: string;
  version: number;
  occurredAt: string;
}

export interface CartEvent {
  cart: Cart;
}

export type OrderEventKind = "created" | "status_changed" | "cancelled" | "deleted";

export interface OrderEvent {
  kind: OrderEventKind;
  orderId: number;
  previousStatus: OrderStatus | null;
  order: Order;
}

export type Unsubscribe = () => void;
