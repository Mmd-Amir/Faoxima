(function () {
    'use strict';

    var one = function (selector, root) { return (root || document).querySelector(selector); };
    var all = function (selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); };

    function updateNavigation(stage) {
        all('[data-nav-step]').forEach(function (item) {
            var number = Number(item.getAttribute('data-nav-step'));
            item.classList.toggle('is-active', number === stage);
            item.classList.toggle('is-complete', number < stage);
            var use = one('use', item);
            if (use && number < stage) use.setAttribute('href', '#i-check');
        });
        var label = one('#sidebar-progress-label');
        var fill = one('#sidebar-progress-fill');
        if (label) label.textContent = stage.toLocaleString('fa-IR') + ' از ۴';
        if (fill) fill.style.width = (stage * 25) + '%';
    }

    function validateStage(stageElement) {
        var inputs = all('input[required]', stageElement);
        var valid = true;
        inputs.forEach(function (input) {
            input.classList.remove('is-invalid');
            if (!input.checkValidity()) {
                input.classList.add('is-invalid');
                if (valid) input.reportValidity();
                valid = false;
            }
        });
        return valid;
    }

    function updateReview() {
        var values = {
            '#review-admin': one('#admin_id'),
            '#review-database': one('#database_name'),
            '#review-host': one('#database_host'),
            '#review-webhook': one('#bot_address_webhook')
        };
        Object.keys(values).forEach(function (selector) {
            var target = one(selector);
            var input = values[selector];
            if (target) target.textContent = input && input.value.trim() ? input.value.trim() : '—';
        });
    }

    function initWizard() {
        var form = one('#installer-form');
        if (!form) return;
        var stages = all('[data-form-stage]', form);
        var errorStage = document.body.getAttribute('data-error-stage');
        var current = errorStage === 'database' ? 3 : 2;
        if (['config', 'migration', 'admin', 'webhook', 'cleanup'].indexOf(errorStage) !== -1) current = 4;
        var loading = one('#install-loading');
        var recovery = one('#install-loading-recovery');
        var submitting = false;
        var watchdog = null;

        function resetSubmission() {
            submitting = false;
            if (watchdog) window.clearTimeout(watchdog);
            watchdog = null;
            if (loading) loading.hidden = true;
            if (recovery) recovery.hidden = true;
            all('button', form).forEach(function (button) { button.disabled = false; });
        }

        function show(stage) {
            current = Math.max(2, Math.min(4, stage));
            stages.forEach(function (element) {
                element.classList.toggle('is-active', Number(element.getAttribute('data-form-stage')) === current);
            });
            var previous = one('#install-prev-btn');
            var next = one('#install-next-btn');
            var submit = one('#install-submit');
            if (previous) previous.hidden = current === 2;
            if (next) next.hidden = current === 4;
            if (submit) submit.hidden = current !== 4;
            if (current === 4) updateReview();
            updateNavigation(current);
        }

        one('#install-next-btn').addEventListener('click', function () {
            var active = one('[data-form-stage="' + current + '"]', form);
            if (validateStage(active)) show(current + 1);
        });
        one('#install-prev-btn').addEventListener('click', function () { show(current - 1); });
        all('input', form).forEach(function (input) {
            input.addEventListener('input', function () {
                input.classList.remove('is-invalid');
                updateReview();
            });
        });
        form.addEventListener('submit', function (event) {
            if (submitting) {
                event.preventDefault();
                return;
            }
            var invalidStage = stages.find(function (stage) { return !validateStage(stage); });
            if (invalidStage) {
                event.preventDefault();
                show(Number(invalidStage.getAttribute('data-form-stage')));
                return;
            }
            submitting = true;
            if (loading) loading.hidden = false;
            if (recovery) recovery.hidden = true;
            window.setTimeout(function () {
                all('button', form).forEach(function (button) { button.disabled = true; });
            }, 0);
            watchdog = window.setTimeout(function () {
                if (recovery) recovery.hidden = false;
            }, 120000);
        });
        var dismiss = one('#install-loading-dismiss');
        if (dismiss) dismiss.addEventListener('click', resetSubmission);
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) resetSubmission();
        });
        show(current);
    }

    function initPasswordToggles() {
        all('[data-password-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.getElementById(button.getAttribute('data-password-toggle'));
                if (!input) return;
                var visible = input.type === 'text';
                input.type = visible ? 'password' : 'text';
                var use = one('use', button);
                if (use) use.setAttribute('href', visible ? '#i-eye' : '#i-eye-off');
                button.setAttribute('aria-label', visible ? 'نمایش مقدار' : 'پنهان کردن مقدار');
            });
        });
    }

    function initCleanup() {
        var panel = one('[data-cleanup-url]');
        if (!panel) return;
        var state = one('#cleanup-state');
        var body = new URLSearchParams({ rx_action: 'cleanup', csrf_token: panel.getAttribute('data-cleanup-token') });
        fetch(panel.getAttribute('data-cleanup-url'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin',
            keepalive: true
        }).then(function (response) { return response.json(); }).then(function (result) {
            if (!state) return;
            if (result.ok) {
                state.classList.add('is-done');
                var use = one('use', state);
                if (use) use.setAttribute('href', '#i-check');
                one('span', state).textContent = 'پاک‌سازی فایل‌های اینستالر تکمیل شد.';
            } else {
                throw new Error('cleanup');
            }
        }).catch(function () {
            if (!state) return;
            state.classList.add('is-fail');
            var use = one('use', state);
            if (use) use.setAttribute('href', '#i-warn');
            one('span', state).textContent = 'پاک‌سازی خودکار کامل نشد؛ پوشه installer را دستی حذف کنید.';
        });
    }

    function initKeepAlive() {
        var token = document.body.getAttribute('data-keepalive-token');
        if (!token || one('[data-cleanup-url]')) return;
        window.setInterval(function () {
            var body = new URLSearchParams({ rx_action: 'keepalive', csrf_token: token });
            fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
                credentials: 'same-origin',
                cache: 'no-store'
            }).catch(function () {});
        }, 120000);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initWizard();
        initPasswordToggles();
        initCleanup();
        initKeepAlive();
    });
})();
