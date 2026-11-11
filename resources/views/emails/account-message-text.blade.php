{{-- Plain-text version of emails/account-message. Not HTML, so values are
     printed as-is ({!! !!}) to keep characters like ' and & readable. --}}
{!! $heading !!}

Hi {!! $name !!},

@foreach ($paragraphs as $paragraph)
{!! $paragraph !!}

@endforeach
@if (!empty($buttonUrl))
{!! $buttonLabel !!}:
{!! $buttonUrl !!}

@endif
@if (!empty($note))
{!! $note !!}

@endif
@if (!empty($footnote))
{!! $footnote !!}

@endif
--
This is an automated message from EquipTrack. Please don't reply to this email.
