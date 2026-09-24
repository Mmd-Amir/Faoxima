import { call } from './api.js?v=0.0.52';
import { escapeHtml, emptyState } from './utils.js?v=0.0.52';
import { icon } from './icons.js?v=0.0.52';

let current = null;
let readyPromise = null;

function bannerHtml(notif) {
    return `
        <div class="notice-banner" id="active-notice-banner" role="alert">
            <span class="notice-banner-icon">${icon('bell', 'class="ico ico-xl"')}</span>
            <div class="notice-banner-body">${escapeHtml(notif.message)}</div>
            <button type="button" class="notice-banner-close" id="notice-banner-close" aria-label="بستن">
                ${icon('close', 'class="ico"')}
            </button>
        </div>
    `;
}

async function dismiss(id) {
    if (current && current.id === id) current.seen = true;
    try {
        await call('notification_dismiss', { method: 'POST', body: { id } });
    } catch (_) {  }
}

function showBanner(notif) {
    const existing = document.getElementById('active-notice-banner');
    if (existing) existing.remove();

    const wrap = document.createElement('div');
    wrap.innerHTML = bannerHtml(notif);
    const el = wrap.firstElementChild;
    document.body.appendChild(el);

    const close = el.querySelector('#notice-banner-close');
    close.addEventListener('click', () => {
        el.remove();
        dismiss(notif.id).then(syncBellDot);
    });
}

async function fetchState() {
    try {
        const res = await call('notification_info');
        current = res?.obj?.notification || null;
    } catch (_) {
        current = null;
    }
    return current;
}

export function initNotifications() {
    readyPromise = (async () => {
        const notif = await fetchState();
        if (notif && !notif.seen) {
            showBanner(notif);
        }
        return notif;
    })();
    return readyPromise;
}

function syncBellDot() {
    const dot = document.getElementById('notif-bell-dot');
    if (dot) dot.hidden = !current || !!current.seen;
}

function fmtNotifDate(unixSeconds) {
    try {
        return new Intl.DateTimeFormat('fa-IR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(unixSeconds * 1000));
    } catch (_) {
        return '';
    }
}

function recentListHtml(notifications) {
    const items = notifications.map((n) => `
        <div class="notice-list-item${n.seen ? '' : ' is-unseen'}" data-id="${n.id}">
            <span class="notice-list-icon">${icon('bell', 'class="ico"')}</span>
            <div class="notice-list-body">
                <div class="notice-list-text">${escapeHtml(n.message)}</div>
                <div class="notice-list-time">${escapeHtml(fmtNotifDate(n.created_at))}</div>
            </div>
            ${n.seen ? '' : '<span class="notice-list-dot" aria-hidden="true"></span>'}
        </div>
    `).join('');

    return `
        <div class="notice-overlay" id="notice-recent-overlay" role="dialog" aria-modal="true">
            <div class="notice-recent-panel">
                <div class="notice-recent-head">
                    <span>اعلان‌های اخیر</span>
                    <button type="button" class="notice-banner-close" id="notice-recent-close" aria-label="بستن">
                        ${icon('close', 'class="ico"')}
                    </button>
                </div>
                <div class="notice-recent-list">
                    ${items || emptyState('اعلانی وجود ندارد')}
                </div>
            </div>
        </div>
    `;
}

async function fetchRecent() {
    try {
        const res = await call('notification_recent');
        return res?.obj?.notifications || [];
    } catch (_) {
        return [];
    }
}

async function showRecentList() {
    const notifications = await fetchRecent();

    const existing = document.getElementById('notice-recent-overlay');
    if (existing) existing.remove();

    const wrap = document.createElement('div');
    wrap.innerHTML = recentListHtml(notifications);
    const overlay = wrap.firstElementChild;
    document.body.appendChild(overlay);

    const close = () => overlay.remove();
    overlay.querySelector('#notice-recent-close').addEventListener('click', close);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });

    // Marking the newest item in this list as seen is enough to clear the bell dot —
    // handleDismiss on the server only ever moves last_seen_notification_id forward, so
    // this can't accidentally un-mark something newer that arrived after the list loaded.
    if (notifications.length) {
        const newestId = notifications[0].id;
        dismiss(newestId).then(syncBellDot);
    }
}

export async function mountHomeBell() {
    if (!readyPromise) initNotifications();
    await readyPromise.catch(() => {});

    const btn = document.getElementById('notif-bell-btn');
    if (!btn) return;

    // Always visible next to the reload button, whether or not there's an active
    // notification right now — clicking it opens the recent-notifications history so
    // the icon doubles as "view recent notifications" rather than only appearing
    // reactively when something new comes in.
    syncBellDot();

    btn.addEventListener('click', () => {
        showRecentList();
    });
}
