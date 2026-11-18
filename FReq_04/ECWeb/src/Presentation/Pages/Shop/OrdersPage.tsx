import { useState } from "react";
import { ORDER_STATUSES, type Order, type OrderStatus } from "../../../Domain/Models/Order";
import { formatMoney } from "../../../Domain/Rules/money";
import { isCancellable } from "../../../Domain/Rules/orderStatus";
import { useCancelOrder, useMyOrders } from "../../../Domain/UseCases/Orders/useOrders";
import { StatusBadge } from "../../Components/Badges";
import { Button } from "../../Components/Button";
import { EmptyState, ErrorState, LoadingBlock } from "../../Components/Feedback";
import { ConfirmDialog } from "../../Components/Modal";
import { Pagination } from "../../Components/Pagination";
import { useDocumentTitle } from "../../Hooks/useDocumentTitle";
import { capitalize, formatDateTime } from "../../format";

function OrderCard({ order, onCancel }: { order: Order; onCancel: (order: Order) => void }) {
  return (
    <article className="card order-card">
      <div className="row between wrap">
        <div className="row">
          <h3>Order #{order.id}</h3>
          <StatusBadge status={order.status} />
        </div>
        <span className="muted">{formatDateTime(order.createdAt)}</span>
      </div>
      <div className="order-items">
        {order.items.map((item) => (
          <div key={item.id} className="row between">
            <span>
              {item.quantity} x {item.productName}
            </span>
            <span>{formatMoney(item.subtotal)}</span>
          </div>
        ))}
      </div>
      <div className="row between">
        <strong>Total {formatMoney(order.totalPrice)}</strong>
        {isCancellable(order.status) ? (
          <Button variant="outline" small onClick={() => onCancel(order)}>
            Cancel order
          </Button>
        ) : null}
      </div>
    </article>
  );
}

export function OrdersPage() {
  useDocumentTitle("My orders");
  const [status, setStatus] = useState<OrderStatus | undefined>(undefined);
  const [page, setPage] = useState(1);
  const [target, setTarget] = useState<Order | null>(null);
  const orders = useMyOrders({ status, page, perPage: 10 });
  const cancel = useCancelOrder();

  return (
    <div className="stack" style={{ gap: 18 }}>
      <h1>Orders</h1>
      <div className="chips">
        <button className={`chip ${status === undefined ? "active" : ""}`} onClick={() => { setStatus(undefined); setPage(1); }}>
          All
        </button>
        {ORDER_STATUSES.map((entry) => (
          <button key={entry} className={`chip ${status === entry ? "active" : ""}`} onClick={() => { setStatus(entry); setPage(1); }}>
            {capitalize(entry)}
          </button>
        ))}
      </div>

      {orders.isPending ? <LoadingBlock /> : null}
      {orders.isError ? <ErrorState error={orders.error} onRetry={() => void orders.refetch()} /> : null}
      {orders.data && orders.data.items.length === 0 ? <EmptyState title="No orders yet" message="Your orders will appear here and update live." /> : null}
      {orders.data?.items.map((order) => (
        <OrderCard key={order.id} order={order} onCancel={setTarget} />
      ))}
      <Pagination page={orders.data} onChange={setPage} />

      {target ? (
        <ConfirmDialog
          title={`Cancel order #${target.id}`}
          message="The reserved items go back to stock. This cannot be undone."
          confirmLabel="Cancel order"
          loading={cancel.isPending}
          onClose={() => setTarget(null)}
          onConfirm={() => cancel.mutate(target.id, { onSettled: () => setTarget(null) })}
        />
      ) : null}
    </div>
  );
}
