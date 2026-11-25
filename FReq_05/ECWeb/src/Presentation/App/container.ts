import {
  AuthApiDataSource,
  CartApiDataSource,
  OrderApiDataSource,
  ProductApiDataSource,
  RoleApiDataSource,
  UserApiDataSource,
} from "../../Data/DataSources/ApiDataSources";
import { EchoRealtimeGateway } from "../../Data/Realtime/EchoRealtimeGateway";
import { NullRealtimeGateway } from "../../Data/Realtime/NullRealtimeGateway";
import {
  AuthRepositoryImpl,
  CartRepositoryImpl,
  OrderRepositoryImpl,
  ProductRepositoryImpl,
  RoleRepositoryImpl,
  UserRepositoryImpl,
} from "../../Data/Repositories/Repositories";
import { BenchmarkRepositoryImpl } from "../../Data/Repositories/BenchmarkRepositoryImpl";
import type { Dependencies } from "../../Domain/UseCases/Dependencies";
import type { RealtimeGateway } from "../../Domain/Repositories/RealtimeGateway";
import type { AppConfig } from "../../Services/Config";
import { createHttp } from "../../Services/Http";
import { sessionEvents } from "../../Services/SessionEvents";
import { BrowserTokenStore } from "../../Services/TokenStorage";

export const buildDependencies = (config: AppConfig): Dependencies => {
  const tokens = new BrowserTokenStore();
  const http = createHttp({
    baseURL: config.apiBase,
    tokens,
    onUnauthorized: () => sessionEvents.emitUnauthorized(),
  });

  const realtime: RealtimeGateway = config.realtimeEnabled
    ? new EchoRealtimeGateway(config.reverb, async (socketId, channelName) => {
        const response = await http.post<unknown>(
          "/broadcasting/auth",
          { socket_id: socketId, channel_name: channelName },
          { baseURL: `${config.apiOrigin}/api` },
        );
        return response.data;
      })
    : new NullRealtimeGateway();

  return {
    mode: config.apiMode,
    auth: new AuthRepositoryImpl(new AuthApiDataSource(http), tokens),
    products: new ProductRepositoryImpl(new ProductApiDataSource(http)),
    cart: new CartRepositoryImpl(new CartApiDataSource(http)),
    orders: new OrderRepositoryImpl(new OrderApiDataSource(http)),
    users: new UserRepositoryImpl(new UserApiDataSource(http)),
    roles: new RoleRepositoryImpl(new RoleApiDataSource(http)),
    realtime,
    benchmark: new BenchmarkRepositoryImpl(http, config.apiOrigin),
    hasToken: () => tokens.get() !== null,
    clearToken: () => tokens.clear(),
  };
};
