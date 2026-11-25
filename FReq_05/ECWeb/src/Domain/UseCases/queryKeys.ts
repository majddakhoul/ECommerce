import type { OrderFilter } from "../Models/Order";
import type { ProductFilter } from "../Models/Product";
import type { UserFilter } from "../Models/User";

export const keys = {
  me: ["me"] as const,
  products: ["products"] as const,
  productList: (filter: ProductFilter) => ["products", "list", filter] as const,
  product: (id: number) => ["products", "detail", id] as const,
  categories: ["products", "categories"] as const,
  cart: ["cart"] as const,
  orders: ["orders"] as const,
  myOrders: (filter: OrderFilter) => ["orders", "mine", filter] as const,
  allOrders: (filter: OrderFilter) => ["orders", "all", filter] as const,
  order: (id: number) => ["orders", "detail", id] as const,
  users: ["users"] as const,
  userList: (filter: UserFilter) => ["users", "list", filter] as const,
  roles: ["roles"] as const,
  roleUsers: (id: number, page: number) => ["roles", id, "users", page] as const,
};
