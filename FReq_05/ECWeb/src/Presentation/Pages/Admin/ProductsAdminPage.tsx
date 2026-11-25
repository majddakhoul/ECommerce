import { useState, type FormEvent } from "react";
import { toApiError } from "../../../Domain/Models/ApiError";
import type { Product, ProductDraft } from "../../../Domain/Models/Product";
import { formatMoney } from "../../../Domain/Rules/money";
import { useCreateProduct, useDeleteProduct, useProducts, useUpdateProduct } from "../../../Domain/UseCases/Products/useProducts";
import { StockBadge } from "../../Components/Badges";
import { Button } from "../../Components/Button";
import { EmptyState, ErrorState, LoadingBlock } from "../../Components/Feedback";
import { TextAreaField, TextField } from "../../Components/Field";
import { ConfirmDialog, Modal } from "../../Components/Modal";
import { Pagination } from "../../Components/Pagination";
import { useFlashing } from "../../Components/ProductCards";
import { useDebounce } from "../../Hooks/useDebounce";
import { useDocumentTitle } from "../../Hooks/useDocumentTitle";
import { initialOf } from "../../format";

interface FormState {
  name: string;
  description: string;
  price: string;
  cost: string;
  stock: string;
  category: string;
  image: File | null;
}

const blank: FormState = { name: "", description: "", price: "", cost: "", stock: "", category: "", image: null };

const fromProduct = (product: Product): FormState => ({
  name: product.name,
  description: product.description ?? "",
  price: String(product.price),
  cost: String(product.cost ?? 0),
  stock: String(product.stock),
  category: product.category ?? "",
  image: null,
});

const toDraft = (form: FormState): ProductDraft => ({
  name: form.name.trim(),
  description: form.description.trim(),
  price: Number(form.price),
  cost: Number(form.cost || 0),
  stock: Number(form.stock),
  category: form.category.trim(),
  image: form.image,
});

function ProductForm({ product, onClose }: { product: Product | null; onClose: () => void }) {
  const create = useCreateProduct();
  const update = useUpdateProduct();
  const [form, setForm] = useState<FormState>(product ? fromProduct(product) : blank);
  const mutation = product ? update : create;
  const error = mutation.error ? toApiError(mutation.error) : null;
  const set = (key: keyof FormState) => (event: { target: { value: string } }) => setForm((current) => ({ ...current, [key]: event.target.value }));

  const submit = (event: FormEvent) => {
    event.preventDefault();
    const draft = toDraft(form);
    if (product) {
      update.mutate({ id: product.id, draft }, { onSuccess: onClose });
    } else {
      create.mutate(draft, { onSuccess: onClose });
    }
  };

  return (
    <Modal
      title={product ? `Edit ${product.name}` : "New product"}
      onClose={onClose}
      footer={
        <>
          <Button variant="outline" small onClick={onClose}>
            Cancel
          </Button>
          <Button variant="purple" small type="submit" form="product-form" loading={mutation.isPending}>
            Save
          </Button>
        </>
      }
    >
      <form id="product-form" className="stack" onSubmit={submit} noValidate>
        {error && !error.isValidation ? <div className="alert">{error.message}</div> : null}
        <TextField label="Name" value={form.name} onChange={set("name")} error={error?.firstFieldError("name")} />
        <TextAreaField label="Description" value={form.description} onChange={set("description")} error={error?.firstFieldError("description")} />
        <div className="form-grid two">
          <TextField label="Price" type="number" min="0" step="0.01" value={form.price} onChange={set("price")} error={error?.firstFieldError("price")} />
          <TextField label="Cost" type="number" min="0" step="0.01" value={form.cost} onChange={set("cost")} error={error?.firstFieldError("cost")} />
          <TextField label="Stock" type="number" min="0" step="1" value={form.stock} onChange={set("stock")} error={error?.firstFieldError("stock")} />
          <TextField label="Category" value={form.category} onChange={set("category")} error={error?.firstFieldError("category")} />
        </div>
        <div className="field">
          <label htmlFor="product-image">Image</label>
          <input id="product-image" type="file" accept="image/*" onChange={(event) => setForm((current) => ({ ...current, image: event.target.files?.[0] ?? null }))} />
          {error?.firstFieldError("image") ? <span className="field-error">{error.firstFieldError("image")}</span> : null}
        </div>
      </form>
    </Modal>
  );
}

function Row({ product, onEdit, onDelete }: { product: Product; onEdit: (product: Product) => void; onDelete: (product: Product) => void }) {
  const flashing = useFlashing(product.id);
  return (
    <tr className={flashing ? "flash" : ""}>
      <td>
        <div className="row">
          <div className="thumb">{product.image ? <img src={product.image} alt="" /> : initialOf(product.name)}</div>
          <strong>{product.name}</strong>
        </div>
      </td>
      <td>{product.category ?? "-"}</td>
      <td>{formatMoney(product.price)}</td>
      <td>
        <StockBadge stock={product.stock} />
      </td>
      <td className="actions">
        <Button variant="outline" small icon="edit" onClick={() => onEdit(product)}>
          Edit
        </Button>
        <Button variant="outline" small icon="trash" onClick={() => onDelete(product)}>
          Delete
        </Button>
      </td>
    </tr>
  );
}

export function ProductsAdminPage() {
  useDocumentTitle("Products");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [editing, setEditing] = useState<Product | null | "new">(null);
  const [deleting, setDeleting] = useState<Product | null>(null);
  const debounced = useDebounce(search);
  const products = useProducts({ search: debounced || undefined, page, perPage: 10 });
  const remove = useDeleteProduct();

  return (
    <div className="stack" style={{ gap: 18 }}>
      <div className="page-header" style={{ marginBottom: 0 }}>
        <h1>Products</h1>
        <Button variant="purple" small icon="plus" onClick={() => setEditing("new")}>
          New product
        </Button>
      </div>
      <div className="filters">
        <input className="input" type="search" placeholder="Search products" value={search} onChange={(event) => { setSearch(event.target.value); setPage(1); }} aria-label="Search products" />
      </div>

      {products.isPending ? <LoadingBlock /> : null}
      {products.isError ? <ErrorState error={products.error} onRetry={() => void products.refetch()} /> : null}
      {products.data && products.data.items.length === 0 ? <EmptyState title="No products" message="Create the first product to get started." /> : null}
      {products.data && products.data.items.length > 0 ? (
        <div className="table-wrap">
          <table className="table">
            <thead>
              <tr>
                <th>Product</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {products.data.items.map((product) => (
                <Row key={product.id} product={product} onEdit={setEditing} onDelete={setDeleting} />
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
      <Pagination page={products.data} onChange={setPage} />

      {editing ? <ProductForm product={editing === "new" ? null : editing} onClose={() => setEditing(null)} /> : null}
      {deleting ? (
        <ConfirmDialog
          title={`Delete ${deleting.name}`}
          message="The product is removed from the catalog and from its stock record."
          confirmLabel="Delete"
          loading={remove.isPending}
          onClose={() => setDeleting(null)}
          onConfirm={() => remove.mutate(deleting.id, { onSettled: () => setDeleting(null) })}
        />
      ) : null}
    </div>
  );
}
