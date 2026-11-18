export const ORDER_STATUSES = ["pending", "confirmed", "processing", "delivered", "cancelled"] as const;

export type OrderStatus = (typeof ORDER_STATUSES)[number];

export interface OrderItem {
  id: number;
  productId: number;
  productName: string;
  quantity: number;
  price: number;
  subtotal: number;
}

export interface OrderOwner {
  id: number;
  name: string;
  email: string;
}

export interface Order {
  id: number;
  userId: number;
  user: OrderOwner | null;
  status: OrderStatus;
  totalPrice: number;
  items: OrderItem[];
  deliveredAt: string | null;
  createdAt: string;
  updatedAt: string;
}

export interface OrderFilter {
  status?: OrderStatus;
  userId?: number;
  page?: number;
  perPage?: number;
}

export interface OrderLine {
  productId: number;
  quantity: number;
}
