import { ORDER_STATUSES, type Order, type OrderStatus } from "../../Models/Order";
import type { Product } from "../../Models/Product";
import { isOpen } from "../../Rules/orderStatus";
import { LOW_STOCK_THRESHOLD } from "../../Rules/stock";

export interface DailyPoint {
  date: string;
  label: string;
  orders: number;
  revenue: number;
}

export interface TopProduct {
  productId: number;
  name: string;
  quantity: number;
  revenue: number;
}

export interface DashboardInput {
  orders: Order[];
  totalOrders: number;
  products: Product[];
  totalProducts: number;
  totalUsers: number;
  now?: Date;
}

export interface DashboardStats {
  totalOrders: number;
  sampledOrders: number;
  revenue: number;
  openOrders: number;
  averageOrderValue: number;
  byStatus: Record<OrderStatus, number>;
  last7Days: DailyPoint[];
  topProducts: TopProduct[];
  lowStock: Product[];
  outOfStock: number;
  totalProducts: number;
  totalUsers: number;
  recentOrders: Order[];
}

const dayKey = (date: Date): string =>
  `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;

export const buildDashboard = ({ orders, totalOrders, products, totalProducts, totalUsers, now = new Date() }: DashboardInput): DashboardStats => {
  const byStatus = Object.fromEntries(ORDER_STATUSES.map((status) => [status, 0])) as Record<OrderStatus, number>;
  const productTotals = new Map<number, TopProduct>();

  let revenue = 0;
  let revenueOrders = 0;
  let openOrders = 0;

  const days: DailyPoint[] = Array.from({ length: 7 }, (_, offset) => {
    const date = new Date(now.getFullYear(), now.getMonth(), now.getDate() - (6 - offset));
    return {
      date: dayKey(date),
      label: date.toLocaleDateString("en-US", { weekday: "short" }),
      orders: 0,
      revenue: 0,
    };
  });
  const dayIndex = new Map(days.map((day, index) => [day.date, index]));

  orders.forEach((order) => {
    byStatus[order.status] += 1;

    if (isOpen(order.status)) {
      openOrders += 1;
    }

    const counted = order.status !== "cancelled";
    if (counted) {
      revenue += order.totalPrice;
      revenueOrders += 1;
      order.items.forEach((item) => {
        const current = productTotals.get(item.productId) ?? {
          productId: item.productId,
          name: item.productName,
          quantity: 0,
          revenue: 0,
        };
        current.quantity += item.quantity;
        current.revenue += item.subtotal;
        productTotals.set(item.productId, current);
      });
    }

    const index = dayIndex.get(dayKey(new Date(order.createdAt)));
    if (index !== undefined) {
      days[index].orders += 1;
      if (counted) {
        days[index].revenue += order.totalPrice;
      }
    }
  });

  const lowStock = products
    .filter((product) => product.stock > 0 && product.stock <= LOW_STOCK_THRESHOLD)
    .sort((a, b) => a.stock - b.stock)
    .slice(0, 6);

  return {
    totalOrders,
    sampledOrders: orders.length,
    revenue,
    openOrders,
    averageOrderValue: revenueOrders === 0 ? 0 : revenue / revenueOrders,
    byStatus,
    last7Days: days,
    topProducts: [...productTotals.values()].sort((a, b) => b.quantity - a.quantity).slice(0, 5),
    lowStock,
    outOfStock: products.filter((product) => product.stock <= 0).length,
    totalProducts,
    totalUsers,
    recentOrders: [...orders].sort((a, b) => b.id - a.id).slice(0, 6),
  };
};
