import { formatMoney } from "../../Domain/Rules/money";
import type { DailyPoint } from "../../Domain/UseCases/Dashboard/dashboard";

export function DailyBars({ points, metric }: { points: DailyPoint[]; metric: "orders" | "revenue" }) {
  const values = points.map((point) => point[metric]);
  const max = Math.max(...values, 1);

  return (
    <div className="bars" role="img" aria-label={`Last seven days ${metric}`}>
      {points.map((point) => {
        const value = point[metric];
        return (
          <div key={point.date} className="bar-col">
            <span className="bar-value">{metric === "revenue" ? formatMoney(value).replace(".00", "") : value}</span>
            <div className="bar" style={{ height: `${(value / max) * 100}%` }} />
            <span className="bar-label">{point.label}</span>
          </div>
        );
      })}
    </div>
  );
}

export function Meter({ value, max, color }: { value: number; max: number; color?: string }) {
  const percentage = max <= 0 ? 0 : Math.min(100, (value / max) * 100);
  return (
    <div className="meter" role="progressbar" aria-valuenow={value} aria-valuemax={max}>
      <div style={{ width: `${percentage}%`, ["--meter" as string]: color }} />
    </div>
  );
}
