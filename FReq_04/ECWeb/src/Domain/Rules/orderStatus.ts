import { ORDER_STATUSES, type OrderStatus } from "../Models/Order";

const TRANSITIONS: Record<OrderStatus, OrderStatus[]> = {
  pending: ["confirmed", "processing", "cancelled"],
  confirmed: ["processing", "delivered", "cancelled"],
  processing: ["delivered", "cancelled"],
  delivered: [],
  cancelled: [],
};

export const nextStatuses = (status: OrderStatus): OrderStatus[] => TRANSITIONS[status];

export const canMoveTo = (from: OrderStatus, to: OrderStatus): boolean => TRANSITIONS[from].includes(to);

export const isCancellable = (status: OrderStatus): boolean => canMoveTo(status, "cancelled");

export const isOpen = (status: OrderStatus): boolean => status !== "delivered" && status !== "cancelled";

export const isOrderStatus = (value: string): value is OrderStatus => (ORDER_STATUSES as readonly string[]).includes(value);
