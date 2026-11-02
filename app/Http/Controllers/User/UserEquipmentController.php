<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Penalty;
use App\Services\EquipmentAvailability;
use App\Support\LoanTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class UserEquipmentController extends Controller
{
    /** Borrowing types on the request form. */
    public const WITHIN_THE_DAY = 'within_day';

    public const MULTIPLE_DAYS = 'multiple_days';

    public function index(): View
    {
        $dbCategories = EquipmentCategory::orderBy('category_name')->get();

        $dbEquipment = Equipment::with(['category', 'department'])
            ->orderByDesc('equipment_id')
            ->get();

        // Units each item can lend and the dates when none is free (the
        // request form's "Fully booked" line), in one query for all items.
        $availability = collect(EquipmentAvailability::forMany($dbEquipment))
            ->map(fn (EquipmentAvailability $a) => [
                'lendable' => $a->lendableUnits(),
                'fullyBooked' => $a->fullyBookedRanges(),
            ])
            ->all();

        $categoryCounts = [];
        foreach ($dbEquipment as $eq) {
            $cName = $eq->category?->category_name ?: 'Others';
            $categoryCounts[$cName] = ($categoryCounts[$cName] ?? 0) + 1;
        }

        // Faculty members are the instructors themselves, so their form has no
        // Instructor Name field (see store()).
        $askInstructor = auth('user')->user()->role !== 'Faculty';

        // A Pending penalty blocks new requests; the page explains why.
        $blocking = Penalty::blockingBorrowFor((int) auth('user')->user()->user_id);
        $penaltyBlock = $blocking?->borrowBlockMessage();
        // Where the user submits proof for that penalty.
        $penaltyBlockUrl = $blocking ? route('user.penalties', ['penalty' => $blocking->penalty_id]) : null;

        return view('user.equipment', compact('dbCategories', 'dbEquipment', 'availability', 'categoryCounts', 'askInstructor', 'penaltyBlock', 'penaltyBlockUrl'));
    }

    /**
     * Mirrors the legacy submit_request endpoint's validation order, with the
     * same JSON shape the modal JS expects.
     */
    public function store(Request $request): JsonResponse
    {
        // Students name their instructor; faculty members don't (they are the
        // instructors), so for them the field is neither required nor saved.
        $askInstructor = auth('user')->user()->role !== 'Faculty';

        // "Today" in Philippine time: the app clock runs in UTC, which is still
        // the previous day before 8:00 AM here.
        $today = now(config('app.display_timezone'))->toDateString();

        // Within the Day: returned on the borrow date, so the return date is
        // the borrow date. Multiple Days: a later return date. Without a type,
        // the dates decide (the same date is Within the Day).
        $type = $request->input('borrow_type');
        if ($type === self::WITHIN_THE_DAY) {
            $request->merge(['return_date' => $request->input('borrow_date')]);
        }

        $validator = Validator::make($request->all(), [
            'equipment_id' => ['required', 'integer', 'min:1'],
            'borrow_type' => ['nullable', 'in:' . self::WITHIN_THE_DAY . ',' . self::MULTIPLE_DAYS],
            'borrow_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:' . $today],
            'return_date' => ['required', 'date_format:Y-m-d',
                $type === self::MULTIPLE_DAYS ? 'after:borrow_date' : 'after_or_equal:borrow_date'],
            'purpose' => ['required', 'string', 'max:255'],
            'instructor_name' => [$askInstructor ? 'required' : 'nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'borrow_type.in' => 'Please choose Within the Day or Multiple Days.',
            'borrow_date.after_or_equal' => 'The borrow date cannot be in the past.',
            'return_date.required' => 'Please choose the return date.',
            'return_date.after_or_equal' => 'Return date cannot be earlier than borrow date.',
            'return_date.after' => 'For Multiple Days, the return date must be after the borrow date.',
            'instructor_name.required' => "Please enter your instructor's name.",
            'instructor_name.max' => "The instructor's name may not be longer than 150 characters.",
        ]);

        // Once today's return deadline has passed the office is closed for
        // the day, so nothing can be borrowed today any more.
        $validator->after(function ($validator) use ($request, $today) {
            if ($validator->errors()->isEmpty() && $request->input('borrow_date') === $today && LoanTime::deadlinePassedToday()) {
                $validator->errors()->add('borrow_date', "Today's return deadline (" . LoanTime::clock(LoanTime::deadline())
                    . ') has passed. Please choose tomorrow or a later date.');
            }
        });

        $data = $validator->validate();
        [$from, $to] = $this->period($data);

        $userId = auth('user')->user()->user_id;

        $instructorName = $askInstructor ? trim($data['instructor_name']) : null;

        // An unsettled penalty blocks new requests (config equiptrack.block_borrowing_with_pending_penalty).
        if ($blocking = Penalty::blockingBorrowFor((int) $userId)) {
            return response()->json(['success' => false, 'message' => $blocking->borrowBlockMessage()], 403);
        }

        $result = DB::transaction(function () use ($request, $data, $userId, $instructorName, $from, $to) {
            $equipment = Equipment::where('equipment_id', $data['equipment_id'])
                ->lockForUpdate()
                ->first();

            if (!$equipment) {
                return ['code' => 404, 'message' => 'The selected equipment was not found in the database.'];
            }

            // The admin's status decides whether the item can be requested at
            // all; the dates decide whether a unit is free.
            if (strtolower((string) $equipment->status) !== 'available') {
                return ['code' => 400, 'message' => 'Sorry, ' . $equipment->name . ' is currently ' . strtolower((string) $equipment->status) . ' and cannot be requested.'];
            }

            $availability = EquipmentAvailability::for($equipment);

            if ($availability->lendableUnits() < 1) {
                return ['code' => 400, 'message' => 'Sorry, ' . $equipment->name . ' has no units that can be lent right now.'];
            }

            if (!$availability->isFree($from, $to)) {
                return ['code' => 400, 'message' => $this->fullyBookedMessage($equipment, $availability, $from, $to)];
            }

            $classification = $equipment->classificationLabel();

            $borrow = BorrowRequest::create([
                'user_id' => $userId,
                'equipment_id' => $equipment->equipment_id,
                'quantity' => 1,
                // Read from the locked equipment row: this decides whether the
                // request needs one approval or both the admin's and the department's.
                'equipment_classification' => $classification,
                'purpose' => $data['purpose'],
                'instructor_name' => $instructorName,
                'notes' => $data['notes'] ?? null,
                'date_needed' => $from->format('Y-m-d H:i:s'),
                'borrow_date' => $from->format('Y-m-d H:i:s'),
                'return_date' => $to->format('Y-m-d H:i:s'),
                'due_date' => $to->format('Y-m-d H:i:s'),
            ]);

            // Legacy code inserted audit rows with columns that don't exist on the
            // live table (timestamp/missing ip_address); write to the real schema.
            AuditTrail::create([
                'user_id' => $userId,
                'action' => 'Submitted borrow request #' . $borrow->request_id . ' for ' . $equipment->name,
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'affected_entity' => 'borrow_request:' . $borrow->request_id,
            ]);

            return [
                'code' => 200,
                'success' => true,
                'request_id' => $borrow->request_id,
                'dual' => $classification === Equipment::CLASS_EXPENSIVE,
            ];
        });

        if (($result['code'] ?? 200) !== 200) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['code'] === 404 ? 404 : 400);
        }

        return response()->json([
            'success' => true,
            'message' => !empty($result['dual'])
                ? 'Your borrowing request has been submitted successfully! This equipment needs approval from both the admin and the department.'
                : 'Your borrowing request has been submitted successfully!',
            'request_id' => $result['request_id'],
            'redirect' => route('user.requests'),
        ]);
    }

    /**
     * Borrow and due moments from the form's dates: the borrow date (any time
     * during office hours) and the due date at the return deadline.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(array $input): array
    {
        return [Carbon::parse($input['borrow_date']), LoanTime::dueOn($input['return_date'])];
    }

    /** "Epson Projector is fully booked on Nov 5–7. Please choose other dates." */
    private function fullyBookedMessage(Equipment $equipment, EquipmentAvailability $availability, Carbon $from, Carbon $to): string
    {
        $ranges = $availability->fullyBookedWithin($from, $to);

        return 'Sorry, ' . $equipment->name . ' is fully booked'
            . ($ranges ? ' on ' . implode(', ', array_column($ranges, 'label')) : ' on some of these dates')
            . '. Please choose other dates.';
    }
}
