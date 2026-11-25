import { emptyCart, type Cart, type CartItem } from "../../Domain/Models/Cart";
import { ORDER_STATUSES, type Order, type OrderItem, type OrderStatus } from "../../Domain/Models/Order";
import type { Page } from "../../Domain/Models/Pagination";
import type { Product } from "../../Domain/Models/Product";
import type {
  CartEvent,
  OrderEvent,
  OrderEventKind,
  ProductEvent,
  StockEvent,
} from "../../Domain/Models/Realtime";
import type { Role, Session, User } from "../../Domain/Models/User";
import { toNumber } from "../../Domain/Rules/money";
import type {
  CartDto,
  CartEventDto,
  CartItemDto,
  OrderDto,
  OrderEventDto,
  OrderItemDto,
  PageMetaDto,
  ProductDto,
  ProductEventDto,
  RoleDto,
  SessionDto,
  StockEventDto,
  UserDto,
} from "../DTOs/ApiDtos";

export const toProduct = (dto: ProductDto): Product => {
  const stock = Math.max(0, toNumber(dto.stock));
  return {
    id: dto.id,
    name: dto.name,
    description: dto.description ?? null,
    price: toNumber(dto.price),
    cost: dto.cost === null || dto.cost === undefined ? null : toNumber(dto.cost),
    stock,
    inStock: dto.in_stock ?? stock > 0,
    category: dto.category ?? null,
    image: dto.image ?? null,
    createdAt: dto.created_at,
    updatedAt: dto.updated_at,
  };
};

export const toUser = (dto: UserDto): User => ({
  id: dto.id,
  name: dto.name,
  email: dto.email,
  isActive: Boolean(dto.is_active),
  isLocked: Boolean(dto.is_locked),
  roles: [...(dto.roles ?? [])],
  lastLoginAt: dto.last_login_at ?? null,
  createdAt: dto.created_at,
});

export const toRole = (dto: RoleDto): Role => ({ id: dto.id, name: dto.name, usersCount: dto.users_count ?? 0 });

export const toSession = (dto: SessionDto): Session => ({
  user: toUser(dto.user),
  accessToken: dto.access_token,
  expiresIn: dto.expires_in,
});

const toCartItem = (dto: CartItemDto): CartItem => ({
  productId: dto.product_id,
  name: dto.name,
  price: toNumber(dto.price),
  quantity: dto.quantity,
  subtotal: toNumber(dto.subtotal),
  availableStock: dto.available_stock,
});

export const toCart = (dto: CartDto | null | undefined): Cart => {
  if (!dto) {
    return emptyCart();
  }
  return {
    items: (dto.items ?? []).map(toCartItem),
    totalQuantity: dto.total_quantity ?? 0,
    totalPrice: toNumber(dto.total_price),
  };
};

const toOrderItem = (dto: OrderItemDto): OrderItem => ({
  id: dto.id,
  productId: dto.product_id,
  productName: dto.product_name,
  quantity: dto.quantity,
  price: toNumber(dto.price),
  subtotal: toNumber(dto.subtotal),
});

const toStatus = (value: string): OrderStatus =>
  (ORDER_STATUSES as readonly string[]).includes(value) ? (value as OrderStatus) : "pending";

export const toOrder = (dto: OrderDto): Order => ({
  id: dto.id,
  userId: dto.user_id,
  user: dto.user ? { id: dto.user.id, name: dto.user.name, email: dto.user.email } : null,
  status: toStatus(dto.status),
  totalPrice: toNumber(dto.total_price),
  items: (dto.items ?? []).map(toOrderItem),
  deliveredAt: dto.delivered_at ?? null,
  createdAt: dto.created_at,
  updatedAt: dto.updated_at,
});

export const toPage = <D, T>(items: D[], meta: PageMetaDto | undefined, map: (dto: D) => T): Page<T> => ({
  items: items.map(map),
  page: meta?.current_page ?? 1,
  perPage: meta?.per_page ?? items.length,
  total: meta?.total ?? items.length,
  lastPage: meta?.last_page ?? 1,
});

export const toProductEvent = (dto: ProductEventDto): ProductEvent => ({
  action: dto.action,
  productId: dto.product_id,
  product: dto.product ? toProduct(dto.product) : null,
});

export const toStockEvent = (dto: StockEventDto): StockEvent => ({
  eventId: dto.event_id,
  productId: dto.product_id,
  stock: Math.max(0, dto.stock),
  deleted: Boolean(dto.deleted),
  reason: dto.reason,
  version: dto.version ?? 0,
  occurredAt: dto.occurred_at,
});

export const toCartEvent = (dto: CartEventDto): CartEvent => ({ cart: toCart(dto.cart) });

export const toOrderEvent = (kind: OrderEventKind, dto: OrderEventDto): OrderEvent => ({
  kind,
  orderId: dto.order_id,
  previousStatus: dto.previous_status ? toStatus(dto.previous_status) : null,
  order: toOrder(dto.order),
});
