import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { notify } from "../../../Services/Notifications";
import { emptyCart, type Cart } from "../../Models/Cart";
import { useDependencies } from "../Dependencies";
import { keys } from "../queryKeys";
import { useSession } from "../Auth/useSession";

export const useCart = () => {
  const { cart } = useDependencies();
  const { isAuthenticated } = useSession();
  return useQuery({ queryKey: keys.cart, queryFn: () => cart.get(), enabled: isAuthenticated });
};

export const useCartCount = (): number => useCart().data?.totalQuantity ?? 0;

const useCartMutation = <TVariables,>(run: (cart: ReturnType<typeof useDependencies>["cart"], variables: TVariables) => Promise<Cart>, message?: string) => {
  const dependencies = useDependencies();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (variables: TVariables) => run(dependencies.cart, variables),
    onSuccess: (cart) => {
      queryClient.setQueryData(keys.cart, cart);
      queryClient.invalidateQueries({ queryKey: keys.products });
      if (message) {
        notify.success(message);
      }
    },
  });
};

export const useAddToCart = () =>
  useCartMutation(
    (cart, { productId, quantity }: { productId: number; quantity: number }) => cart.addItem(productId, quantity),
    "Added to your cart",
  );

export const useUpdateCartItem = () =>
  useCartMutation((cart, { productId, quantity }: { productId: number; quantity: number }) =>
    cart.updateItem(productId, quantity),
  );

export const useRemoveCartItem = () => useCartMutation((cart, productId: number) => cart.removeItem(productId));

export const useClearCart = () => {
  const { cart } = useDependencies();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: () => cart.clear(),
    onSuccess: () => queryClient.setQueryData(keys.cart, emptyCart()),
  });
};

export const useCheckout = () => {
  const { cart } = useDependencies();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: () => cart.checkout(),
    onSuccess: (order) => {
      queryClient.setQueryData(keys.cart, emptyCart());
      queryClient.invalidateQueries({ queryKey: keys.orders });
      queryClient.invalidateQueries({ queryKey: keys.products });
      notify.success(`Order #${order.id} placed`, "You will receive a confirmation email.");
    },
  });
};
