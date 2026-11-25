import { useQueryClient } from "@tanstack/react-query";
import { useEffect, useRef, useState } from "react";
import { flashStore } from "../../../Services/FlashStore";
import { notify } from "../../../Services/Notifications";
import type { Cart } from "../../Models/Cart";
import type { Page } from "../../Models/Pagination";
import type { Product } from "../../Models/Product";
import type { ConnectionStatus, OrderEvent, ProductEvent, StockEvent } from "../../Models/Realtime";
import { shouldApplyStockEvent } from "../../Rules/stock";
import { useSession } from "../Auth/useSession";
import { useDependencies } from "../Dependencies";
import { keys } from "../queryKeys";
import {
  RecentKeys,
  applyStockToCart,
  applyStockToPage,
  applyStockToProduct,
  findCartShortage,
  removeProductFromPage,
  upsertProductInPage,
} from "./cacheUpdates";

const PRODUCT_LISTS = ["products", "list"] as const;

export const useConnectionStatus = (): ConnectionStatus => {
  const { realtime } = useDependencies();
  const [status, setStatus] = useState<ConnectionStatus>(() => realtime.status());

  useEffect(() => {
    setStatus(realtime.status());
    return realtime.onStatusChange(setStatus);
  }, [realtime]);

  return status;
};

export const useRealtimeSync = (): void => {
  const { realtime } = useDependencies();
  const { user, isAdmin } = useSession();
  const queryClient = useQueryClient();
  const versions = useRef(new Map<number, number>());
  const recent = useRef(new RecentKeys());

  const userId = user?.id ?? null;

  useEffect(() => {
    if (!realtime.enabled || userId === null) {
      return undefined;
    }

    realtime.connect();

    const handleProduct = (event: ProductEvent) => {
      if (event.action === "deleted") {
        queryClient.removeQueries({ queryKey: keys.product(event.productId) });
        queryClient.setQueriesData<Page<Product>>({ queryKey: PRODUCT_LISTS }, (page) => removeProductFromPage(page, event.productId));
        queryClient.invalidateQueries({ queryKey: PRODUCT_LISTS });
        return;
      }

      if (event.product) {
        queryClient.setQueryData(keys.product(event.productId), event.product);
        const product = event.product;
        queryClient.setQueriesData<Page<Product>>({ queryKey: PRODUCT_LISTS }, (page) => upsertProductInPage(page, product));
        versions.current.delete(event.productId);
        flashStore.flash(event.productId);
      }

      if (event.action === "created") {
        queryClient.invalidateQueries({ queryKey: PRODUCT_LISTS });
        queryClient.invalidateQueries({ queryKey: keys.categories });
        if (!isAdmin && event.product) {
          notify.info("New product", event.product.name);
        }
      }
    };

    const handleStock = (event: StockEvent) => {
      if (!shouldApplyStockEvent(versions.current.get(event.productId), event.version)) {
        return;
      }
      versions.current.set(event.productId, event.version);

      if (event.deleted) {
        queryClient.removeQueries({ queryKey: keys.product(event.productId) });
        queryClient.setQueriesData<Page<Product>>({ queryKey: PRODUCT_LISTS }, (page) => removeProductFromPage(page, event.productId));
        return;
      }

      const cartBefore = queryClient.getQueryData<Cart>(keys.cart);
      const shortage = findCartShortage(cartBefore, event);

      queryClient.setQueriesData<Page<Product>>({ queryKey: PRODUCT_LISTS }, (page) => applyStockToPage(page, event));
      queryClient.setQueryData<Product>(keys.product(event.productId), (product) =>
        product ? applyStockToProduct(product, event) : product,
      );
      queryClient.setQueryData<Cart>(keys.cart, (cart) => applyStockToCart(cart, event));
      flashStore.flash(event.productId);

      if (shortage) {
        notify.warning(
          shortage.available === 0 ? `${shortage.name} is out of stock` : `Only ${shortage.available} left of ${shortage.name}`,
          `You have ${shortage.wanted} in your cart.`,
        );
      }
    };

    const handleOrder = (event: OrderEvent) => {
      if (!recent.current.firstSeen(`${event.kind}:${event.orderId}:${event.order.status}:${event.order.updatedAt}`)) {
        return;
      }

      if (event.kind === "deleted") {
        queryClient.removeQueries({ queryKey: keys.order(event.orderId) });
      } else {
        queryClient.setQueryData(keys.order(event.orderId), event.order);
      }
      queryClient.invalidateQueries({ queryKey: keys.orders });

      const mine = event.order.userId === userId;

      if (event.kind === "status_changed" && mine) {
        notify.info(`Order #${event.orderId} is now ${event.order.status}`);
      }

      if (isAdmin && !mine) {
        if (event.kind === "created") {
          notify.info(`New order #${event.orderId}`, event.order.user?.name);
        }
        if (event.kind === "cancelled") {
          notify.warning(`Order #${event.orderId} was cancelled by the customer`);
        }
      }
    };

    const unsubscribers = [
      realtime.onProducts(handleProduct),
      realtime.onStock(handleStock),
      realtime.onCart(userId, (event) => queryClient.setQueryData(keys.cart, event.cart)),
      realtime.onMyOrders(userId, handleOrder),
    ];

    if (isAdmin) {
      unsubscribers.push(realtime.onAllOrders(handleOrder));
    }

    let wasConnected = realtime.status() === "connected";
    unsubscribers.push(
      realtime.onStatusChange((status) => {
        if (status === "connected" && !wasConnected) {
          queryClient.invalidateQueries();
        }
        wasConnected = status === "connected";
      }),
    );

    return () => {
      unsubscribers.forEach((unsubscribe) => unsubscribe());
      realtime.disconnect();
    };
  }, [realtime, queryClient, userId, isAdmin]);
};
