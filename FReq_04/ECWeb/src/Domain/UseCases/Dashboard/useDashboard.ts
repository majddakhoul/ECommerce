import { useMemo } from "react";
import { useAllOrders } from "../Orders/useOrders";
import { useProducts } from "../Products/useProducts";
import { useUsers } from "../Users/useUsers";
import { buildDashboard } from "./dashboard";

export const DASHBOARD_SAMPLE = 100;

export const useDashboard = () => {
  const orders = useAllOrders({ perPage: DASHBOARD_SAMPLE });
  const products = useProducts({ perPage: DASHBOARD_SAMPLE });
  const users = useUsers({ perPage: 1 });

  const stats = useMemo(() => {
    if (!orders.data || !products.data) {
      return null;
    }
    return buildDashboard({
      orders: orders.data.items,
      totalOrders: orders.data.total,
      products: products.data.items,
      totalProducts: products.data.total,
      totalUsers: users.data?.total ?? 0,
    });
  }, [orders.data, products.data, users.data]);

  return {
    stats,
    isLoading: orders.isPending || products.isPending,
    error: orders.error ?? products.error ?? null,
    refetch: () => Promise.all([orders.refetch(), products.refetch(), users.refetch()]),
  };
};
