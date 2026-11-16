<?php

namespace App\Http\Controllers\V3;

use App\DTOs\ProductFilterDTO;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListProductsRequest;
use App\Http\Resources\ProductResource;
use App\Services\CatalogService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CatalogService $catalog) {}

    public function index(ListProductsRequest $request)
    {
        $filter = ProductFilterDTO::fromRequest($request);
        $page = max(1, (int) $request->query('page', 1));

        $result = $this->catalog->list($filter, $page);

        $this->annotate($result['cache'], $result['source']);

        $paginator = new LengthAwarePaginator(
            $result['products'],
            $result['total'],
            $filter->perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $this->paginated($paginator, ProductResource::class);
    }

    public function show(Request $request, string $product)
    {
        $found = ctype_digit($product) ? $this->catalog->find((int) $product) : null;

        if ($found === null) {
            throw ApiException::notFound('Product not found.');
        }

        $this->authorize('view', $found['product']);

        $this->annotate($found['cache'], $found['source']);

        return $this->success(new ProductResource($found['product']));
    }

    private function annotate(string $cache, string $source): void
    {
        $current = request();
        $current->attributes->set('perf.cache', $cache);
        $current->attributes->set('perf.source', $source);
    }
}
