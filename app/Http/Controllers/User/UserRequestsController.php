<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use App\Models\BorrowRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserRequestsController extends Controller
{
    public function index(): View
    {
        $userRequests = BorrowRequest::with(['equipment.category', 'equipment.department'])
            ->where('user_id', auth('user')->user()->user_id)
            ->orderByDesc('request_id')
            ->get();

        return view('user.requests', compact('userRequests'));
    }

    /**
     * Cancels the signed-in user's own request while it is still Pending.
     * Stock is only deducted when a request is fully approved, so a pending
     * request has nothing to give back. The row is locked so an approval
     * arriving at the same moment cannot slip past the check.
     */
    public function cancel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'request_id' => ['required', 'integer', 'min:1'],
        ]);

        $userId = auth('user')->user()->user_id;

        $result = DB::transaction(function () use ($request, $data, $userId) {
            $borrow = BorrowRequest::with('equipment')
                ->where('request_id', $data['request_id'])
                ->lockForUpdate()
                ->first();

            if (!$borrow || (int) $borrow->user_id !== (int) $userId) {
                return ['code' => 404, 'message' => 'Request not found.'];
            }

            if ($borrow->overall_status === BorrowRequest::STATUS_CANCELLED) {
                return ['code' => 400, 'message' => 'This request is already cancelled.'];
            }

            if (!$borrow->canBeCancelled()) {
                return ['code' => 400, 'message' => 'Only pending requests can be cancelled. This request is already ' . strtolower($borrow->overall_status) . '.'];
            }

            $borrow->update([
                'overall_status' => BorrowRequest::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            AuditTrail::create([
                'user_id' => $userId,
                'action' => mb_substr(
                    'Cancelled borrow request #' . $borrow->request_id . ' for ' . ($borrow->equipment?->name ?? 'equipment'),
                    0,
                    100
                ),
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'affected_entity' => 'borrow_request:' . $borrow->request_id,
            ]);

            return ['code' => 200];
        });

        if ($result['code'] !== 200) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['code']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Your request has been cancelled.',
        ]);
    }
}
