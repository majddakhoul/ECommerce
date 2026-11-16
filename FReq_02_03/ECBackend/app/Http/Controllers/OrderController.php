<?php

namespace App\Http\Controllers;

use App\DTOs\OrderFilterDTO;
use App\DTOs\PlaceOrderDTO;
use App\DTOs\UpdateOrderStatusDTO;
use App\Http\Requests\ListMyOrdersRequest;
use App\Http\Requests\ListOrdersRequest;
use App\Http\Requests\PlaceOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Traits\ApiResponse;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(protected OrderService $orderService) {}

    public function index(ListOrdersRequest $request)
    {
        return $this->paginated(
            $this->orderService->list(OrderFilterDTO::fromRequest($request)),
            OrderResource::class
        );
    }

    public function mine(ListMyOrdersRequest $request)
    {
        return $this->paginated(
            $this->orderService->list(OrderFilterDTO::fromRequest($request, $request->user()->id)),
            OrderResource::class
        );
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        return $this->success(new OrderResource($this->orderService->show($order)));
    }

    public function store(PlaceOrderRequest $request)
    {
        $order = $this->orderService->place($request->user(), PlaceOrderDTO::fromRequest($request));

        return $this->created(new OrderResource($order), 'Order placed');
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order)
    {
        $order = $this->orderService->updateStatus($order, UpdateOrderStatusDTO::fromRequest($request));

        return $this->success(new OrderResource($order), 'Order status updated');
    }

    public function cancel(Order $order)
    {
        $this->authorize('cancel', $order);

        return $this->success(new OrderResource($this->orderService->cancel($order)), 'Order cancelled');
    }

    public function destroy(Order $order)
    {
        $this->authorize('delete', $order);

        $this->orderService->delete($order);

        return $this->noContent();
    }
}
