import type { OrderFilter, OrderLine, OrderStatus } from "../../Domain/Models/Order";
import type { ProductDraft, ProductFilter } from "../../Domain/Models/Product";
import type { Credentials, Registration, UserDraft, UserFilter } from "../../Domain/Models/User";
import type { PasswordChange, ProfileChanges } from "../../Domain/Repositories/AuthRepository";
import type { HttpClient } from "../../Services/Http";
import type {
  CartDto,
  Envelope,
  OrderDto,
  PagedEnvelope,
  ProductDto,
  RoleDto,
  SessionDto,
  UserDto,
} from "../DTOs/ApiDtos";

export class AuthApiDataSource {
  constructor(private readonly http: HttpClient) {}

  login = async (credentials: Credentials) =>
    (await this.http.post<Envelope<SessionDto>>("/auth/login", credentials, { skipAuth: true })).data.data;

  register = async (registration: Registration) =>
    (
      await this.http.post<Envelope<SessionDto>>(
        "/auth/register",
        {
          name: registration.name,
          email: registration.email,
          password: registration.password,
          password_confirmation: registration.passwordConfirmation,
        },
        { skipAuth: true },
      )
    ).data.data;

  me = async () => (await this.http.get<Envelope<UserDto>>("/auth/me")).data.data;

  updateProfile = async (changes: ProfileChanges) =>
    (await this.http.put<Envelope<UserDto>>("/auth/me", changes)).data.data;

  changePassword = async (change: PasswordChange) =>
    (
      await this.http.put<Envelope<SessionDto>>("/auth/password", {
        current_password: change.currentPassword,
        password: change.password,
        password_confirmation: change.passwordConfirmation,
      })
    ).data.data;

  refresh = async () => (await this.http.post<Envelope<SessionDto>>("/auth/refresh")).data.data;

  logout = async () => {
    await this.http.post("/auth/logout");
  };
}

const productForm = (draft: Partial<ProductDraft>, method?: "PUT"): FormData => {
  const form = new FormData();
  if (method) {
    form.append("_method", method);
  }
  if (draft.name !== undefined) form.append("name", draft.name);
  if (draft.description !== undefined) form.append("description", draft.description);
  if (draft.price !== undefined) form.append("price", String(draft.price));
  if (draft.cost !== undefined) form.append("cost", String(draft.cost));
  if (draft.stock !== undefined) form.append("stock", String(draft.stock));
  if (draft.category !== undefined) form.append("category", draft.category);
  if (draft.image) form.append("image", draft.image);
  return form;
};

export class ProductApiDataSource {
  constructor(private readonly http: HttpClient) {}

  list = async (filter: ProductFilter) =>
    (
      await this.http.get<PagedEnvelope<ProductDto>>("/products", {
        params: {
          search: filter.search,
          category: filter.category,
          in_stock: filter.inStock,
          page: filter.page,
          per_page: filter.perPage,
        },
      })
    ).data;

  get = async (id: number) => (await this.http.get<Envelope<ProductDto>>(`/products/${id}`)).data.data;

  categories = async () => (await this.http.get<Envelope<string[]>>("/products/categories")).data.data;

  create = async (draft: ProductDraft) =>
    (await this.http.post<Envelope<ProductDto>>("/products", productForm(draft))).data.data;

  update = async (id: number, draft: Partial<ProductDraft>) => {
    if (draft.image) {
      return (await this.http.post<Envelope<ProductDto>>(`/products/${id}`, productForm(draft, "PUT"))).data.data;
    }
    const { image: _image, ...fields } = draft;
    return (await this.http.put<Envelope<ProductDto>>(`/products/${id}`, fields)).data.data;
  };

  remove = async (id: number) => {
    await this.http.delete(`/products/${id}`);
  };
}

export class CartApiDataSource {
  constructor(private readonly http: HttpClient) {}

  get = async () => (await this.http.get<Envelope<CartDto>>("/cart")).data.data;

  addItem = async (productId: number, quantity: number) =>
    (await this.http.post<Envelope<CartDto>>("/cart/items", { product_id: productId, quantity })).data.data;

  updateItem = async (productId: number, quantity: number) =>
    (await this.http.put<Envelope<CartDto>>(`/cart/items/${productId}`, { quantity })).data.data;

  removeItem = async (productId: number) =>
    (await this.http.delete<Envelope<CartDto>>(`/cart/items/${productId}`)).data.data;

  clear = async () => {
    await this.http.delete("/cart");
  };

  checkout = async () => (await this.http.post<Envelope<OrderDto>>("/cart/checkout")).data.data;
}

export class OrderApiDataSource {
  constructor(private readonly http: HttpClient) {}

  private params = (filter: OrderFilter) => ({
    status: filter.status,
    user_id: filter.userId,
    page: filter.page,
    per_page: filter.perPage,
  });

  listAll = async (filter: OrderFilter) =>
    (await this.http.get<PagedEnvelope<OrderDto>>("/orders", { params: this.params(filter) })).data;

  listMine = async (filter: OrderFilter) =>
    (await this.http.get<PagedEnvelope<OrderDto>>("/orders/my", { params: this.params({ ...filter, userId: undefined }) })).data;

  get = async (id: number) => (await this.http.get<Envelope<OrderDto>>(`/orders/${id}`)).data.data;

  place = async (lines: OrderLine[]) =>
    (
      await this.http.post<Envelope<OrderDto>>("/orders", {
        items: lines.map((line) => ({ product_id: line.productId, quantity: line.quantity })),
      })
    ).data.data;

  updateStatus = async (id: number, status: OrderStatus) =>
    (await this.http.put<Envelope<OrderDto>>(`/orders/${id}/status`, { status })).data.data;

  cancel = async (id: number) => (await this.http.post<Envelope<OrderDto>>(`/orders/${id}/cancel`)).data.data;

  remove = async (id: number) => {
    await this.http.delete(`/orders/${id}`);
  };
}

export class UserApiDataSource {
  constructor(private readonly http: HttpClient) {}

  list = async (filter: UserFilter) =>
    (
      await this.http.get<PagedEnvelope<UserDto>>("/users", {
        params: {
          search: filter.search,
          role: filter.role,
          is_active: filter.isActive,
          page: filter.page,
          per_page: filter.perPage,
        },
      })
    ).data;

  get = async (id: number) => (await this.http.get<Envelope<UserDto>>(`/users/${id}`)).data.data;

  create = async (draft: UserDraft) =>
    (
      await this.http.post<Envelope<UserDto>>("/users", {
        name: draft.name,
        email: draft.email,
        password: draft.password,
        is_active: draft.isActive,
        role: draft.role,
      })
    ).data.data;

  update = async (id: number, changes: Partial<UserDraft>) =>
    (
      await this.http.put<Envelope<UserDto>>(`/users/${id}`, {
        name: changes.name,
        email: changes.email,
        password: changes.password || undefined,
        is_active: changes.isActive,
      })
    ).data.data;

  remove = async (id: number) => {
    await this.http.delete(`/users/${id}`);
  };

  unlock = async (id: number) => (await this.http.post<Envelope<UserDto>>(`/users/${id}/unlock`)).data.data;

  assignRole = async (userId: number, roleId: number) =>
    (await this.http.post<Envelope<UserDto>>(`/users/${userId}/roles`, { role_id: roleId })).data.data;

  removeRole = async (userId: number, roleId: number) =>
    (await this.http.delete<Envelope<UserDto>>(`/users/${userId}/roles/${roleId}`)).data.data;
}

export class RoleApiDataSource {
  constructor(private readonly http: HttpClient) {}

  list = async () => (await this.http.get<Envelope<RoleDto[]>>("/roles")).data.data;

  create = async (name: string) => (await this.http.post<Envelope<RoleDto>>("/roles", { name })).data.data;

  update = async (id: number, name: string) =>
    (await this.http.put<Envelope<RoleDto>>(`/roles/${id}`, { name })).data.data;

  remove = async (id: number) => {
    await this.http.delete(`/roles/${id}`);
  };

  users = async (id: number, page: number) =>
    (await this.http.get<PagedEnvelope<UserDto>>(`/roles/${id}/users`, { params: { page } })).data;
}
