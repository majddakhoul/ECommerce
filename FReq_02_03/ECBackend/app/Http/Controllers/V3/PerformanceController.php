<?php

namespace App\Http\Controllers\V3;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\Performance\PerformanceLab;
use App\Support\AccessControl;
use App\Support\Performance\Flags;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PerformanceController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly PerformanceLab $lab) {}

    public function status(Request $request)
    {
        $this->ensureAdmin($request);

        return $this->success($this->lab->status());
    }

    public function updateFlags(Request $request)
    {
        $this->ensureAdmin($request);

        $rules = [];

        foreach (Flags::NAMES as $name) {
            $rules[$name] = ['sometimes', 'boolean'];
        }

        $validated = $request->validate($rules);

        return $this->success($this->lab->updateFlags($validated), 'Flags updated');
    }

    public function resetFlags(Request $request)
    {
        $this->ensureAdmin($request);

        return $this->success($this->lab->resetFlags(), 'Flags reset to defaults');
    }

    public function flushCache(Request $request)
    {
        $this->ensureAdmin($request);

        return $this->success(['version' => $this->lab->flushCache()], 'Cache flushed');
    }

    public function explain(Request $request)
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'scenario' => ['required', Rule::in(PerformanceLab::EXPLAIN_SCENARIOS)],
            'term' => ['sometimes', 'string', 'max:50'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'user_id' => ['sometimes', 'integer', 'min:1'],
        ]);

        return $this->success($this->lab->explain(
            $validated['scenario'],
            $validated['term'] ?? 'phone',
            $validated['category'] ?? null,
            (int) ($validated['user_id'] ?? 1)
        ));
    }

    public function compare(Request $request)
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'scenario' => ['required', Rule::in(PerformanceLab::SCENARIOS)],
            'runs' => ['sometimes', 'integer', 'min:1', 'max:' . config('performance.compare.max_runs')],
            'term' => ['sometimes', 'string', 'max:50'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        return $this->success($this->lab->compare(
            $validated['scenario'],
            (int) ($validated['runs'] ?? config('performance.compare.default_runs')),
            $validated['term'] ?? 'phone',
            $validated['category'] ?? null
        ));
    }

    private function ensureAdmin(Request $request): void
    {
        if (!$request->user()->hasRole(AccessControl::ADMIN_ROLE)) {
            throw ApiException::forbidden('Only administrators can use the performance lab.');
        }
    }
}
