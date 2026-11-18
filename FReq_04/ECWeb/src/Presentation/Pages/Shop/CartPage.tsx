import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { formatMoney } from "../../../Domain/Rules/money";
import {
  useCart,
  useCheckout,
  useClearCart,
  useRemoveCartItem,
  useUpdateCartItem,
} from "../../../Domain/UseCases/Cart/useCart";
import { Button } from "../../Components/Button";
import { ConfirmDialog } from "../../Components/Modal";
import { EmptyState, ErrorState, LoadingBlock } from "../../Components/Feedback";
import { Icon } from "../../Components/Icon";
import { useDocumentTitle } from "../../Hooks/useDocumentTitle";

export function CartPage() {
  useDocumentTitle("Cart");
  const navigate = useNavigate();
  const cart = useCart();
  const update = useUpdateCartItem();
  const remove = useRemoveCartItem();
  const clear = useClearCart();
  const checkout = useCheckout();
  const [confirmClear, setConfirmClear] = useState(false);

  if (cart.isPending) {
    return <LoadingBlock />;
  }
  if (cart.isError) {
    return <ErrorState error={cart.error} onRetry={() => void cart.refetch()} />;
  }

  const data = cart.data;

  if (data.items.length === 0) {
    return (
      <EmptyState
        title="Your cart is empty"
        message="Add something you like and it will show up here, on every device."
        action={<Link to="/" className="btn btn-purple btn-sm">Browse products</Link>}
      />
    );
  }

  const blocked = data.items.some((item) => item.quantity > item.availableStock);

  return (
    <div className="stack" style={{ gap: 20 }}>
      <div className="page-header" style={{ marginBottom: 0 }}>
        <h1>Cart</h1>
        <Button variant="outline" small onClick={() => setConfirmClear(true)}>
          Clear cart
        </Button>
      </div>

      <div className="detail-grid" style={{ alignItems: "start" }}>
        <div className="card">
          {data.items.map((item) => {
            const short = item.quantity > item.availableStock;
            return (
              <div key={item.productId} className="cart-line">
                <div className="grow stack" style={{ gap: 4 }}>
                  <Link to={`/products/${item.productId}`} style={{ fontWeight: 700 }}>
                    {item.name}
                  </Link>
                  <span className="muted">{formatMoney(item.price)} each</span>
                  {short ? (
                    <span className="field-error">
                      {item.availableStock === 0 ? "Out of stock now." : `Only ${item.availableStock} available now.`}
                    </span>
                  ) : null}
                </div>
                <div className="qty">
                  <button aria-label="Decrease quantity" disabled={item.quantity <= 1 || update.isPending} onClick={() => update.mutate({ productId: item.productId, quantity: item.quantity - 1 })}>
                    <Icon name="minus" size={14} />
                  </button>
                  <span>{item.quantity}</span>
                  <button aria-label="Increase quantity" disabled={item.quantity >= item.availableStock || update.isPending} onClick={() => update.mutate({ productId: item.productId, quantity: item.quantity + 1 })}>
                    <Icon name="plus" size={14} />
                  </button>
                </div>
                <strong style={{ minWidth: 80, textAlign: "right" }}>{formatMoney(item.subtotal)}</strong>
                <button className="icon-btn" aria-label={`Remove ${item.name}`} onClick={() => remove.mutate(item.productId)}>
                  <Icon name="trash" size={18} />
                </button>
              </div>
            );
          })}
        </div>

        <div className="card soft stack">
          <h3>Summary</h3>
          <div className="summary-row">
            <span className="muted">Items</span>
            <span>{data.totalQuantity}</span>
          </div>
          <div className="summary-row total">
            <span>Total</span>
            <span>{formatMoney(data.totalPrice)}</span>
          </div>
          {blocked ? <div className="alert">Some items exceed the available stock. Adjust them to continue.</div> : null}
          <Button block variant="purple" loading={checkout.isPending} disabled={blocked} onClick={() => checkout.mutate(undefined, { onSuccess: () => navigate("/orders") })}>
            Place order
          </Button>
        </div>
      </div>

      {confirmClear ? (
        <ConfirmDialog
          title="Clear cart"
          message="Remove every item from your cart?"
          confirmLabel="Clear"
          loading={clear.isPending}
          onClose={() => setConfirmClear(false)}
          onConfirm={() => clear.mutate(undefined, { onSettled: () => setConfirmClear(false) })}
        />
      ) : null}
    </div>
  );
}
