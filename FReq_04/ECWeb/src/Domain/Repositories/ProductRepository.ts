import type { Page } from "../Models/Pagination";
import type { Product, ProductDraft, ProductFilter } from "../Models/Product";

export interface ProductRepository {
  list(filter: ProductFilter): Promise<Page<Product>>;
  get(id: number): Promise<Product>;
  categories(): Promise<string[]>;
  create(draft: ProductDraft): Promise<Product>;
  update(id: number, draft: Partial<ProductDraft>): Promise<Product>;
  remove(id: number): Promise<void>;
}
