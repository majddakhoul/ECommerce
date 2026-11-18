import type { OrderStatus } from "../../Domain/Models/Order";
import { stockLevel } from "../../Domain/Rules/stock";

const capitalize = (value: string): string => value.charAt(0).toUpperCase() + value.slice(1);

export function StatusBadge({ status }: { status: OrderStatus }) {
  return <span className={`badge badge-${status}`}>{capitalize(status)}</span>;
}

export function RoleBadge({ role }: { role: string }) {
  const known = role === "admin" || role === "user";
  return <span className={`badge ${known ? `badge-${role}` : ""}`}>{capitalize(role)}</span>;
}

export function ActiveBadge({ active, locked }: { active: boolean; locked?: boolean }) {
  if (locked) {
    return <span className="badge badge-locked">Locked</span>;
  }
  return <span className={`badge ${active ? "badge-active" : "badge-inactive"}`}>{active ? "Active" : "Inactive"}</span>;
}

export function StockBadge({ stock }: { stock: number }) {
  const level = stockLevel(stock);
  const label = level === "out" ? "Out of stock" : level === "low" ? `Only ${stock} left` : `${stock} in stock`;
  return <span className={`badge badge-${level}`}>{label}</span>;
}
