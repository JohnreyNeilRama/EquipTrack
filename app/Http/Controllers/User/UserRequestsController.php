<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BorrowRequest;
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
}
