import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { notify } from "../../../Services/Notifications";
import type { Order, OrderFilter, OrderLine, OrderStatus } from "../../Models/Order";
import { useDependencies } from "../Dependencies";
import { keys } from "../queryKeys";

export const useMyOrders = (filter: OrderFilter) => {
  const { orders } = useDependencies();
  return useQuery({
    queryKey: keys.myOrders(filter),
    queryFn: () => orders.listMine(filter),
    placeholderData: keepPreviousData,
  });
};

export const useAllOrders = (filter: OrderFilter, enabled = true) => {
  const { orders } = useDependencies();
  return useQuery({
    queryKey: keys.allOrders(filter),
    queryFn: () => orders.listAll(filter),
    placeholderData: keepPreviousData,
    enabled,
  });
};

export const useOrder = (id: number) => {
  const { orders } = useDependencies();
  return useQuery({ queryKey: keys.order(id), queryFn: () => orders.get(id), enabled: Number.isFinite(id) });
};

const useOrderMutation = <TVariables,>(run: (orders: ReturnType<typeof useDependencies>["orders"], variables: TVariables) => Promise<Order>, message: (order: Order) => string) => {
  const dependencies = useDependencies();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (variables: TVariables) => run(dependencies.orders, variables),
    onSuccess: (order) => {
      queryClient.setQueryData(keys.order(order.id), order);
      queryClient.invalidateQueries({ queryKey: keys.orders });
      queryClient.invalidateQueries({ queryKey: keys.products });
      notify.success(message(order));
    },
  });
};

export const usePlaceOrder = () =>
  useOrderMutation((orders, lines: OrderLine[]) => orders.place(lines), (order) => `Order #${order.id} placed`);

export const useCancelOrder = () =>
  useOrderMutation((orders, id: number) => orders.cancel(id), (order) => `Order #${order.id} cancelled`);

export const useUpdateOrderStatus = () =>
  useOrderMutation(
    (orders, { id, status }: { id: number; status: OrderStatus }) => orders.updateStatus(id, status),
    (order) => `Order #${order.id} is now ${order.status}`,
  );

export const useDeleteOrder = () => {
  const { orders } = useDependencies();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => orders.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: keys.orders });
      notify.success("Order deleted");
    },
  });
};
