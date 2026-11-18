import type {
  CartEvent,
  ConnectionStatus,
  OrderEvent,
  ProductEvent,
  StockEvent,
  Unsubscribe,
} from "../Models/Realtime";

export interface RealtimeGateway {
  readonly enabled: boolean;
  status(): ConnectionStatus;
  onStatusChange(listener: (status: ConnectionStatus) => void): Unsubscribe;
  connect(): void;
  disconnect(): void;
  onProducts(handler: (event: ProductEvent) => void): Unsubscribe;
  onStock(handler: (event: StockEvent) => void): Unsubscribe;
  onCart(userId: number, handler: (event: CartEvent) => void): Unsubscribe;
  onMyOrders(userId: number, handler: (event: OrderEvent) => void): Unsubscribe;
  onAllOrders(handler: (event: OrderEvent) => void): Unsubscribe;
}
