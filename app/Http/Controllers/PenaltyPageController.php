<?php

namespace App\Http\Controllers;

use App\Models\BorrowTransaction;
use App\Models\Penalty;
use App\Models\PenaltySubmission;
use App\Models\PenaltySubmissionFile;
use App\Services\PenaltyService;
use App\Services\PenaltySubmissionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penalties page for the reviewers (Admin, Lab Personnel). Subclasses say who
 * is signed in and which penalties and returned loans they may see; listing,
 * recording, editing, settling, waiving and verifying the users' proof live
 * here, in PenaltyService and in PenaltySubmissionService.
 */
abstract class PenaltyPageController extends Controller
{
    public function __construct(
        protected PenaltyService $penalties,
        protected PenaltySubmissionService $submissions,
    ) {
    }

    /** Penalties this account may see and act on. */
    abstract protected function scope(): Builder;

    /** AuditLogger actor of the signed-in account. */
    abstract protected function actor(): array;

    abstract protected function view(): string;

    /** Route that serves a proof file to this account (it checks the scope). */
    abstract protected function proofFileRoute(): string;

    /** Borrow transactions this account may record a penalty on (Add Penalty). */
    abstract protected function loanScope(): Builder;

    /** Extra data for the page: the returned loans for Add Penalty. */
    protected function pageData(): array
    {
        return ['returnedLoans' => $this->penalties->returnedLoans($this->loanScope())];
    }

    public function index(): View
    {
        return view($this->view(), ['dbPenalties' => $this->rows()] + $this->pageData());
    }

    /** JSON feed for live refresh. */
    public function data(): JsonResponse
    {
        return $this->feed();
    }

    /**
     * Add Penalty: records a penalty on a returned loan in this account's scope,
     * for example damage found after the return was confirmed. PenaltyService
     * allows it only on returned loans and only once per type.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'transaction_id' => ['required', 'integer', 'min:1'],
            'type' => ['required', Rule::in(Penalty::TYPES)],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'description' => ['required', 'string', 'max:1000'],
        ]);

        $transaction = $this->loanScope()->with('request')->find($data['transaction_id']);

        if (!$transaction instanceof BorrowTransaction || !$transaction->request) {
            return response()->json(['success' => false, 'message' => 'Returned loan not found.'], 404);
        }

        $result = $this->penalties->record($transaction, $data, $this->actor());

        if (is_string($result)) {
            return response()->json(['success' => false, 'message' => $result], 400);
        }

        return $this->feed('Penalty recorded.');
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

    /** Approves the proof under review and settles the penalty. */
    public function approveProof(Request $request): JsonResponse
    {
        $data = $request->validate([
            'penalty_id' => ['required', 'integer', 'min:1'],
            'item_received' => ['nullable', 'boolean'],
        ]);

        return $this->act($data['penalty_id'], function (Penalty $penalty) use ($data) {
            $submission = $this->submissionUnderReview($penalty);

            return $submission
                ? $this->submissions->approve($submission, (bool) ($data['item_received'] ?? false), $this->actor())
                : 'There is no proof waiting for review.';
        }, 'Proof approved. The penalty is settled.');
    }

    /** Rejects the proof under review; the user can submit again. */
    public function rejectProof(Request $request): JsonResponse
    {
        $data = $request->validate([
            'penalty_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Please tell the user why the proof was rejected.',
        ]);

        return $this->act($data['penalty_id'], function (Penalty $penalty) use ($data) {
            $submission = $this->submissionUnderReview($penalty);

            return $submission
                ? $this->submissions->reject($submission, $data['reason'], $this->actor())
                : 'There is no proof waiting for review.';
        }, 'Proof rejected. The user was asked to submit again.');
    }

    /** A proof file of a penalty in this account's scope (shown inline). */
    public function proofFile(int $file): Response
    {
        $penaltyIds = $this->scope()->pluck('penalty_id');

        $record = PenaltySubmissionFile::whereKey($file)
            ->whereHas('submission', fn ($query) => $query->whereIn('penalty_id', $penaltyIds))
            ->firstOrFail();

        abort_unless(Storage::disk('local')->exists($record->path), 404);

        return Storage::disk('local')->response($record->path, $record->original_name, [
            'Content-Type' => $record->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    private function submissionUnderReview(Penalty $penalty): ?PenaltySubmission
    {
        return $penalty->submissions()->where('status', PenaltySubmission::STATUS_REVIEW)->first();
    }

    protected function rows(): array
    {
        return $this->penalties->rows($this->scope(), $this->proofFileRoute());
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
            'penalties' => $this->rows(),
        ] + $this->pageData(), fn ($value) => $value !== null));
    }
}
