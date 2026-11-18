export interface Envelope<T> {
  status: boolean;
  code: number;
  message: string | null;
  data: T;
}

export interface PageMetaDto {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface PagedEnvelope<T> extends Envelope<T[]> {
  meta: PageMetaDto;
}

export interface ProductDto {
  id: number;
  name: string;
  description: string | null;
  price: string | number;
  cost: string | number | null;
  stock: number | null;
  in_stock?: boolean;
  category: string | null;
  image: string | null;
  created_at: string;
  updated_at: string;
}

export interface UserDto {
  id: number;
  name: string;
  email: string;
  is_active: boolean;
  is_locked: boolean;
  roles: string[];
  last_login_at: string | null;
  created_at: string;
}

export interface RoleDto {
  id: number;
  name: string;
  users_count?: number;
}

export interface SessionDto {
  user: UserDto;
  access_token: string;
  token_type: string;
  expires_in: number;
}

export interface CartItemDto {
  product_id: number;
  name: string;
  price: string | number;
  quantity: number;
  subtotal: string | number;
  available_stock: number;
}

export interface CartDto {
  items: CartItemDto[];
  total_quantity: number;
  total_price: string | number;
}

export interface OrderItemDto {
  id: number;
  product_id: number;
  product_name: string;
  quantity: number;
  price: string | number;
  subtotal: string | number;
}

export interface OrderDto {
  id: number;
  user_id: number;
  user?: { id: number; name: string; email: string };
  status: string;
  total_price: string | number;
  items?: OrderItemDto[];
  delivered_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface ProductEventDto {
  action: "created" | "updated" | "deleted";
  product_id: number;
  product: ProductDto | null;
}

export interface StockEventDto {
  event_id: string;
  product_id: number;
  stock: number;
  deleted: boolean;
  reason: string;
  version: number;
  occurred_at: string;
}

export interface CartEventDto {
  cart: CartDto;
}

export interface OrderEventDto {
  order_id: number;
  previous_status: string | null;
  order: OrderDto;
}
