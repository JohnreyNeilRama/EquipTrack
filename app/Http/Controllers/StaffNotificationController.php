<?php

namespace App\Http\Controllers;

use App\Services\ReviewerNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notification bell for the reviewers (Admin, Lab Personnel). The subclasses
 * only say who is signed in; the feed itself lives in ReviewerNotificationService.
 */
abstract class StaffNotificationController extends Controller
{
    /**
     * @return array{0: string, 1: int, 2: ?int} account type, account id, department id
     */
    abstract protected function viewer(): array;

    public function index(ReviewerNotificationService $service): JsonResponse
    {
        [$type, $accountId, $departmentId] = $this->viewer();

        return response()->json(['success' => true] + $service->feed($type, $accountId, $departmentId));
    }

    public function markRead(Request $request, ReviewerNotificationService $service): JsonResponse
    {
        $data = $request->validate([
            'all' => ['nullable', 'boolean'],
            'keys' => ['nullable', 'array', 'max:100'],
            'keys.*' => ['string', 'max:100'],
        ]);

        [$type, $accountId, $departmentId] = $this->viewer();

        $result = $service->markRead($type, $accountId, $departmentId, $data['keys'] ?? [], !empty($data['all']));

        if (!$result['saved']) {
            return response()->json([
                'success' => false,
                'message' => 'Read state is not available yet. Please run the database migration.',
            ], 503);
        }

        return response()->json(['success' => true, 'unreadCount' => $result['unreadCount']]);
    }
}
