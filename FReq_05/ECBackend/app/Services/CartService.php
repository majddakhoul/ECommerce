<?php

namespace App\Services;

use App\DTOs\CartItemDTO;
use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Repositories\Contracts\CartRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\Inventory\InventoryGatewayInterface;
use App\Services\Realtime\RealtimePublisher;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class CartService
{
    private const LOCK_PREFIX = 'cart:lock:';

    public function __construct(
        protected CartRepositoryInterface $cartRepo,
        protected ProductRepositoryInterface $productRepo,
        protected InventoryGatewayInterface $inventory,
        protected OrderService $orderService,
        protected RealtimePublisher $realtime
    ) {}

    public function view(User $user): array
    {
        $cart = $this->cartRepo->get($user->id);
        $products = $this->productRepo->findMany(array_keys($cart));
        $stocks = $this->inventory->getStocks(array_keys($cart));

        $items = [];
        $totalQuantity = 0;
        $totalPrice = 0;

        foreach ($cart as $productId => $quantity) {
            $product = $products->get($productId);

            if (!$product) {
                unset($cart[$productId]);

                continue;
            }

            $subtotal = $product->price * $quantity;
            $totalQuantity += $quantity;
            $totalPrice += $subtotal;

            $items[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'quantity' => $quantity,
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'available_stock' => $stocks[$product->id] ?? 0,
            ];
        }

        $this->cartRepo->put($user->id, $cart);

        return [
            'items' => $items,
            'total_quantity' => $totalQuantity,
            'total_price' => number_format($totalPrice, 2, '.', ''),
        ];
    }

    public function addItem(User $user, CartItemDTO $dto): array
    {
        return $this->exclusive($user, function () use ($user, $dto) {
            $product = $this->productRepo->findMany([$dto->productId])->get($dto->productId);

            if (!$product) {
                throw ApiException::notFound('Product not found.');
            }

            $cart = $this->cartRepo->get($user->id);
            $quantity = ($cart[$dto->productId] ?? 0) + $dto->quantity;

            $this->assertStock($product, $quantity);

            $cart[$dto->productId] = $quantity;
            $this->cartRepo->put($user->id, $cart);

            return $this->publish($user, $this->view($user));
        });
    }

    public function updateItem(User $user, Product $product, CartItemDTO $dto): array
    {
        return $this->exclusive($user, function () use ($user, $product, $dto) {
            $cart = $this->cartRepo->get($user->id);

            if (!isset($cart[$product->id])) {
                throw ApiException::notFound('The product is not in the cart.');
            }

            $this->assertStock($product, $dto->quantity);

            $cart[$product->id] = $dto->quantity;
            $this->cartRepo->put($user->id, $cart);

            return $this->publish($user, $this->view($user));
        });
    }

    public function removeItem(User $user, Product $product): array
    {
        return $this->exclusive($user, function () use ($user, $product) {
            $cart = $this->cartRepo->get($user->id);

            if (!isset($cart[$product->id])) {
                throw ApiException::notFound('The product is not in the cart.');
            }

            unset($cart[$product->id]);
            $this->cartRepo->put($user->id, $cart);

            return $this->publish($user, $this->view($user));
        });
    }

    public function clear(User $user): void
    {
        $this->exclusive($user, function () use ($user) {
            $this->cartRepo->forget($user->id);
            $this->realtime->emptyCart($user->id);
        });
    }

    public function checkout(User $user): Order
    {
        return $this->exclusive($user, function () use ($user) {
            $cart = $this->cartRepo->get($user->id);

            if ($cart === []) {
                throw ApiException::unprocessable('The cart is empty.');
            }

            $order = $this->orderService->placeItems($user, $cart);

            $this->cartRepo->forget($user->id);
            $this->realtime->emptyCart($user->id);

            return $order;
        });
    }

    private function exclusive(User $user, callable $callback): mixed
    {
        try {
            return Cache::lock(self::LOCK_PREFIX . $user->id, (int) config('api.cart_lock_seconds'))
                ->block((int) config('api.cart_lock_wait_seconds'), $callback);
        } catch (LockTimeoutException) {
            throw ApiException::conflict('The cart is being updated. Try again.');
        }
    }

    private function publish(User $user, array $view): array
    {
        $this->realtime->cartUpdated($user->id, $view);

        return $view;
    }

    private function assertStock(Product $product, int $quantity): void
    {
        $available = $this->inventory->getStock($product->id);

        if ($available < $quantity) {
            throw ApiException::conflict("Insufficient stock for '{$product->name}'. Available: {$available}.");
        }
    }
}
