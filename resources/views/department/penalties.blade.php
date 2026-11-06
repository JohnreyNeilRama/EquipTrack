@extends('layouts.department')

@section('title', 'EquipTrack - Penalties')

@push('css')
@include('partials.penalties.styles')
<style>
    /* Search + filters card: ccs/filter-card.css; filter widths: partials/penalties/styles */

    /* Fixed column widths (same approach as the Release Equipment and Return
       Confirmation tables): the columns keep the same size whatever the rows
       hold, even when the table is empty. The short, predictable columns have
       set widths; User and Equipment share the rest equally. Text that doesn't
       fit ends in "…" (the full value is in the tooltip and the View form).
       Below the minimum width the table scrolls sideways inside its card. */
    /* .admin-table-card in the selector: it must beat the shared phone rule
       ".admin-table-card table { min-width: 500px }", which would otherwise
       squeeze User and Equipment to nothing on small screens. */
    .admin-table-card .penalties-table {
        table-layout: fixed;
        min-width: 1080px;
    }
    .penalties-table th:nth-child(1) { width: 120px; }   /* Penalty ID */
    .penalties-table th:nth-child(2),
    .penalties-table th:nth-child(3) { width: auto; min-width: 0; max-width: none; }  /* User, Equipment */
    .penalties-table th:nth-child(4) { width: 125px; }   /* Type */
    .penalties-table th:nth-child(5) { width: 115px; }   /* Amount */
    .penalties-table th:nth-child(6) { width: 130px; }   /* Recorded */
    .penalties-table th:nth-child(7) { width: 140px; }   /* Status */
    .penalties-table th:nth-child(8) { width: 140px; }   /* Action */
    .penalties-table th,
    .penalties-table td {
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .penalties-table td.col-user,
    .penalties-table td.col-equipment {
        max-width: none;
        min-width: 0;
    }
    .penalties-table .type-tag,
    .penalties-table .status-badge {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        vertical-align: middle;
    }
    /* The empty-table message spans every column and keeps its own layout */
    .penalties-table td.no-requests-message {
        white-space: normal;
        overflow: visible;
    }
    /* Keyboard focus on a row (Enter opens it, like a click) */
    .penalties-table tr.admin-table-row:focus-visible {
        outline: 2px solid #385585;
        outline-offset: -2px;
    }

    /* View button: partials/penalties/styles.blade.php (shared with Admin) */
</style>
@endpush

@section('content')
    @include('partials.penalties.page', ['party' => 'dept'])
@endsection

@push('scripts')
@include('partials.penalties.script', [
    'party' => 'dept',
    'urls' => [
        'data' => route('department.penalties.data'),
        'store' => route('department.penalties.store'),
        'update' => route('department.penalties.update'),
        'settle' => route('department.penalties.settle'),
        'waive' => route('department.penalties.waive'),
        'approveProof' => route('department.penalties.proof.approve'),
        'rejectProof' => route('department.penalties.proof.reject'),
    ],
])
<script>
    // Rows, as on the Release Equipment and Return Confirmation tables: a
    // click anywhere opens the penalty (the shared script handles that);
    // selecting text in a row does not, and Enter on a focused row does.
    (() => {
        const tableBody = document.getElementById('penaltiesTableBody');
        if (!tableBody) return;

        const makeFocusable = () => tableBody.querySelectorAll('tr.admin-table-row').forEach(tr => {
            if (tr.tabIndex !== 0) tr.tabIndex = 0;
        });
        new MutationObserver(makeFocusable).observe(tableBody, { childList: true });
        makeFocusable();

        // Runs before the row's own click handler, and stops it while text is selected.
        tableBody.addEventListener('click', (e) => {
            if (e.target.closest('button')) return;
            if (window.getSelection && String(window.getSelection()).trim() !== '') e.stopPropagation();
        }, true);

        tableBody.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter' || !e.target.matches('tr.admin-table-row')) return;
            e.preventDefault();
            e.target.click();
        });
    })();
</script>
@endpush
