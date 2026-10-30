<?php

namespace App\Services;

use App\DTOs\CartItemDTO;
use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Repositories\Contracts\CartRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;

class CartService
{
    public function __construct(
        protected CartRepositoryInterface $cartRepo,
        protected ProductRepositoryInterface $productRepo,
        protected OrderService $orderService
    ) {}

    public function view(User $user): array
    {
        $cart = $this->cartRepo->get($user->id);
        $products = $this->productRepo->findMany(array_keys($cart));

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
                'available_stock' => $product->stock,
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
        $product = $this->productRepo->findMany([$dto->productId])->get($dto->productId);

        if (!$product) {
            throw ApiException::notFound('Product not found.');
        }

        $cart = $this->cartRepo->get($user->id);
        $quantity = ($cart[$dto->productId] ?? 0) + $dto->quantity;

        $this->assertStock($product, $quantity);

        $cart[$dto->productId] = $quantity;
        $this->cartRepo->put($user->id, $cart);

        return $this->view($user);
    }

    public function updateItem(User $user, Product $product, CartItemDTO $dto): array
    {
        $cart = $this->cartRepo->get($user->id);

        if (!isset($cart[$product->id])) {
            throw ApiException::notFound('The product is not in the cart.');
        }

        $this->assertStock($product, $dto->quantity);

        $cart[$product->id] = $dto->quantity;
        $this->cartRepo->put($user->id, $cart);

        return $this->view($user);
    }

    public function removeItem(User $user, Product $product): array
    {
        $cart = $this->cartRepo->get($user->id);

        if (!isset($cart[$product->id])) {
            throw ApiException::notFound('The product is not in the cart.');
        }

        unset($cart[$product->id]);
        $this->cartRepo->put($user->id, $cart);

        return $this->view($user);
    }

    public function clear(User $user): void
    {
        $this->cartRepo->forget($user->id);
    }

    public function checkout(User $user): Order
    {
        $cart = $this->cartRepo->get($user->id);

        if ($cart === []) {
            throw ApiException::unprocessable('The cart is empty.');
        }

        $order = $this->orderService->placeItems($user, $cart);

        $this->cartRepo->forget($user->id);

        return $order;
    }

    private function assertStock(Product $product, int $quantity): void
    {
        if ($product->stock < $quantity) {
            throw ApiException::conflict("Insufficient stock for '{$product->name}'. Available: {$product->stock}.");
        }
    }
}
