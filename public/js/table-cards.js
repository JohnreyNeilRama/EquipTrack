// Department and Admin pages on phones: tables show each row as a card (see
// ccs/mobile-staff.css). This labels every cell with its column
// name (data-label) and marks the card's parts:
//   cell-head    first column (ID or name): the card's title
//   cell-status  the Status column: a badge next to the title
//   cell-action  the Action column: full-width buttons at the bottom
// Rows the page draws later (filters, paging, refresh) are labelled too.
// Desktop is unaffected: the attributes are only used inside the phone media
// query.
(function () {
    function label(table) {
        const heads = Array.from(table.querySelectorAll('thead th'))
            .map(th => th.textContent.replace(/\s+/g, ' ').trim());
        if (!heads.length) return;
        table.classList.add('cards-on-phone');

        table.querySelectorAll('tbody tr').forEach(tr => {
            Array.from(tr.children).forEach((cell, i) => {
                if (cell.colSpan > 1) {
                    tr.classList.add('is-message-row');
                    return;
                }
                const head = heads[i] || '';
                if (cell.dataset.label !== head) cell.dataset.label = head;
                cell.classList.toggle('cell-head', i === 0);
                cell.classList.toggle('cell-status', /^status$/i.test(head));
                // An "Action" column with buttons (not, e.g., the Audit
                // Trail's action-type badge)
                cell.classList.toggle('cell-action', /^actions?$/i.test(head) && !!cell.querySelector('button, a'));
            });
        });
    }

    document.querySelectorAll('.main-content table').forEach(table => {
        label(table);
        // Only added or removed rows and cells trigger it (not the labels
        // set here), so it can't loop.
        new MutationObserver(() => label(table)).observe(table, { childList: true, subtree: true });
    });
})();
