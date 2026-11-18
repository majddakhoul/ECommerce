import type { ConnectionStatus, Unsubscribe } from "../../Domain/Models/Realtime";
import type { RealtimeGateway } from "../../Domain/Repositories/RealtimeGateway";

const nothing: Unsubscribe = () => undefined;

export class NullRealtimeGateway implements RealtimeGateway {
  readonly enabled = false;

  status = (): ConnectionStatus => "disabled";

  onStatusChange = (): Unsubscribe => nothing;

  connect = (): void => undefined;

  disconnect = (): void => undefined;

  onProducts = (): Unsubscribe => nothing;

  onStock = (): Unsubscribe => nothing;

  onCart = (): Unsubscribe => nothing;

  onMyOrders = (): Unsubscribe => nothing;

  onAllOrders = (): Unsubscribe => nothing;
}
