<?php

namespace App\Http\Controllers;

use App\DTOs\CreateProductDTO;
use App\DTOs\ProductFilterDTO;
use App\DTOs\UpdateProductDTO;
use App\Http\Requests\ListProductsRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use App\Traits\ApiResponse;

class ProductController extends Controller
{
    use ApiResponse;

    public function __construct(protected ProductService $productService) {}

    public function index(ListProductsRequest $request)
    {
        return $this->paginated(
            $this->productService->list(ProductFilterDTO::fromRequest($request)),
            ProductResource::class
        );
    }

    public function categories()
    {
        $this->authorize('viewAny', Product::class);

        return $this->success($this->productService->categories());
    }

    public function show(Product $product)
    {
        $this->authorize('view', $product);

        return $this->success(new ProductResource($product));
    }

    public function store(StoreProductRequest $request)
    {
        $product = $this->productService->create(CreateProductDTO::fromRequest($request));

        return $this->created(new ProductResource($product), 'Product created');
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $product = $this->productService->update($product, UpdateProductDTO::fromRequest($request));

        return $this->success(new ProductResource($product), 'Product updated');
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        $this->productService->delete($product);

        return $this->noContent();
    }
}
