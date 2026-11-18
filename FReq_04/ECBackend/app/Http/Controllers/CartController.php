<?php

namespace App\Http\Controllers;

use App\DTOs\CartItemDTO;
use App\Http\Requests\AddCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\OrderResource;
use App\Models\Product;
use App\Services\CartService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    use ApiResponse;

    public function __construct(protected CartService $cartService) {}

    public function index(Request $request)
    {
        return $this->success($this->cartService->view($request->user()));
    }

    public function addItem(AddCartItemRequest $request)
    {
        return $this->success(
            $this->cartService->addItem($request->user(), CartItemDTO::fromRequest($request)),
            'Item added to the cart'
        );
    }

    public function updateItem(UpdateCartItemRequest $request, Product $product)
    {
        return $this->success(
            $this->cartService->updateItem($request->user(), $product, CartItemDTO::fromRequest($request, $product->id)),
            'Cart item updated'
        );
    }

    public function removeItem(Request $request, Product $product)
    {
        return $this->success(
            $this->cartService->removeItem($request->user(), $product),
            'Item removed from the cart'
        );
    }

    public function clear(Request $request)
    {
        $this->cartService->clear($request->user());

        return $this->noContent();
    }

    public function checkout(Request $request)
    {
        $order = $this->cartService->checkout($request->user());

        return $this->created(new OrderResource($order), 'Order placed');
    }
}
