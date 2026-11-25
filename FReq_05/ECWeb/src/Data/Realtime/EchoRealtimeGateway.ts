import Echo from "laravel-echo";
import Pusher from "pusher-js";
import type {
  CartEvent,
  ConnectionStatus,
  OrderEvent,
  OrderEventKind,
  ProductEvent,
  StockEvent,
  Unsubscribe,
} from "../../Domain/Models/Realtime";
import type { RealtimeGateway } from "../../Domain/Repositories/RealtimeGateway";
import type { ReverbConfig } from "../../Services/Config";
import type { CartEventDto, OrderEventDto, ProductEventDto, StockEventDto } from "../DTOs/ApiDtos";
import { toCartEvent, toOrderEvent, toProductEvent, toStockEvent } from "../Mappers/mappers";

export type ChannelAuthorizer = (socketId: string, channelName: string) => Promise<unknown>;

interface ChannelLike {
  listen(event: string, callback: (payload: unknown) => void): ChannelLike;
  stopListening(event: string, callback?: (payload: unknown) => void): ChannelLike;
}

interface EchoLike {
  channel(name: string): ChannelLike;
  private(name: string): ChannelLike;
  leave(name: string): void;
  disconnect(): void;
  connector: { pusher: { connection: { bind(event: string, callback: (state: { current: string }) => void): void } } };
}

const STATE_MAP: Record<string, ConnectionStatus> = {
  initialized: "connecting",
  connecting: "connecting",
  connected: "connected",
  unavailable: "reconnecting",
  disconnected: "reconnecting",
  failed: "failed",
};

export class EchoRealtimeGateway implements RealtimeGateway {
  readonly enabled = true;

  private echo: EchoLike | null = null;
  private current: ConnectionStatus = "disabled";
  private readonly statusListeners = new Set<(status: ConnectionStatus) => void>();
  private readonly refCounts = new Map<string, number>();

  constructor(
    private readonly config: ReverbConfig,
    private readonly authorize: ChannelAuthorizer,
  ) {}

  status = (): ConnectionStatus => this.current;

  onStatusChange = (listener: (status: ConnectionStatus) => void): Unsubscribe => {
    this.statusListeners.add(listener);
    return () => {
      this.statusListeners.delete(listener);
    };
  };

  connect = (): void => {
    if (this.echo) {
      return;
    }

    (window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;
    this.setStatus("connecting");

    const echo = new Echo({
      broadcaster: "reverb",
      key: this.config.key,
      wsHost: this.config.host,
      wsPort: this.config.port,
      wssPort: this.config.port,
      forceTLS: this.config.scheme === "https",
      enabledTransports: ["ws", "wss"],
      disableStats: true,
      authorizer: (channel: { name: string }) => ({
        authorize: (socketId: string, callback: (error: Error | null, data: unknown) => void) => {
          this.authorize(socketId, channel.name)
            .then((data) => callback(null, data))
            .catch((error: unknown) => callback(error instanceof Error ? error : new Error("Channel authorization failed."), null));
        },
      }),
    } as unknown as ConstructorParameters<typeof Echo>[0]) as unknown as EchoLike;

    echo.connector.pusher.connection.bind("state_change", (state) => {
      this.setStatus(STATE_MAP[state.current] ?? "connecting");
    });

    this.echo = echo;
  };

  disconnect = (): void => {
    this.echo?.disconnect();
    this.echo = null;
    this.refCounts.clear();
    this.setStatus("disabled");
  };

  onProducts = (handler: (event: ProductEvent) => void): Unsubscribe =>
    this.listen("products", false, {
      ".product.created": (payload) => handler(toProductEvent(payload as ProductEventDto)),
      ".product.updated": (payload) => handler(toProductEvent(payload as ProductEventDto)),
      ".product.deleted": (payload) => handler(toProductEvent(payload as ProductEventDto)),
    });

  onStock = (handler: (event: StockEvent) => void): Unsubscribe =>
    this.listen("stock", false, {
      ".stock.changed": (payload) => handler(toStockEvent(payload as StockEventDto)),
    });

  onCart = (userId: number, handler: (event: CartEvent) => void): Unsubscribe =>
    this.listen(`cart.${userId}`, true, {
      ".cart.updated": (payload) => handler(toCartEvent(payload as CartEventDto)),
    });

  onMyOrders = (userId: number, handler: (event: OrderEvent) => void): Unsubscribe =>
    this.listen(`orders.user.${userId}`, true, this.orderHandlers(handler));

  onAllOrders = (handler: (event: OrderEvent) => void): Unsubscribe =>
    this.listen("orders.admin", true, this.orderHandlers(handler));

  private orderHandlers(handler: (event: OrderEvent) => void): Record<string, (payload: unknown) => void> {
    const entries: [string, OrderEventKind][] = [
      [".order.created", "created"],
      [".order.status_changed", "status_changed"],
      [".order.cancelled", "cancelled"],
      [".order.deleted", "deleted"],
    ];
    return Object.fromEntries(
      entries.map(([name, kind]) => [name, (payload: unknown) => handler(toOrderEvent(kind, payload as OrderEventDto))]),
    );
  }

  private listen(name: string, isPrivate: boolean, handlers: Record<string, (payload: unknown) => void>): Unsubscribe {
    this.connect();
    const echo = this.echo as EchoLike;
    const channel = isPrivate ? echo.private(name) : echo.channel(name);

    Object.entries(handlers).forEach(([event, handler]) => channel.listen(event, handler));
    this.refCounts.set(name, (this.refCounts.get(name) ?? 0) + 1);

    return () => {
      Object.entries(handlers).forEach(([event, handler]) => channel.stopListening(event, handler));
      const remaining = (this.refCounts.get(name) ?? 1) - 1;
      if (remaining <= 0) {
        this.refCounts.delete(name);
        this.echo?.leave(name);
      } else {
        this.refCounts.set(name, remaining);
      }
    };
  }

  private setStatus(status: ConnectionStatus): void {
    if (this.current === status) {
      return;
    }
    this.current = status;
    this.statusListeners.forEach((listener) => listener(status));
  }
}
