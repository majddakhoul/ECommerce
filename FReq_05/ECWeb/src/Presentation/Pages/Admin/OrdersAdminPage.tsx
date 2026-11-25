import { useState } from "react";
import { ORDER_STATUSES, type Order, type OrderStatus } from "../../../Domain/Models/Order";
import { formatMoney } from "../../../Domain/Rules/money";
import { nextStatuses } from "../../../Domain/Rules/orderStatus";
import { useAllOrders, useDeleteOrder, useUpdateOrderStatus } from "../../../Domain/UseCases/Orders/useOrders";
import { StatusBadge } from "../../Components/Badges";
import { Button } from "../../Components/Button";
import { EmptyState, ErrorState, LoadingBlock } from "../../Components/Feedback";
import { ConfirmDialog } from "../../Components/Modal";
import { Pagination } from "../../Components/Pagination";
import { useDocumentTitle } from "../../Hooks/useDocumentTitle";
import { capitalize, formatDateTime } from "../../format";

export function OrdersAdminPage() {
  useDocumentTitle("Orders");
  const [status, setStatus] = useState<OrderStatus | undefined>(undefined);
  const [page, setPage] = useState(1);
  const [deleting, setDeleting] = useState<Order | null>(null);
  const orders = useAllOrders({ status, page, perPage: 10 });
  const update = useUpdateOrderStatus();
  const remove = useDeleteOrder();

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
      {orders.data && orders.data.items.length === 0 ? <EmptyState title="No orders" message="New orders appear here live." /> : null}
      {orders.data && orders.data.items.length > 0 ? (
        <div className="table-wrap">
          <table className="table">
            <thead>
              <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Items</th>
                <th>Total</th>
                <th>Status</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {orders.data.items.map((order) => (
                <tr key={order.id}>
                  <td>
                    <strong>#{order.id}</strong>
                    <br />
                    <span className="muted">{formatDateTime(order.createdAt)}</span>
                  </td>
                  <td>
                    {order.user?.name ?? "-"}
                    <br />
                    <span className="muted">{order.user?.email ?? ""}</span>
                  </td>
                  <td>{order.items.map((item) => `${item.quantity} x ${item.productName}`).join(", ")}</td>
                  <td>{formatMoney(order.totalPrice)}</td>
                  <td>
                    <StatusBadge status={order.status} />
                  </td>
                  <td className="actions">
                    {nextStatuses(order.status).map((next) => (
                      <Button
                        key={next}
                        variant={next === "cancelled" ? "danger" : "outline"}
                        small
                        disabled={update.isPending}
                        onClick={() => update.mutate({ id: order.id, status: next })}
                      >
                        {capitalize(next)}
                      </Button>
                    ))}
                    <Button variant="outline" small icon="trash" aria-label={`Delete order ${order.id}`} onClick={() => setDeleting(order)} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
      <Pagination page={orders.data} onChange={setPage} />

      {deleting ? (
        <ConfirmDialog
          title={`Delete order #${deleting.id}`}
          message="The order is permanently removed. Reserved stock is released when the order was still open."
          confirmLabel="Delete"
          loading={remove.isPending}
          onClose={() => setDeleting(null)}
          onConfirm={() => remove.mutate(deleting.id, { onSettled: () => setDeleting(null) })}
        />
      ) : null}
    </div>
  );
}
