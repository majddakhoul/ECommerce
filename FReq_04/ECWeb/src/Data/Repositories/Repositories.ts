import type { Cart } from "../../Domain/Models/Cart";
import type { Order, OrderFilter, OrderLine, OrderStatus } from "../../Domain/Models/Order";
import type { Page } from "../../Domain/Models/Pagination";
import type { Product, ProductDraft, ProductFilter } from "../../Domain/Models/Product";
import type { Role, Session, User, UserDraft, UserFilter, Credentials, Registration } from "../../Domain/Models/User";
import type { AuthRepository, PasswordChange, ProfileChanges } from "../../Domain/Repositories/AuthRepository";
import type { CartRepository } from "../../Domain/Repositories/CartRepository";
import type { OrderRepository } from "../../Domain/Repositories/OrderRepository";
import type { ProductRepository } from "../../Domain/Repositories/ProductRepository";
import type { RoleRepository, UserRepository } from "../../Domain/Repositories/UserRepository";
import type { TokenStore } from "../../Services/TokenStorage";
import type {
  AuthApiDataSource,
  CartApiDataSource,
  OrderApiDataSource,
  ProductApiDataSource,
  RoleApiDataSource,
  UserApiDataSource,
} from "../DataSources/ApiDataSources";
import { toCart, toOrder, toPage, toProduct, toRole, toSession, toUser } from "../Mappers/mappers";

export class AuthRepositoryImpl implements AuthRepository {
  constructor(
    private readonly source: AuthApiDataSource,
    private readonly tokens: TokenStore,
  ) {}

  private remember(session: Session): Session {
    this.tokens.set(session.accessToken);
    return session;
  }

  login = async (credentials: Credentials) => this.remember(toSession(await this.source.login(credentials)));

  register = async (registration: Registration) => this.remember(toSession(await this.source.register(registration)));

  me = async (): Promise<User> => toUser(await this.source.me());

  updateProfile = async (changes: ProfileChanges): Promise<User> => toUser(await this.source.updateProfile(changes));

  changePassword = async (change: PasswordChange) => this.remember(toSession(await this.source.changePassword(change)));

  refresh = async () => this.remember(toSession(await this.source.refresh()));

  logout = async (): Promise<void> => {
    try {
      await this.source.logout();
    } finally {
      this.tokens.clear();
    }
  };
}

export class ProductRepositoryImpl implements ProductRepository {
  constructor(private readonly source: ProductApiDataSource) {}

  list = async (filter: ProductFilter): Promise<Page<Product>> => {
    const body = await this.source.list(filter);
    return toPage(body.data, body.meta, toProduct);
  };

  get = async (id: number) => toProduct(await this.source.get(id));

  categories = async () => (await this.source.categories()).filter((name): name is string => Boolean(name));

  create = async (draft: ProductDraft) => toProduct(await this.source.create(draft));

  update = async (id: number, draft: Partial<ProductDraft>) => toProduct(await this.source.update(id, draft));

  remove = (id: number) => this.source.remove(id);
}

export class CartRepositoryImpl implements CartRepository {
  constructor(private readonly source: CartApiDataSource) {}

  get = async (): Promise<Cart> => toCart(await this.source.get());

  addItem = async (productId: number, quantity: number) => toCart(await this.source.addItem(productId, quantity));

  updateItem = async (productId: number, quantity: number) => toCart(await this.source.updateItem(productId, quantity));

  removeItem = async (productId: number) => toCart(await this.source.removeItem(productId));

  clear = () => this.source.clear();

  checkout = async (): Promise<Order> => toOrder(await this.source.checkout());
}

export class OrderRepositoryImpl implements OrderRepository {
  constructor(private readonly source: OrderApiDataSource) {}

  listAll = async (filter: OrderFilter): Promise<Page<Order>> => {
    const body = await this.source.listAll(filter);
    return toPage(body.data, body.meta, toOrder);
  };

  listMine = async (filter: OrderFilter): Promise<Page<Order>> => {
    const body = await this.source.listMine(filter);
    return toPage(body.data, body.meta, toOrder);
  };

  get = async (id: number) => toOrder(await this.source.get(id));

  place = async (lines: OrderLine[]) => toOrder(await this.source.place(lines));

  updateStatus = async (id: number, status: OrderStatus) => toOrder(await this.source.updateStatus(id, status));

  cancel = async (id: number) => toOrder(await this.source.cancel(id));

  remove = (id: number) => this.source.remove(id);
}

export class UserRepositoryImpl implements UserRepository {
  constructor(private readonly source: UserApiDataSource) {}

  list = async (filter: UserFilter): Promise<Page<User>> => {
    const body = await this.source.list(filter);
    return toPage(body.data, body.meta, toUser);
  };

  get = async (id: number) => toUser(await this.source.get(id));

  create = async (draft: UserDraft) => toUser(await this.source.create(draft));

  update = async (id: number, changes: Partial<UserDraft>) => toUser(await this.source.update(id, changes));

  remove = (id: number) => this.source.remove(id);

  unlock = async (id: number) => toUser(await this.source.unlock(id));

  assignRole = async (userId: number, roleId: number) => toUser(await this.source.assignRole(userId, roleId));

  removeRole = async (userId: number, roleId: number) => toUser(await this.source.removeRole(userId, roleId));
}

export class RoleRepositoryImpl implements RoleRepository {
  constructor(private readonly source: RoleApiDataSource) {}

  list = async (): Promise<Role[]> => (await this.source.list()).map(toRole);

  create = async (name: string) => toRole(await this.source.create(name));

  update = async (id: number, name: string) => toRole(await this.source.update(id, name));

  remove = (id: number) => this.source.remove(id);

  users = async (id: number, page: number): Promise<Page<User>> => {
    const body = await this.source.users(id, page);
    return toPage(body.data, body.meta, toUser);
  };
}
