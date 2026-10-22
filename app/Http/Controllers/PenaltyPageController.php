<?php

namespace App\Http\Controllers;

use App\Models\Penalty;
use App\Services\PenaltyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Penalties page for the reviewers (Admin, Lab Personnel). Subclasses say who
 * is signed in and which penalties they may see; listing, editing, settling
 * and waiving live here and in PenaltyService.
 */
abstract class PenaltyPageController extends Controller
{
    public function __construct(protected PenaltyService $penalties)
    {
    }

    /** Penalties this account may see and act on. */
    abstract protected function scope(): Builder;

    /** AuditLogger actor of the signed-in account. */
    abstract protected function actor(): array;

    abstract protected function view(): string;

    /** Extra data for the page (the admin's Add Penalty list). */
    protected function pageData(): array
    {
        return [];
    }

    public function index(): View
    {
        return view($this->view(), ['dbPenalties' => $this->penalties->rows($this->scope())] + $this->pageData());
    }

    /** JSON feed for live refresh. */
    public function data(): JsonResponse
    {
        return $this->feed();
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'penalty_id' => ['required', 'integer', 'min:1'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'description' => ['required', 'string', 'max:1000'],
        ]);

        return $this->act($data['penalty_id'], fn (Penalty $p) => $this->penalties->update($p, $data, $this->actor()), 'Penalty updated.');
    }

    public function settle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'penalty_id' => ['required', 'integer', 'min:1'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->act(
            $data['penalty_id'],
            fn (Penalty $p) => $this->penalties->settle($p, $data['reference_no'] ?? null, $data['remarks'] ?? null, $this->actor()),
            'Penalty marked as settled.',
        );
    }

    public function waive(Request $request): JsonResponse
    {
        $data = $request->validate([
            'penalty_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Please state why the penalty is waived.',
        ]);

        return $this->act($data['penalty_id'], fn (Penalty $p) => $this->penalties->waive($p, $data['reason'], $this->actor()), 'Penalty waived.');
    }

    /** Runs $apply on a penalty in scope; $apply returns an error message or null. */
    protected function act(int $penaltyId, callable $apply, string $message): JsonResponse
    {
        $penalty = $this->scope()->whereKey($penaltyId)->first();

        if (!$penalty) {
            return response()->json(['success' => false, 'message' => 'Penalty not found.'], 404);
        }

        if ($error = $apply($penalty)) {
            return response()->json(['success' => false, 'message' => $error], 400);
        }

        return $this->feed($message);
    }

    protected function feed(?string $message = null): JsonResponse
    {
        return response()->json(array_filter([
            'success' => true,
            'message' => $message,
            'penalties' => $this->penalties->rows($this->scope()),
        ] + $this->pageData(), fn ($value) => $value !== null));
    }
}
