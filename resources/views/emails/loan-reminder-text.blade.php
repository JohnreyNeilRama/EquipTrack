{{-- Plain-text version of the loan reminder email. Not HTML, so values are
     printed as-is ({!! !!}) to keep characters like ' and & readable. --}}
{{ $overdue ? 'Your borrowed equipment is overdue' : 'Your borrowed equipment is due soon' }}

Hi {!! $name !!},

@if ($overdue)
{!! $equipment !!} was due on {{ $dueDate }} and is now {{ $when }}. Please return it to the laboratory as soon as possible.
@else
This is a reminder that {!! $equipment !!} is {{ $when }} ({{ $dueDate }}). Please return it to the laboratory on time.
@endif

Equipment:   {!! $equipment !!}
Borrowed on: {{ $borrowDate }}
Due date:    {{ $dueDate }}{{ $overdue ? ' (' . $when . ')' : '' }}

@if ($overdue)
Late returns may be recorded as a penalty, and a pending penalty stops you from making new borrow requests.
@endif
When you hand the item back, submit the return on the Return Item page so the laboratory can confirm it:
{!! $returnsUrl !!}

Already handed it back? Submit the return on the Return Item page. Reminders stop once you have submitted it.

--
This is an automated message from EquipTrack. Please don't reply to this email.
