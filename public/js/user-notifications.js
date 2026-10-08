// Notification bell for Students / Faculty.
// Loads the signed-in user's notifications from the server (request status and
// overdue reminders), shows an unread badge, and records what has been read.
// Runs after admin-shell.js; it only touches the #notifWrapper markup that the
// user layout renders.

document.addEventListener('DOMContentLoaded', () => {
    const wrapper = document.getElementById('notifWrapper');
    if (!wrapper) return;

    const bell = document.getElementById('notifBtn');
    const badge = document.getElementById('notifBadge');
    const dropdown = document.getElementById('notifDropdown');
    const list = document.getElementById('notifList');
    const markAllBtn = document.getElementById('notifMarkAll');

    const FEED_URL = wrapper.dataset.feedUrl;
    const READ_URL = wrapper.dataset.readUrl;
    const CSRF_TOKEN = wrapper.dataset.csrf;
    const REFRESH_MS = 30000;

    const ICONS = {
        approved: 'fa-circle-check',
        rejected: 'fa-circle-xmark',
        overdue: 'fa-triangle-exclamation'
    };

    let notifications = [];

    function escapeHTML(value) {
        if (value === null || value === undefined) return '';
        return String(value).replace(/[&<>'"]/g, tag => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        }[tag] || tag));
    }

    function unreadCount() {
        return notifications.filter(n => !n.read).length;
    }

    function renderBadge() {
        const unread = unreadCount();

        if (badge) {
            badge.textContent = unread > 99 ? '99+' : unread;
            badge.style.display = unread > 0 ? 'flex' : 'none';
        }
        if (markAllBtn) {
            markAllBtn.style.display = unread > 0 ? 'inline-block' : 'none';
        }
    }

    function render() {
        renderBadge();

        if (!notifications.length) {
            list.innerHTML = `
                <div class="notif-empty">
                    <i class="fa-solid fa-bell-slash"></i>
                    <span>No notifications yet.</span>
                </div>
            `;
            return;
        }

        list.innerHTML = notifications.map(n => `
            <a href="${escapeHTML(n.url)}" class="notif-item ${n.read ? '' : 'unread'}" data-key="${escapeHTML(n.key)}">
                <div class="notif-icon ${escapeHTML(n.type)}">
                    <i class="fa-solid ${ICONS[n.type] || 'fa-bell'}"></i>
                </div>
                <div class="notif-body">
                    <span class="notif-item-title">${escapeHTML(n.title)}</span>
                    <span class="notif-item-msg">${escapeHTML(n.message)}</span>
                    <span class="notif-item-time">${escapeHTML(n.ago)}</span>
                </div>
            </a>
        `).join('');
    }

    function refresh() {
        return fetch(FEED_URL, {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(res => res.ok ? res.json() : Promise.reject(new Error('HTTP ' + res.status)))
            .then(data => {
                if (data && data.success) {
                    notifications = data.notifications || [];
                    render();
                }
            })
            .catch(err => console.error('Error loading notifications:', err));
    }

    // keepalive lets the request finish even when the click navigates away.
    function markRead(payload) {
        return fetch(READ_URL, {
            method: 'POST',
            keepalive: true,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: JSON.stringify(payload)
        }).catch(err => console.error('Error updating notifications:', err));
    }

    function setOpen(open) {
        dropdown.classList.toggle('show', open);
        bell.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) refresh();
    }

    // The bell belongs to this script. Handle the click in the capture phase and
    // stop it there, so a stale per-page handler (e.g. an old "No new
    // notifications" alert) can't also fire. The other navbar dropdown is closed
    // here because the click no longer reaches the shell's own listener.
    bell.addEventListener('click', e => {
        e.stopImmediatePropagation();
        const profileMenu = document.getElementById('dropdownMenu');
        if (profileMenu) profileMenu.classList.remove('show');
        setOpen(!dropdown.classList.contains('show'));
    }, true);
    bell.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            setOpen(!dropdown.classList.contains('show'));
        }
    });

    // Capture phase, so it still runs when another dropdown stops propagation.
    document.addEventListener('click', e => {
        if (!wrapper.contains(e.target)) setOpen(false);
    }, true);

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') setOpen(false);
    });

    // Clicking a notification marks it read, then follows its link.
    list.addEventListener('click', e => {
        const item = e.target.closest('.notif-item');
        if (!item) return;

        const key = item.dataset.key;
        const notification = notifications.find(n => n.key === key);
        if (notification && !notification.read) {
            notification.read = true;
            markRead({ keys: [key] });
            // Update in place; re-rendering here could cancel the link navigation.
            item.classList.remove('unread');
            renderBadge();
        }
    });

    if (markAllBtn) {
        markAllBtn.addEventListener('click', () => {
            notifications.forEach(n => { n.read = true; });
            render();
            markRead({ all: true });
        });
    }

    // Initial load, then stay current without a manual reload.
    refresh();
    setInterval(refresh, REFRESH_MS);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') refresh();
    });
});
