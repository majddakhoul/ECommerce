import { useMemo, useState } from "react";
import { useCategories, useProducts } from "../../../Domain/UseCases/Products/useProducts";
import { EmptyState, ErrorState, SkeletonGrid } from "../../Components/Feedback";
import { Icon } from "../../Components/Icon";
import { Pagination } from "../../Components/Pagination";
import { HeroCard, ProductCard } from "../../Components/ProductCards";
import { useDebounce } from "../../Hooks/useDebounce";
import { useDocumentTitle } from "../../Hooks/useDocumentTitle";
import { capitalize } from "../../format";

const PAGE_SIZE = 12;

export function HomePage() {
  useDocumentTitle("Shop");
  const [search, setSearch] = useState("");
  const [category, setCategory] = useState<string | undefined>(undefined);
  const [page, setPage] = useState(1);
  const debounced = useDebounce(search);

  const categories = useCategories();
  const latest = useProducts({ perPage: PAGE_SIZE });
  const products = useProducts({ search: debounced || undefined, category, page, perPage: PAGE_SIZE });

  const newest = useMemo(
    () => [...(latest.data?.items ?? [])].sort((a, b) => b.id - a.id).slice(0, 6),
    [latest.data],
  );

  const browsing = Boolean(debounced || category);

  return (
    <div className="stack" style={{ gap: 24 }}>
      <label className="search">
        <input
          type="search"
          placeholder="Try search..."
          value={search}
          onChange={(event) => {
            setSearch(event.target.value);
            setPage(1);
          }}
          aria-label="Search products"
        />
        <Icon name="search" />
      </label>

      {!browsing && newest.length > 0 ? (
        <section className="stack" style={{ gap: 12 }}>
          <h2>New In</h2>
          <div className="hero-row">
            {newest.map((product) => (
              <HeroCard key={product.id} product={product} />
            ))}
          </div>
        </section>
      ) : null}

      <section className="stack" style={{ gap: 12 }}>
        <h2>Collection</h2>
        <div className="chips" role="tablist" aria-label="Categories">
          <button className={`chip ${category === undefined ? "active" : ""}`} onClick={() => { setCategory(undefined); setPage(1); }}>
            See all
          </button>
          {(categories.data ?? []).map((name) => (
            <button key={name} className={`chip ${category === name ? "active" : ""}`} onClick={() => { setCategory(name); setPage(1); }}>
              {capitalize(name)}
            </button>
          ))}
        </div>

        {products.isPending ? <SkeletonGrid /> : null}
        {products.isError ? <ErrorState error={products.error} onRetry={() => void products.refetch()} /> : null}
        {products.data && products.data.items.length === 0 ? <EmptyState title="No products found" message="Try another search or category." /> : null}
        {products.data && products.data.items.length > 0 ? (
          <>
            <div className="product-grid">
              {products.data.items.map((product) => (
                <ProductCard key={product.id} product={product} />
              ))}
            </div>
            <Pagination page={products.data} onChange={setPage} />
          </>
        ) : null}
      </section>
    </div>
  );
}
