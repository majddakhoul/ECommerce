import { ORDER_STATUSES } from "../../../Domain/Models/Order";
import { formatMoney } from "../../../Domain/Rules/money";
import { DASHBOARD_SAMPLE, useDashboard } from "../../../Domain/UseCases/Dashboard/useDashboard";
import { StatusBadge } from "../../Components/Badges";
import { DailyBars, Meter } from "../../Components/Charts";
import { ErrorState, LoadingBlock } from "../../Components/Feedback";
import { Button } from "../../Components/Button";
import { useDocumentTitle } from "../../Hooks/useDocumentTitle";
import { capitalize, formatDateTime } from "../../format";

const STATUS_COLOR: Record<string, string> = {
  pending: "var(--warning)",
  confirmed: "var(--info)",
  processing: "var(--accent)",
  delivered: "var(--success)",
  cancelled: "var(--danger)",
};

interface KpiProps {
  label: string;
  value: string | number;
  note?: string;
}

function Kpi({ label, value, note }: KpiProps) {
  return (
    <div className="kpi">
      <span className="kpi-label">{label}</span>
      <span className="kpi-value">{value}</span>
      {note ? <span className="kpi-note">{note}</span> : null}
    </div>
  );
}

export function DashboardPage() {
  useDocumentTitle("Dashboard");
  const { stats, isLoading, error, refetch } = useDashboard();

  if (isLoading) {
    return <LoadingBlock />;
  }
  if (!stats) {
    return <ErrorState error={error} onRetry={() => void refetch()} />;
  }

  const sampled = stats.sampledOrders < stats.totalOrders;

  return (
    <div className="stack" style={{ gap: 20 }}>
      <div className="page-header" style={{ marginBottom: 0 }}>
        <h1>Dashboard</h1>
        <Button variant="outline" small icon="refresh" onClick={() => void refetch()}>
          Refresh
        </Button>
      </div>

      <div className="kpi-grid">
        <Kpi label="Revenue" value={formatMoney(stats.revenue)} note={sampled ? `Latest ${DASHBOARD_SAMPLE} orders` : "All orders"} />
        <Kpi label="Orders" value={stats.totalOrders} note={`${stats.openOrders} open`} />
        <Kpi label="Average order" value={formatMoney(stats.averageOrderValue)} />
        <Kpi label="Products" value={stats.totalProducts} note={`${stats.outOfStock} out of stock`} />
        <Kpi label="Users" value={stats.totalUsers} />
      </div>

      <div className="dash-grid">
        <section className="card">
          <div className="card-title">Orders, last 7 days</div>
          <DailyBars points={stats.last7Days} metric="orders" />
        </section>
        <section className="card">
          <div className="card-title">Revenue, last 7 days</div>
          <DailyBars points={stats.last7Days} metric="revenue" />
        </section>
      </div>

      <div className="dash-grid">
        <section className="card">
          <div className="card-title">Orders by status</div>
          <div className="stack" style={{ gap: 14 }}>
            {ORDER_STATUSES.map((status) => (
              <div key={status} className="stack" style={{ gap: 6 }}>
                <div className="stat-line">
                  <span>{capitalize(status)}</span>
                  <strong>{stats.byStatus[status]}</strong>
                </div>
                <Meter value={stats.byStatus[status]} max={Math.max(stats.sampledOrders, 1)} color={STATUS_COLOR[status]} />
              </div>
            ))}
          </div>
        </section>

        <section className="card">
          <div className="card-title">Top products</div>
          {stats.topProducts.length === 0 ? <p className="muted">No sales yet.</p> : null}
          {stats.topProducts.map((product) => (
            <div key={product.productId} className="list-row">
              <span className="grow">{product.name}</span>
              <span className="muted">{product.quantity} sold</span>
              <strong>{formatMoney(product.revenue)}</strong>
            </div>
          ))}
        </section>
      </div>

      <div className="dash-grid">
        <section className="card">
          <div className="card-title">Low stock</div>
          {stats.lowStock.length === 0 ? <p className="muted">Everything is well stocked.</p> : null}
          {stats.lowStock.map((product) => (
            <div key={product.id} className="list-row">
              <span className="grow">{product.name}</span>
              <span className="badge badge-low">{product.stock} left</span>
            </div>
          ))}
        </section>

        <section className="card">
          <div className="card-title">Recent orders</div>
          {stats.recentOrders.map((order) => (
            <div key={order.id} className="list-row">
              <span className="grow">
                #{order.id} {order.user?.name ?? ""}
                <br />
                <span className="muted">{formatDateTime(order.createdAt)}</span>
              </span>
              <StatusBadge status={order.status} />
              <strong>{formatMoney(order.totalPrice)}</strong>
            </div>
          ))}
        </section>
      </div>
    </div>
  );
}
