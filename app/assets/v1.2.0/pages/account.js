import { call } from '../api.js?v=0.0.53';
import { escapeHtml, fmtPrice, fmtNumber, skeletonList } from '../utils.js?v=0.0.52';
import { setUser } from '../state.js?v=0.0.52';
import { icon } from '../icons.js?v=0.0.52';
import { getInitDataUnsafe } from '../telegram.js?v=0.0.52';

function renderPendingBanner(pendingList) {
    const gateways = (pendingList || []).filter(p =>
        p.method !== 'cart to cart' && p.method !== 'carttocart_pv'
    );
    if (!gateways.length) return '';
    return gateways.map(p => `
        <div class="account-pending" role="status">
            ${icon('clock')}
            <div>
                <b>پرداخت در انتظار تأیید</b>
                <p>
                    کد پیگیری: <bdi>${escapeHtml(p.order_id)}</bdi>
                </p>
                <p>
                    ${escapeHtml(fmtPrice(p.amount))} · در انتظار بررسی ادمین
                </p>
            </div>
        </div>
    `).join('');
}

export async function account(view) {
    view.innerHTML = `
        <article class="account-page" aria-label="حساب کاربری">
            <div id="account-body" class="account-content">
                ${skeletonList(4)}
            </div>
        </article>
    `;

    const $body = view.querySelector('#account-body');

    let info = null;
    let pendingList = [];
    try {
        const [infoRes, pendingRes] = await Promise.all([
            call('user_info'),
            call('pending_payments').catch(() => null),
        ]);
        info = infoRes?.obj;
        if (info) setUser(info);
        pendingList = pendingRes?.obj?.pending || [];
    } catch (err) {
        $body.innerHTML = `
            <div class="empty">
                ${icon('alert', 'class="ico ico-xxl ico-warn"')}
                <h3>خطا در دریافت اطلاعات</h3>
                <p class="muted">${escapeHtml(err.message || '')}</p>
            </div>
        `;
        return;
    }

    if (!info) {
        $body.innerHTML = `
            <div class="empty">
                ${icon('info', 'class="ico ico-xxl ico-muted"')}
                <h3>اطلاعاتی یافت نشد</h3>
            </div>
        `;
        return;
    }

    const user = getInitDataUnsafe()?.user || {};
    const name = [user.first_name, user.last_name].filter(Boolean).join(' ') || 'کاربر';
    const phone = String(info.phone || '').trim();
    const hasPhone = phone !== '' && !/ارسال نشده|not.?set|none|null|^—$|^0$/i.test(phone);

    $body.innerHTML = `
        <div class="account-page-heading">
            <div><p class="account-eyebrow">فضای شخصی شما</p><h1>حساب کاربری</h1></div>
            <a href="#/tickets" class="account-help" aria-label="پشتیبانی" title="پشتیبانی">${icon('shield')}</a>
        </div>
        <section class="account-profile" aria-label="پروفایل">
            <div class="account-profile-copy">
                <h2 dir="auto">${escapeHtml(name)}</h2>
                <div class="account-profile-meta">
                    ${user.username ? `<bdi class="account-profile-username">@${escapeHtml(user.username)}</bdi>` : ''}
                </div>
            </div>
            <span class="account-avatar" aria-hidden="true">${escapeHtml(Array.from(name.trim())[0] || 'ک')}</span>
            <span class="account-role" dir="auto">${escapeHtml(info.group_type || '—')}</span>
        </section>

        <section class="wallet-hero account-wallet" aria-label="کیف پول">
            <div class="wallet-hero-top">
                <span class="account-wallet-icon">${icon('wallet', 'class="ico"')}</span>
                <span>موجودی کیف پول</span>
            </div>
            <div class="wallet-hero-amount">${escapeHtml(fmtPrice(info.balance))}</div>
            <div class="wallet-hero-actions account-wallet-actions">
                <a href="#/recharge" class="wallet-hero-btn-primary">
                    ${icon('plus', 'class="ico"')}
                    <span>شارژ کیف پول</span>
                </a>
                <a href="#/transfer" class="wallet-hero-btn-ghost">
                    ${icon('transfer', 'class="ico"')}
                    <span>انتقال موجودی</span>
                </a>
                <a href="#/history" class="wallet-hero-btn-ghost account-wallet-history">
                    ${icon('clock', 'class="ico"')}
                    <span>تاریخچه تراکنش‌ها</span>
                </a>
            </div>
        </section>

        <div class="account-metrics" aria-label="آمار حساب">
            <a href="#/services" class="account-metric account-metric-services">
                <span class="account-metric-icon">${icon('box')}</span>
                <span class="account-metric-value">${escapeHtml(fmtNumber(info.count_order))}</span>
                <span class="account-metric-label">سرویس‌های فعال</span>
            </a>
            <a href="#/history" class="account-metric account-metric-payments">
                <span class="account-metric-icon">${icon('creditCard')}</span>
                <span class="account-metric-value">${escapeHtml(fmtNumber(info.count_payment))}</span>
                <span class="account-metric-label">پرداخت‌ها</span>
            </a>
        </div>

        <section class="account-details" aria-labelledby="account-details-title">
            <h2 id="account-details-title">اطلاعات حساب</h2>
            <div class="account-detail-row">
                <span class="account-detail-icon">${icon('calendar')}</span>
                <span class="account-detail-label">تاریخ عضویت</span>
                <b class="account-detail-value"><bdi>${escapeHtml(info.time_join || '—')}</bdi></b>
            </div>
            <div class="account-detail-row">
                <span class="account-detail-icon">${icon('phone')}</span>
                <span class="account-detail-label">شماره تلفن</span>
                <b class="account-detail-value${hasPhone ? '' : ' is-missing'}">${hasPhone ? `<bdi>${escapeHtml(phone)}</bdi>` : 'ثبت نشده'}</b>
            </div>
        </section>

        ${renderPendingBanner(pendingList)}

        <nav class="account-shortcuts" aria-label="خدمات حساب">
            <a href="#/buy" class="account-shortcut account-shortcut-buy">
                <span class="account-shortcut-icon">${icon('cart')}</span>
                <span class="account-shortcut-copy"><b>خرید سرویس</b><small>انتخاب پلن و شروع یک اتصال تازه</small></span>
                ${icon('chevronLeft', 'class="ico account-shortcut-arrow"')}
            </a>
            <a href="#/services" class="account-shortcut account-shortcut-services">
                <span class="account-shortcut-icon">${icon('fileText')}</span>
                <span class="account-shortcut-copy"><b>سرویس‌های من</b><small>مشاهده، مدیریت و تمدید</small></span>
                ${icon('chevronLeft', 'class="ico account-shortcut-arrow"')}
            </a>
            <a href="#/tickets" class="account-shortcut account-shortcut-support">
                <span class="account-shortcut-icon">${icon('shield')}</span>
                <span class="account-shortcut-copy"><b>پشتیبانی</b><small>ارسال تیکت و پیگیری درخواست‌ها</small></span>
                ${icon('chevronLeft', 'class="ico account-shortcut-arrow"')}
            </a>
        </nav>
    `;

    try {
        if (user.photo_url && new URL(user.photo_url).protocol === 'https:') {
            const avatar = $body.querySelector('.account-avatar');
            const photo = document.createElement('img');
            photo.alt = '';
            photo.referrerPolicy = 'no-referrer';
            photo.decoding = 'async';
            photo.addEventListener('load', () => avatar.replaceChildren(photo), { once: true });
            photo.src = user.photo_url;
        }
    } catch (_) { }
}

