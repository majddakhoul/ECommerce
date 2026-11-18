import { useState } from "react";
import { Link, useParams } from "react-router-dom";
import { stockLevel } from "../../../Domain/Rules/stock";
import { formatMoney } from "../../../Domain/Rules/money";
import { useAddToCart } from "../../../Domain/UseCases/Cart/useCart";
import { useProduct } from "../../../Domain/UseCases/Products/useProducts";
import { StockBadge } from "../../Components/Badges";
import { Button } from "../../Components/Button";
import { ErrorState, LoadingBlock } from "../../Components/Feedback";
import { Icon } from "../../Components/Icon";
import { useFlashing } from "../../Components/ProductCards";
import { useDocumentTitle } from "../../Hooks/useDocumentTitle";
import { capitalize, initialOf } from "../../format";

export function ProductDetailPage() {
  const id = Number(useParams().id);
  const product = useProduct(id);
  const addToCart = useAddToCart();
  const flashing = useFlashing(id);
  const [quantity, setQuantity] = useState(1);

  useDocumentTitle(product.data?.name ?? "Product");

  if (product.isPending) {
    return <LoadingBlock />;
  }
  if (product.isError || !product.data) {
    return <ErrorState error={product.error} onRetry={() => void product.refetch()} />;
  }

  const item = product.data;
  const level = stockLevel(item.stock);
  const clamped = Math.min(Math.max(1, quantity), Math.max(1, item.stock));

  return (
    <div className="stack" style={{ gap: 18 }}>
      <Link to="/" className="row muted" style={{ fontWeight: 700 }}>
        <Icon name="arrowLeft" size={18} /> Back
      </Link>
      <div className="detail-grid">
        <div className={`detail-media ${flashing ? "product-card flash" : ""}`}>
          {item.image ? <img src={item.image} alt={item.name} /> : <span className="hero-initial" style={{ position: "static", padding: 0 }}>{initialOf(item.name)}</span>}
        </div>
        <div className="stack" style={{ gap: 14 }}>
          {item.category ? <span className="badge" style={{ alignSelf: "flex-start" }}>{capitalize(item.category)}</span> : null}
          <h1>{item.name}</h1>
          <div className="row">
            <strong style={{ fontSize: 26 }}>{formatMoney(item.price)}</strong>
            <StockBadge stock={item.stock} />
          </div>
          <p className="muted">{item.description || "No description available."}</p>
          <div className="row wrap" style={{ marginTop: 8 }}>
            <div className="qty">
              <button aria-label="Decrease quantity" disabled={clamped <= 1} onClick={() => setQuantity(clamped - 1)}>
                <Icon name="minus" size={14} />
              </button>
              <span>{clamped}</span>
              <button aria-label="Increase quantity" disabled={clamped >= item.stock} onClick={() => setQuantity(clamped + 1)}>
                <Icon name="plus" size={14} />
              </button>
            </div>
            <Button
              variant="purple"
              icon="bag"
              disabled={level === "out"}
              loading={addToCart.isPending}
              onClick={() => addToCart.mutate({ productId: item.id, quantity: clamped })}
            >
              {level === "out" ? "Out of stock" : "Add to cart"}
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}
