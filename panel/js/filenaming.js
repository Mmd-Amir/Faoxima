(function () {
    'use strict';

    var page = document.getElementById('fnPage');
    if (!page) {
        return;
    }

    var API = page.getAttribute('data-api') || 'filenaming_api.php';
    var CSRF = page.getAttribute('data-csrf') || '';
    var ERROR_TEXT = {
        auth: 'نشست شما منقضی شده است؛ صفحه را دوباره باز کنید.',
        forbidden: 'دسترسی شما برای تغییر این تنظیمات مجاز نیست.',
        migration_required: 'ساختار جدول تنظیمات هنوز ساخته نشده است؛ یک‌بار فرایند به‌روزرسانی نصب (table.php) را اجرا کنید.',
        panel_not_found: 'پنل انتخاب‌شده پیدا نشد؛ فهرست را دوباره بارگذاری کنید.',
        invalid_source: 'نوع منبع انتخاب‌شده معتبر نیست.',
        invalid_user_id: 'آیدی عددی معتبر وارد کنید؛ برای قانون قدیمی، آیدی صاحب سرویس را جستجو کنید.',
        invalid_target: 'کانفیگ هدف معتبر نیست یا اعتبار انتخاب آن تمام شده است؛ کانفیگ‌ها را دوباره بارگذاری و انتخاب کنید.',
        invalid_filename: 'نام فایل پس از پاک‌سازی قابل استفاده نیست؛ یک نام دیگر وارد کنید.',
        invalid_extension: 'پسوند فایل انتخاب‌شده معتبر نیست.',
        duplicate_rule: 'برای این کانفیگ از قبل تنظیمی ثبت شده است؛ آن را ویرایش کنید.',
        panel_read_failed: 'خواندن اطلاعات از پنل ناموفق بود؛ اتصال پنل را بررسی کنید.',
        save_failed: 'ذخیره تنظیم ناموفق بود؛ دوباره تلاش کنید.',
        server: 'خطای سرور رخ داد؛ دوباره تلاش کنید.',
        method: 'درخواست نامعتبر است.',
        csrf_invalid: 'توکن امنیتی معتبر نیست؛ صفحه را دوباره باز کنید.',
        unknown_action: 'درخواست ناشناخته است.'
    };

    var state = {
        panels: [],
        rules: [],
        rulesPage: 1,
        editing: null,
        targets: [],
        selected: null,
        previewTimer: null,
        previewSeq: 0,
        contextSeq: 0,
        targetsSeq: 0,
        customerId: '',
        servicePage: 0,
        busy: false
    };

    var masterInput = document.getElementById('fnMaster');
    var rulesState = document.getElementById('fnRulesState');
    var rulesBox = document.getElementById('fnRules');
    var rulesFoot = document.getElementById('fnRulesFoot');
    var rulesInfo = document.getElementById('fnRulesInfo');
    var rulesPager = document.getElementById('fnRulesPager');
    var rulesPageSize = 3;
    var addBtn = document.getElementById('fnAdd');
    var modal = document.getElementById('fnModal');
    var form = document.getElementById('fnForm');
    var modalTitle = document.getElementById('fnModalTitle');
    var idInput = document.getElementById('fnId');
    var panelSelect = document.getElementById('fnPanel');
    var serviceStep = document.getElementById('fnServiceStep');
    var serviceLabel = document.getElementById('fnServiceLabel');
    var serviceHint = document.getElementById('fnServiceHint');
    var serviceRow = document.getElementById('fnServiceRow');
    var serviceNote = document.getElementById('fnServiceNote');
    var serviceSelect = document.getElementById('fnService');
    var loadTargetsBtn = document.getElementById('fnLoadTargets');
    var targetsBox = document.getElementById('fnTargets');
    var targetFilterWrap = document.getElementById('fnTargetFilterWrap');
    var targetFilter = document.getElementById('fnTargetFilter');
    var sourceInput = document.getElementById('fnSource');
    var extInput = document.getElementById('fnExt');
    var keyInput = document.getElementById('fnKey');
    var labelInput = document.getElementById('fnLabel');
    var basisInput = document.getElementById('fnBasis');
    var filenameInput = document.getElementById('fnFilename');
    var captionInput = document.getElementById('fnCaption');
    var nameHint = document.getElementById('fnNameHint');
    var previewName = document.getElementById('fnPreviewName');
    var previewCaption = document.getElementById('fnPreviewCaption');
    var previewNote = document.getElementById('fnPreviewNote');
    var enabledInput = document.getElementById('fnEnabled');
    var formError = document.getElementById('fnFormError');
    var saveBtn = document.getElementById('fnSave');
    var toast = document.getElementById('fnToast');
    var toastTimer = null;
    var customerLookup = document.getElementById('fnCustomerLookup');
    var customerInput = document.getElementById('fnCustomerId');
    var findServicesBtn = document.getElementById('fnFindServices');
    var serviceStatus = document.getElementById('fnServiceStatus');
    var servicePager = document.getElementById('fnServicePager');
    var servicesPrev = document.getElementById('fnServicesPrev');
    var servicesNext = document.getElementById('fnServicesNext');
    var servicesPage = document.getElementById('fnServicesPage');
    var pickers = [];

    function closePickers(except) {
        pickers.forEach(function (picker) {
            if (picker !== except) picker.close();
        });
    }

    function refreshPickers() {
        pickers.forEach(function (picker) { picker.refresh(); });
    }

    function createPicker(select, labelId, placeholder) {
        var host = document.createElement('div');
        host.className = 'fn-picker';
        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'fn-picker__trigger';
        trigger.setAttribute('aria-labelledby', labelId + ' ' + select.id + 'Value');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-controls', select.id + 'Popup');
        var value = document.createElement('span');
        value.id = select.id + 'Value';
        value.className = 'fn-picker__value';
        var arrow = document.createElement('span');
        arrow.className = 'fn-picker__arrow';
        arrow.setAttribute('aria-hidden', 'true');
        arrow.textContent = '⌄';
        trigger.appendChild(value);
        trigger.appendChild(arrow);
        var popup = document.createElement('div');
        popup.id = select.id + 'Popup';
        popup.className = 'fn-picker__popup';
        popup.hidden = true;
        var search = document.createElement('input');
        search.type = 'search';
        search.className = 'form-control fn-picker__search';
        search.placeholder = placeholder;
        search.setAttribute('aria-label', placeholder);
        search.autocomplete = 'off';
        var list = document.createElement('div');
        list.className = 'fn-picker__list';
        list.setAttribute('role', 'listbox');
        list.setAttribute('aria-labelledby', labelId);
        var count = document.createElement('div');
        count.className = 'fn-picker__count';
        count.setAttribute('role', 'status');
        popup.appendChild(search);
        popup.appendChild(count);
        popup.appendChild(list);
        host.appendChild(trigger);
        host.appendChild(popup);
        select.insertAdjacentElement('afterend', host);
        select.hidden = true;
        select.required = false;

        function normalize(text) {
            return String(text || '').toLowerCase().replace(/ي/g, 'ی').replace(/ك/g, 'ک')
                .replace(/[۰-۹]/g, function (digit) { return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)); });
        }

        function addText(parent, className, text) {
            var span = document.createElement('span');
            span.className = className;
            span.textContent = text;
            parent.appendChild(span);
        }

        function render() {
            list.innerHTML = '';
            var terms = normalize(search.value).trim().split(/\s+/).filter(Boolean);
            var options = Array.prototype.slice.call(select.options).filter(function (option) {
                var haystack = normalize(option.textContent + ' ' + (option.dataset.detail || '') + ' ' + (option.dataset.search || '') + ' ' + option.value);
                return option.value && !option.disabled && terms.every(function (term) { return haystack.indexOf(term) !== -1; });
            });
            count.textContent = options.length ? options.length.toLocaleString('fa-IR') + ' مورد قابل انتخاب' : 'موردی پیدا نشد؛ عبارت جستجو را تغییر دهید.';
            options.forEach(function (option) {
                var item = document.createElement('button');
                item.type = 'button';
                item.className = 'fn-picker__option';
                item.setAttribute('role', 'option');
                item.setAttribute('aria-selected', option.value === select.value ? 'true' : 'false');
                addText(item, 'fn-picker__title', option.textContent);
                if (option.dataset.detail) addText(item, 'fn-picker__detail', option.dataset.detail);
                item.addEventListener('click', function () {
                    select.value = option.value;
                    picker.close();
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    picker.refresh();
                    trigger.focus();
                });
                item.addEventListener('keydown', function (event) {
                    var buttons = Array.prototype.slice.call(list.querySelectorAll('button'));
                    var index = buttons.indexOf(item);
                    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                        event.preventDefault();
                        var next = index + (event.key === 'ArrowDown' ? 1 : -1);
                        if (buttons[next]) buttons[next].focus();
                        else if (next < 0) search.focus();
                    }
                });
                list.appendChild(item);
            });
        }

        var picker = {
            close: function () {
                popup.hidden = true;
                trigger.setAttribute('aria-expanded', 'false');
            },
            refresh: function () {
                var selected = select.options[select.selectedIndex];
                value.innerHTML = '';
                addText(value, 'fn-picker__title', selected ? selected.textContent : placeholder);
                if (selected && selected.value && selected.dataset.detail) addText(value, 'fn-picker__detail', selected.dataset.detail);
                host.classList.toggle('has-value', !!select.value);
                trigger.disabled = select.disabled || !Array.prototype.some.call(select.options, function (option) { return !!option.value && !option.disabled; });
                if (trigger.disabled) picker.close();
                if (!popup.hidden) render();
            }
        };
        trigger.addEventListener('click', function () {
            var open = popup.hidden;
            closePickers();
            if (open) {
                popup.hidden = false;
                trigger.setAttribute('aria-expanded', 'true');
                search.value = '';
                render();
                search.focus();
            }
        });
        search.addEventListener('input', render);
        search.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown') {
                var first = list.querySelector('button');
                if (first) { event.preventDefault(); first.focus(); }
            }
        });
        host.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !popup.hidden) {
                event.preventDefault();
                event.stopPropagation();
                picker.close();
                trigger.focus();
            }
        });
        document.addEventListener('click', function (event) {
            if (!host.contains(event.target)) picker.close();
        });
        document.addEventListener('focusin', function (event) {
            if (!host.contains(event.target)) picker.close();
        });
        select.addEventListener('change', picker.refresh);
        new MutationObserver(picker.refresh).observe(select, { childList: true, subtree: true, attributes: true, characterData: true });
        pickers.push(picker);
        picker.refresh();
    }

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    }

    function errorText(code, fallback) {
        return ERROR_TEXT[code] || fallback || 'خطای نامشخص رخ داد.';
    }

    function showToast(message, kind) {
        if (!toast) {
            return;
        }
        toast.textContent = message;
        toast.className = 'fn-toast' + (kind ? ' is-' + kind : '');
        toast.hidden = false;
        if (toastTimer) {
            clearTimeout(toastTimer);
        }
        toastTimer = setTimeout(function () {
            toast.hidden = true;
        }, 3800);
    }

    function setRulesState(kind, message) {
        if (!rulesState) {
            return;
        }
        rulesState.className = 'fn-state' + (kind ? ' is-' + kind : '');
        rulesState.textContent = message || '';
        rulesState.classList.toggle('is-visible', !!message);
    }

    function setFormError(message) {
        if (!formError) {
            return;
        }
        formError.textContent = message || '';
        formError.hidden = !message;
    }

    function apiGet(action, params) {
        var url = API + '?action=' + encodeURIComponent(action);
        if (params) {
            Object.keys(params).forEach(function (key) {
                var value = params[key];
                if (value === null || value === undefined || value === '') {
                    return;
                }
                url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(value);
            });
        }
        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(parseResponse);
    }

    function apiPost(action, data) {
        var body = new URLSearchParams();
        Object.keys(data || {}).forEach(function (key) {
            var value = data[key];
            body.append(key, value == null ? '' : String(value));
        });
        return fetch(API + '?action=' + encodeURIComponent(action), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-CSRF-Token': CSRF,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body.toString()
        }).then(parseResponse);
    }

    function parseResponse(response) {
        return response.json().catch(function () {
            return { ok: false, error: 'server' };
        }).then(function (payload) {
            if (!response.ok || !payload || payload.ok !== true) {
                var code = payload && payload.error ? payload.error : 'server';
                return { ok: false, error: code, payload: payload || {} };
            }
            return payload;
        });
    }

    function findPanel(ref) {
        for (var i = 0; i < state.panels.length; i++) {
            if (state.panels[i].ref === ref) {
                return state.panels[i];
            }
        }
        return null;
    }

    function loadPanels() {
        return apiGet('panels').then(function (res) {
            if (!res.ok) {
                return res;
            }
            state.panels = res.panels || [];
            panelSelect.innerHTML = '<option value="">یک پنل انتخاب کنید…</option>';
            state.panels.forEach(function (panel) {
                var option = document.createElement('option');
                option.value = panel.ref;
                option.textContent = panel.name;
                option.dataset.detail = 'نوع پنل: ' + ({ 'Manualsale': 'فروش دستی', 'pasarguard': 'پاسارگارد', 'WGDashboard': 'وایرگارد', 'x-ui_single': 'ایکس‌یو‌آی', 'jovadpanel': 'جواد پنل', 'rebecca': 'ربکا' }[panel.type] || panel.type);
                panelSelect.appendChild(option);
            });
            return res;
        });
    }

    function loadRules(focusId) {
        setRulesState('loading', 'در حال بارگذاری تنظیمات…');
        rulesFoot.hidden = true;
        return apiGet('rules').then(function (res) {
            if (!res.ok) {
                setRulesState('error', errorText(res.error));
                return res;
            }
            state.rules = res.rules || [];
            if (focusId) {
                var focusIndex = state.rules.findIndex(function (rule) { return String(rule.id) === String(focusId); });
                if (focusIndex >= 0) state.rulesPage = Math.floor(focusIndex / rulesPageSize) + 1;
            }
            renderRules();
            return res;
        });
    }

    function renderRules() {
        rulesBox.innerHTML = '';
        rulesFoot.hidden = !state.rules.length;
        if (!state.rules.length) {
            setRulesState('empty', 'هنوز تنظیمی اضافه نشده است. با دکمه «افزودن تنظیم» اولین قانون نام و کپشن را بسازید.');
            return;
        }
        setRulesState('', '');
        var pages = Math.ceil(state.rules.length / rulesPageSize);
        state.rulesPage = Math.max(1, Math.min(state.rulesPage, pages));
        var start = (state.rulesPage - 1) * rulesPageSize;
        var visibleRules = state.rules.slice(start, start + rulesPageSize);
        rulesInfo.textContent = 'نمایش ' + (start + 1).toLocaleString('fa-IR') + ' تا ' + (start + visibleRules.length).toLocaleString('fa-IR') + ' از ' + state.rules.length.toLocaleString('fa-IR') + ' قانون';
        rulesPager.innerHTML = '';
        rulesPager.hidden = pages <= 1;
        function addPageButton(label, page, disabled, current) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'mdt-page' + (disabled ? ' disabled' : '') + (current ? ' active' : '');
            button.textContent = label;
            button.disabled = disabled;
            button.dataset.rulesPage = String(page);
            if (current) button.setAttribute('aria-current', 'page');
            rulesPager.appendChild(button);
        }
        function addDots() {
            var dots = document.createElement('span');
            dots.className = 'mdt-dots';
            dots.textContent = '…';
            rulesPager.appendChild(dots);
        }
        addPageButton('قبلی', state.rulesPage - 1, state.rulesPage === 1, false);
        var firstPage = Math.max(1, state.rulesPage - 2);
        var lastPage = Math.min(pages, firstPage + 4);
        firstPage = Math.max(1, lastPage - 4);
        if (firstPage > 1) {
            addPageButton('۱', 1, false, false);
            if (firstPage > 2) addDots();
        }
        for (var page = firstPage; page <= lastPage; page++) {
            addPageButton(page.toLocaleString('fa-IR'), page, false, page === state.rulesPage);
        }
        if (lastPage < pages) {
            if (lastPage < pages - 1) addDots();
            addPageButton(pages.toLocaleString('fa-IR'), pages, false, false);
        }
        addPageButton('بعدی', state.rulesPage + 1, state.rulesPage === pages, false);
        visibleRules.forEach(function (rule) {
            var card = document.createElement('div');
            card.className = 'fn-rule' + (rule.is_enabled ? '' : ' is-disabled');
            var targetLabel = rule.match_label || rule.match_key;
            card.innerHTML =
                '<div class="fn-rule__top">' +
                    '<div class="fn-rule__title">' +
                        '<div class="fn-rule__panel">' + esc(rule.panel_name || rule.panel_ref) + '</div>' +
                        '<div class="fn-rule__target">' + esc(rule.match_basis_label || '') + ': ' + esc(targetLabel) + '</div>' +
                    '</div>' +
                    '<span class="badge ' + (rule.is_enabled ? 'badge-success' : 'badge-danger') + '">' + (rule.is_enabled ? 'فعال' : 'غیرفعال') + '</span>' +
                '</div>' +
                '<div class="fn-rule__badges"><span class="badge badge-purple">' + esc(rule.panel_type || '—') + '</span><span class="badge badge-info">.' + esc(rule.file_ext) + '</span></div>' +
                (rule.requires_reselect ? '<div class="fn-hintbox">برای اعمال این قانون، کانفیگ را در بخش ویرایش دوباره انتخاب کنید؛ شناسه‌های جدید نوع پروتکل را هم تفکیک می‌کنند.</div>' : '') +
                '<div class="fn-rule__name"><span>نام فایل:</span><code>' + esc(rule.custom_filename) + '</code></div>' +
                (rule.custom_caption ? '<div class="fn-rule__caption">' + esc(rule.custom_caption) + '</div>' : '') +
                '<div class="fn-rule__actions">' +
                    '<label class="switch" title="فعال/غیرفعال">' +
                        '<input type="checkbox" role="switch" aria-label="فعال یا غیرفعال کردن تنظیم" data-fn-toggle="' + rule.id + '"' + (rule.is_enabled ? ' checked' : '') + '>' +
                        '<span class="switch__slot"></span>' +
                    '</label>' +
                    '<div class="fn-rule__buttons">' +
                        '<button type="button" class="fn-icon-btn" data-fn-edit="' + rule.id + '" aria-label="ویرایش">' +
                            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>' +
                        '</button>' +
                        '<button type="button" class="fn-icon-btn is-danger" data-fn-delete="' + rule.id + '" aria-label="حذف">' +
                            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
                        '</button>' +
                    '</div>' +
                '</div>';
            rulesBox.appendChild(card);
        });
    }

    function openModal() {
        modal.hidden = false;
        document.body.classList.add('fn-modal-open');
        setTimeout(function () {
            panelSelect.focus();
        }, 30);
    }

    function closeModal() {
        closePickers();
        state.contextSeq++;
        modal.hidden = true;
        document.body.classList.remove('fn-modal-open');
        setFormError('');
        state.editing = null;
        state.targets = [];
        state.selected = null;
    }

    function resetForm() {
        closePickers();
        state.contextSeq++;
        form.reset();
        state.targets = [];
        state.selected = null;
        state.customerId = '';
        state.servicePage = 0;
        customerInput.value = '';
        customerLookup.hidden = false;
        serviceStatus.textContent = '';
        servicePager.hidden = true;
        findServicesBtn.disabled = false;
        loadTargetsBtn.disabled = true;
        idInput.value = '0';
        sourceInput.value = '';
        keyInput.value = '';
        labelInput.value = '';
        basisInput.value = 'label';
        extInput.value = 'conf';
        enabledInput.checked = true;
        serviceStep.hidden = true;
        serviceLabel.hidden = false;
        serviceHint.hidden = false;
        serviceRow.hidden = true;
        serviceNote.hidden = true;
        serviceSelect.innerHTML = '<option value="">ابتدا یک سرویس انتخاب کنید…</option>';
        targetsBox.innerHTML = '<div class="fn-hintbox">برای مشاهده کانفیگ‌ها، پنل و سرویس منبع را انتخاب کنید.</div>';
        if (targetFilter) {
            targetFilter.value = '';
        }
        if (targetFilterWrap) {
            targetFilterWrap.hidden = true;
        }
        nameHint.textContent = 'نام اصلی فایل به‌طور کامل با این نام جایگزین می‌شود.';
        previewName.textContent = '—';
        previewCaption.textContent = '—';
        previewNote.hidden = true;
        previewNote.textContent = '';
        filenameInput.value = '';
        captionInput.value = '';
        refreshPickers();
    }

    function setSelectedTarget(target) {
        if (target && (target.ambiguous || !target.token)) {
            target = null;
        }
        state.selected = target || null;
        Array.prototype.slice.call(targetsBox.querySelectorAll('.fn-target')).forEach(function (node) {
            node.classList.toggle(
                'is-selected',
                !!target && node.getAttribute('data-key') === target.key && node.getAttribute('data-source') === (target.source || '')
            );
        });
        if (!target) {
            sourceInput.value = '';
            keyInput.value = '';
            labelInput.value = '';
            basisInput.value = 'label';
            extInput.value = 'conf';
            return;
        }
        sourceInput.value = target.source || '';
        keyInput.value = target.key || '';
        labelInput.value = target.label || '';
        basisInput.value = target.basis || 'label';
        extInput.value = target.ext || 'conf';
        var hint = 'نام اصلی فایل به‌طور کامل با این نام جایگزین می‌شود.';
        if (target.basis === 'label') {
            hint = 'مبنای تطبیق: برچسب کانفیگ «' + target.label + '». نام فایل فعلی از همین برچسب ساخته می‌شود.';
        } else if (target.basis === 'file') {
            hint = 'مبنای تطبیق: نام فایل پیکربندی «' + target.label + '».';
        } else if (target.basis === 'endpoint') {
            hint = 'مبنای تطبیق: آدرس سرور «' + target.label + '»؛ فقط وقتی این آدرس یکتا باشد اعمال می‌شود.';
        } else if (target.basis === 'record') {
            hint = 'مبنای تطبیق: شناسه رکورد فروش دستی #' + (target.record_id || '') + '.';
        } else if (target.basis === 'sub') {
            hint = 'مبنای تطبیق: فایل اشتراک همین اینباند پنل.';
        } else if (target.basis === 'server') {
            hint = 'مبنای تطبیق: شناسه ثابت سرور و نوع پروتکل؛ تغییر نام نمایشی سرور روی تطبیق اثر ندارد.';
        }
        if (target.ambiguous) {
            hint = 'این کانفیگ با کانفیگ دیگری در همان سرویس هم‌شناسه است؛ برای جلوگیری از ارسال نام اشتباه قابل انتخاب نیست.';
        }
        nameHint.textContent = hint;
        schedulePreview();
        if (target && !target.ambiguous) {
            setFormError('');
        }
    }

    function renderTargets(list, notice) {
        state.targets = list || [];
        targetsBox.innerHTML = '';
        if (targetFilter) {
            targetFilter.value = '';
        }
        if (targetFilterWrap) {
            targetFilterWrap.hidden = state.targets.length <= 8;
        }
        if (notice) {
            var note = document.createElement('div');
            note.className = 'fn-hintbox';
            note.textContent = notice;
            targetsBox.appendChild(note);
        }
        if (!state.targets.length) {
            if (!notice) {
                var empty = document.createElement('div');
                empty.className = 'fn-hintbox';
                empty.textContent = 'کانفیگ قابل‌انتخابی برای این سرویس پیدا نشد.';
                targetsBox.appendChild(empty);
            }
            if (!(state.editing && state.editing.match_key)) {
                setSelectedTarget(null);
                return;
            }
        }
        state.targets.forEach(function (target, index) {
            var label = document.createElement('label');
            label.className = 'fn-target' + (target.ambiguous ? ' is-ambiguous' : '');
            label.setAttribute('data-key', target.key);
            label.setAttribute('data-source', target.source || '');
            label.innerHTML =
                '<input type="radio" name="fn_target" value="' + esc(target.key) + '"' + (target.ambiguous ? ' disabled' : '') + '>' +
                '<span class="fn-target__dot"></span>' +
                '<span class="fn-target__body">' +
                    '<span class="fn-target__label">' + esc(target.label || target.key) + '</span>' +
                    '<span class="fn-target__meta">' + esc(target.hint || '') + ' · .' + esc(target.ext || '') + (target.owner ? ' · ' + esc(target.owner) : '') + '</span>' +
                    (target.ambiguous ? '<span class="fn-target__meta fn-target__warn">هم‌شناسه با کانفیگ دیگر — قابل انتخاب نیست</span>' : '') +
                '</span>';
            label.addEventListener('click', function (event) {
                if (target.ambiguous) {
                    event.preventDefault();
                    return;
                }
                var input = label.querySelector('input');
                if (input) {
                    input.checked = true;
                }
                setSelectedTarget(target);
            });
            targetsBox.appendChild(label);
        });
        var preferred = null;
        var preferredInput = null;
        if (state.editing && state.editing.match_key) {
            for (var i = 0; i < state.targets.length; i++) {
                if (state.targets[i].key === state.editing.match_key && state.targets[i].source === state.editing.source) {
                    preferred = state.targets[i];
                    break;
                }
            }
            if (!preferred || preferred.ambiguous) {
                preferred = null;
                setSelectedTarget(null);
                setFormError('هدف قبلی پیدا نشد یا هم‌شناسه است؛ کانفیگ موردنظر را دوباره از فهرست انتخاب کنید.');
            }
        }
        if (preferred) {
            if (!preferredInput) {
                Array.prototype.slice.call(targetsBox.querySelectorAll('.fn-target')).forEach(function (node) {
                    if (!preferredInput
                        && node.getAttribute('data-key') === preferred.key
                        && node.getAttribute('data-source') === (preferred.source || '')) {
                        preferredInput = node.querySelector('input');
                    }
                });
            }
            if (preferredInput) {
                preferredInput.checked = true;
            }
            setSelectedTarget(preferred);
        }
    }

    function normalizeCustomerId(value) {
        return String(value || '').trim().replace(/[۰-۹]/g, function (digit) { return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)); })
            .replace(/[٠-٩]/g, function (digit) { return String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit)); });
    }

    function loadServices(panelRef, preferredService, customerId, page, ruleId) {
        customerId = normalizeCustomerId(customerId);
        if (!customerId && !ruleId) {
            serviceStatus.textContent = 'ابتدا آیدی عددی کاربر را وارد کنید.';
            return Promise.resolve({ ok: false });
        }
        if (customerId && !/^[1-9][0-9]{0,19}$/.test(customerId)) {
            serviceStatus.textContent = 'آیدی عددی معتبر وارد کنید.';
            return Promise.resolve({ ok: false });
        }
        var context = ++state.contextSeq;
        state.targets = [];
        setSelectedTarget(null);
        closePickers();
        serviceRow.hidden = true;
        servicePager.hidden = true;
        loadTargetsBtn.disabled = true;
        findServicesBtn.disabled = true;
        serviceStatus.textContent = 'در حال جستجوی سرویس‌های کاربر…';
        targetsBox.innerHTML = '<div class="fn-hintbox">پس از جستجوی کاربر، سرویس منبع را انتخاب کنید.</div>';
        serviceSelect.innerHTML = '<option value="">در حال بارگذاری سرویس‌ها…</option>';
        return apiGet('services', { panel_ref: panelRef, user_id: customerId, page: page || 0, rule_id: ruleId || '' }).then(function (res) {
            if (context !== state.contextSeq || panelSelect.value !== panelRef) {
                return { ok: false, stale: true };
            }
            if (!res.ok) {
                serviceSelect.innerHTML = '<option value="">' + esc(errorText(res.error)) + '</option>';
                serviceStatus.textContent = errorText(res.error);
                findServicesBtn.disabled = false;
                return res;
            }
            var services = res.services || [];
            serviceSelect.innerHTML = '<option value="">' + (services.length ? 'یک سرویس انتخاب کنید…' : 'سرویسی برای این پنل ثبت نشده است.') + '</option>';
            services.forEach(function (service) {
                var option = document.createElement('option');
                option.value = service.value;
                option.textContent = service.label;
                option.dataset.detail = service.detail || '';
                option.dataset.search = service.search || '';
                serviceSelect.appendChild(option);
            });
            if (preferredService) {
                serviceSelect.value = preferredService;
            }
            state.customerId = res.user_id || customerId;
            state.servicePage = res.page || 0;
            customerInput.value = state.customerId;
            findServicesBtn.disabled = false;
            serviceStatus.textContent = services.length
                ? 'سرویس‌های کاربر ' + state.customerId + ' در این پنل؛ یک مورد را انتخاب کنید. سفارش‌های پرداخت‌نشده و حذف‌شده نمایش داده نمی‌شوند.'
                : 'برای این آیدی در پنل انتخاب‌شده سرویس قابل بررسی پیدا نشد.';
            servicePager.hidden = !(state.servicePage > 0 || res.has_more);
            servicesPrev.disabled = state.servicePage === 0;
            servicesNext.disabled = !res.has_more;
            servicesPage.textContent = 'صفحه ' + (state.servicePage + 1).toLocaleString('fa-IR') + ' · حداکثر ۲۰ سرویس';
            loadTargetsBtn.disabled = !serviceSelect.value;
            refreshPickers();
            serviceStep.hidden = false;
            serviceLabel.hidden = false;
            serviceHint.hidden = false;
            serviceRow.hidden = !services.length;
            serviceNote.hidden = true;
            return res;
        }).catch(function () {
            if (context === state.contextSeq && panelSelect.value === panelRef) {
                serviceSelect.innerHTML = '<option value="">بارگذاری سرویس‌ها ناموفق بود؛ پنل را دوباره انتخاب کنید.</option>';
                serviceStatus.textContent = 'جستجو ناموفق بود؛ دوباره تلاش کنید.';
                findServicesBtn.disabled = false;
                refreshPickers();
            }
            return { ok: false, error: 'server' };
        });
    }

    function loadTargets() {
        var context = state.contextSeq;
        var request = ++state.targetsSeq;
        var panelRef = panelSelect.value;
        if (!panelRef) {
            setFormError('ابتدا یک پنل انتخاب کنید.');
            return Promise.resolve(null);
        }
        var panel = findPanel(panelRef);
        var service = serviceSelect.value;
        var needsService = panel && ['x-ui_single', 'jovadpanel', 'rebecca', 'pasarguard'].indexOf(panel.type) !== -1;
        if (needsService && !service) {
            setFormError('برای این پنل ابتدا یک سرویس منبع انتخاب کنید.');
            return Promise.resolve(null);
        }
        targetsBox.innerHTML = '<div class="fn-hintbox">در حال خواندن کانفیگ‌ها از پنل…</div>';
        state.targets = [];
        setSelectedTarget(null);
        setFormError('');
        return apiGet('targets', { panel_ref: panelRef, service: service }).then(function (res) {
            if (context !== state.contextSeq || request !== state.targetsSeq
                || panelSelect.value !== panelRef || serviceSelect.value !== service) {
                return { ok: false, stale: true };
            }
            if (!res.ok) {
                targetsBox.innerHTML = '<div class="fn-hintbox">' + esc(errorText(res.error)) + '</div>';
                return res;
            }
            renderTargets(res.targets || [], res.notice || '');
            return res;
        }).catch(function () {
            if (context === state.contextSeq && request === state.targetsSeq) {
                targetsBox.innerHTML = '<div class="fn-hintbox">خواندن کانفیگ‌ها ناموفق بود؛ دوباره تلاش کنید.</div>';
            }
            return { ok: false, error: 'server' };
        });
    }

    function schedulePreview() {
        if (state.previewTimer) {
            clearTimeout(state.previewTimer);
        }
        state.previewTimer = setTimeout(runPreview, 260);
    }

    function runPreview() {
        var filename = filenameInput.value.trim();
        var caption = captionInput.value;
        var ext = extInput.value || 'conf';
        if (!filename) {
            previewName.textContent = '—';
            previewNote.hidden = true;
            previewCaption.textContent = caption ? caption : '—';
            return;
        }
        var seq = ++state.previewSeq;
        apiPost('preview', { custom_filename: filename, file_ext: ext, custom_caption: caption }).then(function (res) {
            if (seq !== state.previewSeq) {
                return;
            }
            if (!res.ok) {
                previewName.textContent = '—';
                previewNote.hidden = false;
                previewNote.textContent = errorText(res.error);
                return;
            }
            previewName.textContent = res.filename || '—';
            previewCaption.textContent = res.caption ? res.caption : '—';
            if (res.error) {
                previewNote.hidden = false;
                previewNote.textContent = 'نام واردشده پس از پاک‌سازی قابل استفاده نیست؛ نام دیگری وارد کنید.';
            } else if (res.changed) {
                previewNote.hidden = false;
                previewNote.textContent = res.wireguard
                    ? 'نام واردشده طبق قوانین فعلی فایل‌های WireGuard پاک‌سازی شد: فقط حروف لاتین، رقم، نقطه و خط تیره مجاز است و طول نام حداکثر ۱۵ کاراکتر می‌شود. نام نهایی بالا همان چیزی است که در تلگرام نمایش داده می‌شود.'
                    : 'نام واردشده پاک‌سازی شد؛ نام نهایی بالا همان چیزی است که در تلگرام نمایش داده می‌شود.';
            } else {
                previewNote.hidden = true;
                previewNote.textContent = '';
            }
        });
    }

    function openForCreate() {
        state.editing = null;
        resetForm();
        modalTitle.textContent = 'افزودن تنظیم';
        openModal();
        setRulesState('', '');
    }

    function openForEdit(rule) {
        state.editing = Object.assign({}, rule);
        resetForm();
        modalTitle.textContent = 'ویرایش تنظیم';
        idInput.value = String(rule.id);
        enabledInput.checked = !!rule.is_enabled;
        filenameInput.value = rule.custom_filename || '';
        captionInput.value = rule.custom_caption || '';
        extInput.value = rule.file_ext || 'conf';
        sourceInput.value = rule.source || '';
        keyInput.value = rule.match_key || '';
        labelInput.value = rule.match_label || '';
        basisInput.value = rule.match_basis || 'label';
        panelSelect.value = rule.panel_ref || '';
        refreshPickers();
        openModal();
        var panel = findPanel(rule.panel_ref);
        if (!panel) {
            loadPanels().then(function () {
                panelSelect.value = rule.panel_ref || '';
                prepareAfterPanel(rule.source_service);
            });
        } else {
            prepareAfterPanel(rule.source_service);
        }
        schedulePreview();
    }

    function prepareAfterPanel(preferredService) {
        var panelRef = panelSelect.value;
        if (!panelRef) {
            return;
        }
        var panel = findPanel(panelRef);
        if (!panel) {
            return;
        }
        if (panel.type === 'Manualsale' || panel.type === 'WGDashboard') {
            customerLookup.hidden = true;
            servicePager.hidden = true;
            serviceStatus.textContent = '';
            serviceSelect.value = '';
            serviceStep.hidden = false;
            serviceLabel.hidden = true;
            serviceHint.hidden = true;
            serviceRow.hidden = true;
            serviceNote.hidden = false;
            loadTargets();
            return;
        }
        customerLookup.hidden = false;
        serviceStep.hidden = false;
        serviceLabel.hidden = false;
        serviceHint.hidden = false;
        serviceRow.hidden = true;
        servicePager.hidden = true;
        serviceNote.hidden = true;
        serviceStatus.textContent = 'ابتدا آیدی عددی کاربر را جستجو کنید.';
        if (!preferredService) return;
        loadServices(panelRef, preferredService, '', 0, state.editing ? state.editing.id : 0).then(function (res) {
            if (res.ok && serviceSelect.value) {
                loadTargets();
            }
        }).catch(function () {
            setFormError('بارگذاری سرویس‌ها ناموفق بود؛ پنل را دوباره انتخاب کنید.');
        });
    }

    function saveRule(event) {
        event.preventDefault();
        if (state.busy) {
            return;
        }
        setFormError('');
        if (!panelSelect.value) {
            setFormError('پنل را انتخاب کنید.');
            return;
        }
        if (!keyInput.value || !state.selected || !state.selected.token || state.selected.ambiguous) {
            setFormError('یک کانفیگ هدف انتخاب کنید.');
            return;
        }
        if (!filenameInput.value.trim()) {
            setFormError('نام فایل سفارشی را وارد کنید.');
            return;
        }
        var panel = findPanel(panelSelect.value);
        state.busy = true;
        saveBtn.disabled = true;
        apiPost('save', {
            id: idInput.value || '0',
            panel_ref: panelSelect.value,
            panel_name: panel ? panel.name : '',
            panel_type: panel ? panel.type : '',
            source: sourceInput.value,
            source_service: serviceSelect.value,
            target_token: state.selected.token,
            match_key: keyInput.value,
            match_label: labelInput.value,
            match_basis: basisInput.value,
            file_ext: extInput.value,
            custom_filename: filenameInput.value.trim(),
            custom_caption: captionInput.value,
            is_enabled: enabledInput.checked ? '1' : '0'
        }).then(function (res) {
            state.busy = false;
            saveBtn.disabled = false;
            if (!res.ok) {
                setFormError(errorText(res.error));
                return;
            }
            closeModal();
            showToast('تنظیم با موفقیت ذخیره شد.', 'success');
            loadRules(res.id);
        }).catch(function () {
            state.busy = false;
            saveBtn.disabled = false;
            setFormError(errorText('server'));
        });
    }

    function toggleRule(id, input) {
        var enabled = input.checked ? '1' : '0';
        apiPost('toggle', { id: id, is_enabled: enabled }).then(function (res) {
            if (!res.ok) {
                input.checked = !input.checked;
                showToast(errorText(res.error), 'error');
                return;
            }
            showToast(input.checked ? 'تنظیم فعال شد.' : 'تنظیم غیرفعال شد.', 'success');
            loadRules();
        });
    }

    function deleteRule(id) {
        if (!window.confirm('این تنظیم حذف شود؟ این کار قابل بازگشت نیست.')) {
            return;
        }
        apiPost('delete', { id: id }).then(function (res) {
            if (!res.ok) {
                showToast(errorText(res.error), 'error');
                return;
            }
            showToast('تنظیم حذف شد.', 'success');
            loadRules();
        });
    }

    function toggleMaster() {
        var enabled = masterInput.checked ? '1' : '0';
        masterInput.disabled = true;
        apiPost('master', { is_enabled: enabled }).then(function (res) {
            masterInput.disabled = false;
            if (!res.ok) {
                masterInput.checked = !masterInput.checked;
                showToast(errorText(res.error), 'error');
                return;
            }
            showToast(masterInput.checked ? 'نام و کپشن سفارشی فعال شد.' : 'نام و کپشن سفارشی غیرفعال شد.', 'success');
        });
    }

    masterInput.addEventListener('change', toggleMaster);
    rulesPager.addEventListener('click', function (event) {
        var button = event.target.closest('[data-rules-page]');
        if (!button || button.disabled) return;
        state.rulesPage = parseInt(button.dataset.rulesPage, 10) || 1;
        renderRules();
        var currentButton = rulesPager.querySelector('[aria-current="page"]');
        if (currentButton) currentButton.focus({ preventScroll: true });
    });
    function searchCustomer() {
        if (!panelSelect.value) {
            setFormError('ابتدا پنل را انتخاب کنید.');
            return;
        }
        loadServices(panelSelect.value, '', customerInput.value, 0);
    }
    findServicesBtn.addEventListener('click', searchCustomer);
    customerInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            searchCustomer();
        }
    });
    customerInput.addEventListener('input', function () {
        state.contextSeq++;
        state.customerId = '';
        serviceSelect.value = '';
        state.targets = [];
        setSelectedTarget(null);
        closePickers();
        serviceRow.hidden = true;
        servicePager.hidden = true;
        findServicesBtn.disabled = false;
        loadTargetsBtn.disabled = true;
        serviceStatus.textContent = 'برای این آیدی دکمه جستجوی سرویس‌ها را بزنید.';
        targetsBox.innerHTML = '<div class="fn-hintbox">برای مشاهده کانفیگ‌ها، کاربر و سرویس را دوباره انتخاب کنید.</div>';
    });
    servicesPrev.addEventListener('click', function () {
        if (state.servicePage > 0) loadServices(panelSelect.value, '', state.customerId, state.servicePage - 1);
    });
    servicesNext.addEventListener('click', function () {
        if (!servicesNext.disabled) loadServices(panelSelect.value, '', state.customerId, state.servicePage + 1);
    });
    createPicker(panelSelect, 'fnPanelLabel', 'جستجوی نام یا نوع پنل…');
    createPicker(serviceSelect, 'fnServiceLabel', 'جستجو در سرویس‌های همین صفحه…');
    addBtn.addEventListener('click', openForCreate);
    form.addEventListener('submit', saveRule);
    filenameInput.addEventListener('input', schedulePreview);
    captionInput.addEventListener('input', function () {
        previewCaption.textContent = captionInput.value ? captionInput.value : '—';
        schedulePreview();
    });
    loadTargetsBtn.addEventListener('click', loadTargets);
    if (targetFilter) {
        targetFilter.addEventListener('input', function () {
            var query = targetFilter.value.trim().toLowerCase();
            Array.prototype.slice.call(targetsBox.querySelectorAll('.fn-target')).forEach(function (node) {
                node.hidden = query !== '' && node.textContent.toLowerCase().indexOf(query) === -1;
            });
        });
    }
    serviceSelect.addEventListener('change', function () {
        loadTargetsBtn.disabled = !serviceSelect.value;
        state.contextSeq++;
        setSelectedTarget(null);
        if (state.targets.length) {
            state.targets = [];
            targetsBox.innerHTML = '<div class="fn-hintbox">برای این سرویس دکمه «بارگذاری کانفیگ‌ها» را بزنید.</div>';
            setSelectedTarget(null);
        }
    });
    panelSelect.addEventListener('change', function () {
        state.contextSeq++;
        serviceSelect.value = '';
        state.customerId = '';
        state.servicePage = 0;
        customerInput.value = '';
        servicePager.hidden = true;
        serviceStatus.textContent = '';
        findServicesBtn.disabled = false;
        loadTargetsBtn.disabled = true;
        closePickers();
        refreshPickers();
        state.targets = [];
        state.selected = null;
        sourceInput.value = '';
        keyInput.value = '';
        labelInput.value = '';
        basisInput.value = 'label';
        extInput.value = 'conf';
        targetsBox.innerHTML = '<div class="fn-hintbox">برای مشاهده کانفیگ‌ها، سرویس منبع را انتخاب کنید.</div>';
        setFormError('');
        if (state.editing) {
            state.editing.match_key = '';
            state.editing.match_label = '';
            state.editing.match_basis = '';
            state.editing.source = '';
        }
        prepareAfterPanel('');
    });

    rulesBox.addEventListener('click', function (event) {
        var editBtn = event.target.closest('[data-fn-edit]');
        if (editBtn) {
            var id = parseInt(editBtn.getAttribute('data-fn-edit'), 10);
            for (var i = 0; i < state.rules.length; i++) {
                if (state.rules[i].id === id) {
                    openForEdit(state.rules[i]);
                    return;
                }
            }
            return;
        }
        var deleteBtn = event.target.closest('[data-fn-delete]');
        if (deleteBtn) {
            deleteRule(parseInt(deleteBtn.getAttribute('data-fn-delete'), 10));
        }
    });

    rulesBox.addEventListener('change', function (event) {
        var toggle = event.target.closest('[data-fn-toggle]');
        if (toggle) {
            toggleRule(parseInt(toggle.getAttribute('data-fn-toggle'), 10), toggle);
        }
    });

    Array.prototype.slice.call(modal.querySelectorAll('[data-fn-close]')).forEach(function (node) {
        node.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });

    loadPanels().then(function (res) {
        if (!res.ok) {
            panelSelect.innerHTML = '<option value="">' + esc(errorText(res.error)) + '</option>';
            showToast(errorText(res.error), 'error');
        }
        return loadRules();
    }).catch(function () {
        setRulesState('error', errorText('server'));
    });
})();
