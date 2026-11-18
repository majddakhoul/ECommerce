import { useSyncExternalStore } from "react";
import { Link } from "react-router-dom";
import type { Product } from "../../Domain/Models/Product";
import { formatMoney } from "../../Domain/Rules/money";
import { stockLevel } from "../../Domain/Rules/stock";
import { flashStore } from "../../Services/FlashStore";
import { StockBadge } from "./Badges";
import { Icon } from "./Icon";

const initialOf = (name: string): string => name.trim().charAt(0).toUpperCase() || "?";

export const useFlashing = (productId: number): boolean => {
  const flashing = useSyncExternalStore(flashStore.subscribe, flashStore.snapshot);
  return flashing.has(productId);
};

export function ProductCard({ product }: { product: Product }) {
  const flashing = useFlashing(product.id);
  const level = stockLevel(product.stock);

  return (
    <Link to={`/products/${product.id}`} className={`product-card ${flashing ? "flash" : ""}`}>
      <div className="product-media">
        {product.image ? <img src={product.image} alt={product.name} loading="lazy" /> : <span className="product-initial">{initialOf(product.name)}</span>}
        {level === "low" ? (
          <span className="product-stock">
            <StockBadge stock={product.stock} />
          </span>
        ) : null}
        {level === "out" ? <div className="product-overlay">OUT OF STOCK</div> : null}
      </div>
      <div className="product-name">{product.name}</div>
      <div className="product-price">{formatMoney(product.price)}</div>
    </Link>
  );
}

export function HeroCard({ product }: { product: Product }) {
  return (
    <Link to={`/products/${product.id}`} className="hero">
      <span className="hero-tag">NEW</span>
      {product.image ? <img className="hero-image" src={product.image} alt={product.name} /> : <div className="hero-initial">{initialOf(product.name)}</div>}
      <div className="hero-footer">
        <h3>{product.name}</h3>
        <div className="row">
          <span className="hero-price">{formatMoney(product.price)}</span>
          <span className="hero-cta">
            SHOP NOW <Icon name="arrowRight" size={16} />
          </span>
        </div>
      </div>
    </Link>
  );
}
