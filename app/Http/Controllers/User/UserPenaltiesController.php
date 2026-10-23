<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
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
 * My Penalties (Students / Faculty): the signed-in user's penalties and the
 * proof they submit that a penalty was paid, replaced or repaired outside the
 * system. Staff verify the proof on their Penalties page.
 *
 * Every query is scoped to the authenticated user.
 */
class UserPenaltiesController extends Controller
{
    public function __construct(
        private PenaltyService $penalties,
        private PenaltySubmissionService $submissions,
    ) {
    }

    public function index(): View
    {
        return view('user.penalties', [
            'dbPenalties' => $this->rows(),
            'limits' => [
                'maxFiles' => PenaltySubmission::MAX_FILES,
                'maxFileMb' => PenaltySubmission::MAX_FILE_KB / 1024,
                'mimes' => PenaltySubmission::FILE_MIMES,
            ],
        ]);
    }

    /** JSON feed for live refresh. */
    public function data(): JsonResponse
    {
        return response()->json(['success' => true, 'penalties' => $this->rows()]);
    }

    public function submit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'penalty_id' => ['required', 'integer', 'min:1'],
            'type' => ['required', Rule::in([PenaltySubmission::TYPE_PAYMENT, PenaltySubmission::TYPE_REPLACEMENT, PenaltySubmission::TYPE_REPAIR])],
            'reference_no' => ['nullable', 'required_if:type,' . PenaltySubmission::TYPE_PAYMENT, 'string', 'max:100'],
            'amount_paid' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'settled_on' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'files' => ['required', 'array', 'min:1', 'max:' . PenaltySubmission::MAX_FILES],
            // mimes checks the file's real content, not only its name.
            'files.*' => ['required', 'file', 'max:' . PenaltySubmission::MAX_FILE_KB, 'mimes:' . implode(',', PenaltySubmission::FILE_MIMES)],
        ], [
            'reference_no.required_if' => 'Please enter the official receipt or reference number.',
            'settled_on.before_or_equal' => 'The date cannot be in the future.',
            'files.required' => 'Please attach at least one photo or receipt.',
            'files.max' => 'You can attach up to ' . PenaltySubmission::MAX_FILES . ' files.',
            'files.*.max' => 'Each file must be 5 MB or smaller.',
            'files.*.mimes' => 'Only JPG, PNG, WEBP or PDF files are accepted.',
        ]);

        $user = auth('user')->user();
        $penalty = Penalty::where('user_id', $user->user_id)->find($data['penalty_id']);

        if (!$penalty) {
            return response()->json(['success' => false, 'message' => 'Penalty not found.'], 404);
        }

        $result = $this->submissions->submit($penalty, $user, $data, $request->file('files', []));

        if (is_string($result)) {
            return response()->json(['success' => false, 'message' => $result], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Your proof was submitted. Staff will verify it; borrowing stays on hold until it is approved.',
            'penalties' => $this->rows(),
        ]);
    }

    /** One of the user's own proof files (shown inline). */
    public function file(int $file): Response
    {
        $record = PenaltySubmissionFile::whereKey($file)
            ->whereHas('submission', fn ($query) => $query->where('user_id', auth('user')->user()->user_id))
            ->firstOrFail();

        abort_unless(Storage::disk('local')->exists($record->path), 404);

        return Storage::disk('local')->response($record->path, $record->original_name, [
            'Content-Type' => $record->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    private function rows(): array
    {
        return $this->penalties->rows($this->scope(), 'user.penalties.file');
    }

    private function scope(): Builder
    {
        return Penalty::query()->where('user_id', auth('user')->user()->user_id);
    }
}
