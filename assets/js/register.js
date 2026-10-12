/* KPAH – Trang đăng ký: kiểm tra dữ liệu, độ mạnh mật khẩu, gửi AJAX, thông báo.
 * Lưu ý: kiểm tra ở đây chỉ để hỗ trợ người dùng; server PHP luôn kiểm tra lại toàn bộ. */
(function () {
    'use strict';

    /* ------------------------------------------------------------------
       Hàm thuần (không đụng DOM) – có thể kiểm thử bằng Node
       ------------------------------------------------------------------ */
    var LEVELS = ['', 'Yếu', 'Trung bình', 'Mạnh', 'Rất mạnh'];

    function strength(pw, username) {
        if (!pw) { return { level: 0, label: '' }; }
        if (pw.length < 6) { return { level: 0, label: 'Quá ngắn' }; }

        var kinds = 0;
        [/[a-z]/, /[A-Z]/, /\d/, /[^A-Za-z0-9]/].forEach(function (r) { if (r.test(pw)) { kinds++; } });

        var score = 1;
        if (pw.length >= 8 && kinds >= 2) { score++; }
        if (pw.length >= 10 && kinds >= 2) { score++; }
        if (pw.length >= 12 && kinds >= 3) { score++; }

        var weak = /^(.)\1+$/.test(pw) ||
            /^(?:0123|1234|2345|3456|4567|5678|6789|abcd|qwer|1111|0000|password)/i.test(pw) ||
            (username && pw.toLowerCase() === String(username).toLowerCase());
        if (weak) { score = 1; }

        return { level: score, label: LEVELS[score] };
    }

    function checkUsername(value, re, hint) {
        if (!value) { return 'Vui lòng nhập tên tài khoản.'; }
        return re.test(value) ? '' : hint;
    }

    function checkPassword(pw, min, max) {
        if (!pw) { return 'Vui lòng nhập mật khẩu.'; }
        if (!/^[\x21-\x7E]+$/.test(pw)) {
            return 'Mật khẩu chỉ gồm chữ cái không dấu, số và ký tự đặc biệt thông thường, không chứa khoảng trắng.';
        }
        if (pw.length < min || pw.length > max) {
            return 'Mật khẩu phải dài từ ' + min + ' đến ' + max + ' ký tự.';
        }
        return '';
    }

    function checkPhone(raw, mode) {
        var v = String(raw || '').trim();
        if (!v) { return mode === 'required' ? 'Vui lòng nhập số điện thoại.' : ''; }
        var p = v.replace(/[\s.\-()]/g, '');
        return /^(?:\+?84|0)[35789]\d{8}$/.test(p) ? '' : 'Số điện thoại chưa đúng định dạng (ví dụ 0912345678).';
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { strength: strength, checkUsername: checkUsername, checkPassword: checkPassword, checkPhone: checkPhone };
        return;
    }

    /* ------------------------------------------------------------------
       Giao diện
       ------------------------------------------------------------------ */
    var form = document.getElementById('reg-form');
    if (!form) { return; }

    var cfg = {
        endpoint: form.getAttribute('data-endpoint'),
        emailCodeEndpoint: form.getAttribute('data-email-code-endpoint'),
        emailVerifyEndpoint: form.getAttribute('data-email-verify-endpoint'),
        captchaSrc: form.getAttribute('data-captcha-src'),
        hint: form.getAttribute('data-user-hint') || 'Tên tài khoản không hợp lệ.',
        pwMin: parseInt(form.getAttribute('data-pw-min'), 10) || 6,
        pwMax: parseInt(form.getAttribute('data-pw-max'), 10) || 32,
        phoneMode: form.getAttribute('data-phone-mode') || 'optional'
    };
    try {
        cfg.userRe = new RegExp(form.getAttribute('data-user-pattern'), form.getAttribute('data-user-flags') || '');
    } catch (e) {
        cfg.userRe = /^[a-z0-9_]{4,20}$/;
    }

    var $ = function (id) { return document.getElementById(id); };
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var el = {
        username: $('f-username'), phone: $('f-phone'), email: $('f-email'), emailCode: $('f-email-code'), password: $('f-password'), password2: $('f-password2'),
        captcha: $('f-captcha'), agree: $('f-agree'), csrf: form.elements.csrf,
        submit: $('submit'), label: form.querySelector('.btn-label'),
        alert: $('alert'), alertIcon: $('alert-icon'), alertTitle: $('alert-title'), alertText: $('alert-text'),
        meter: $('meter'), meterLabel: $('meter-label'), ok2: $('ok-password2'),
        img: $('captcha-img'), refresh: $('captcha-refresh'),
        formView: $('form-view'), successView: $('success-view'), successUser: $('success-user'), successTitle: $('success-title'),
        again: $('again'), sendEmailCode: $('send-email-code'), verifyEmailCode: $('verify-email-code'), emailVerified: $('email-verified'), terms: $('terms'), openTerms: $('open-terms'), closeTerms: $('terms-close'), acceptTerms: $('terms-accept')
    };

    var meterDefault = el.meterLabel ? el.meterLabel.textContent : '';
    var busy = false;

    /* ---------- Lỗi theo trường ---------- */
    function wrap(name) { return form.querySelector('[data-field="' + name + '"]'); }

    function setError(name, msg) {
        var w = wrap(name);
        var p = $('e-' + name);
        if (!w || !p) { return; }
        w.classList.toggle('has-error', !!msg);
        p.textContent = msg || '';
        var input = w.querySelector('input:not([type="hidden"])');
        if (input) { input.setAttribute('aria-invalid', msg ? 'true' : 'false'); }
    }

    function clearErrors() {
        ['username', 'phone', 'email', 'email_code', 'password', 'password2', 'captcha', 'agree'].forEach(function (n) { setError(n, ''); });
    }

    /* ---------- Thông báo tổng ---------- */
    function showAlert(type, title, text) {
        el.alert.setAttribute('data-type', type);
        el.alertIcon.setAttribute('href', type === 'info' ? '#i-info' : '#i-alert');
        el.alertTitle.textContent = title;
        el.alertText.textContent = text || '';
        el.alert.hidden = false;
        el.alert.focus({ preventScroll: true });
        el.alert.scrollIntoView({ block: 'nearest', behavior: reduceMotion ? 'auto' : 'smooth' });
    }
    function hideAlert() { el.alert.hidden = true; }

    /* ---------- Kiểm tra từng trường ---------- */
    var validators = {
        username: function () { return checkUsername(el.username.value.trim().toLowerCase(), cfg.userRe, cfg.hint); },
        phone: function () { return el.phone ? checkPhone(el.phone.value, cfg.phoneMode) : ''; },
        email: function () { return !el.email.value.trim() ? 'Vui lòng nhập email.' : (/^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/.test(el.email.value.trim()) ? '' : 'Địa chỉ email chưa đúng định dạng.'); },
        email_code: function () { return !el.emailCode.value.trim() ? 'Vui lòng nhập mã xác minh email.' : (/^\\d{6}$/.test(el.emailCode.value.trim()) ? '' : 'Mã xác minh gồm 6 chữ số.'); },
        password: function () { return checkPassword(el.password.value, cfg.pwMin, cfg.pwMax); },
        password2: function () {
            if (!el.password2.value) { return 'Vui lòng nhập lại mật khẩu.'; }
            return el.password2.value === el.password.value ? '' : 'Mật khẩu nhập lại chưa khớp.';
        },
        captcha: function () {
            if (!el.captcha) { return ''; }
            return el.captcha.value.replace(/\s+/g, '').length < 4 ? 'Vui lòng nhập mã xác nhận ở ảnh.' : '';
        },
        agree: function () { return el.agree && !el.agree.checked ? 'Bạn cần đồng ý với điều khoản sử dụng để tiếp tục.' : ''; }
    };

    function validateField(name) {
        var msg = validators[name]();
        setError(name, msg);
        if (name === 'password2') { updateMatch(); }
        return !msg;
    }

    function validateAll() {
        var order = ['username', 'phone', 'email', 'email_code', 'password', 'password2', 'captcha', 'agree'];
        var firstBad = null;
        order.forEach(function (n) {
            if (!wrap(n)) { return; }
            if (!validateField(n) && !firstBad) { firstBad = n; }
        });
        return firstBad;
    }

    function focusField(name) {
        var w = wrap(name);
        var input = w && w.querySelector('input:not([type="hidden"])');
        if (input) { input.focus(); }
    }

    /* ---------- Độ mạnh mật khẩu & khớp mật khẩu ---------- */
    function updateMeter() {
        var s = strength(el.password.value, el.username.value.trim());
        el.meter.setAttribute('data-level', String(s.level));
        el.meterLabel.textContent = s.label ? 'Độ mạnh: ' + s.label : meterDefault;
    }

    function updateMatch() {
        var ok = el.password2.value !== '' && el.password2.value === el.password.value;
        el.ok2.hidden = !ok;
    }

    /* ---------- Sự kiện nhập liệu ---------- */
    Object.keys(validators).forEach(function (name) {
        var w = wrap(name);
        if (!w) { return; }
        var input = w.querySelector('input:not([type="hidden"])');
        if (!input) { return; }
        var evt = input.type === 'checkbox' ? 'change' : 'input';

        input.addEventListener('blur', function () {
            if (input.type === 'checkbox') { return; }
            w.setAttribute('data-touched', '1');
            if (name === 'captcha' && !input.value) { return; }
            validateField(name);
        });
        input.addEventListener(evt, function () {
            if (w.getAttribute('data-touched') === '1' || w.classList.contains('has-error')) { validateField(name); }
        });
    });

    el.username.addEventListener('input', function () {
        var v = el.username.value, lower = v.toLowerCase();
        if (v !== lower) {
            var pos = el.username.selectionStart;
            el.username.value = lower;
            try { el.username.setSelectionRange(pos, pos); } catch (e) { /* bỏ qua */ }
        }
        updateMeter();
    });

    el.password.addEventListener('input', function () {
        updateMeter();
        updateMatch();
        if (el.password2.value && wrap('password2').getAttribute('data-touched') === '1') { validateField('password2'); }
    });
    el.password2.addEventListener('input', updateMatch);

    if (el.captcha) {
        el.captcha.addEventListener('input', function () {
            var v = el.captcha.value.toUpperCase();
            if (v !== el.captcha.value) { el.captcha.value = v; }
        });
    }

    /* ---------- Hiện/ẩn mật khẩu ---------- */
    Array.prototype.forEach.call(form.querySelectorAll('.eye'), function (btn) {
        btn.addEventListener('click', function () {
            var input = $(btn.getAttribute('data-toggle'));
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            btn.setAttribute('aria-label', show ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
            btn.querySelector('use').setAttribute('href', show ? '#i-eye-off' : '#i-eye');
        });
    });

    /* ---------- CAPTCHA ---------- */
    function refreshCaptcha(clearInput) {
        if (!el.img) { return; }
        el.img.src = cfg.captchaSrc + '?t=' + Date.now();
        if (clearInput && el.captcha) { el.captcha.value = ''; }
        if (el.refresh) {
            el.refresh.classList.remove('spin');
            void el.refresh.offsetWidth;
            el.refresh.classList.add('spin');
        }
    }
    if (el.refresh) { el.refresh.addEventListener('click', function () { refreshCaptcha(true); if (el.captcha) { el.captcha.focus(); } }); }
    window.addEventListener('pageshow', function (e) { if (e.persisted) { refreshCaptcha(true); } });

    /* ---------- Điều khoản ---------- */
    if (el.terms && el.openTerms) {
        el.openTerms.addEventListener('click', function () {
            if (typeof el.terms.showModal === 'function') { el.terms.showModal(); } else { el.terms.setAttribute('open', ''); }
        });
        var closeTerms = function () {
            if (typeof el.terms.close === 'function') { el.terms.close(); } else { el.terms.removeAttribute('open'); }
        };
        el.closeTerms.addEventListener('click', closeTerms);
        el.terms.addEventListener('click', function (e) { if (e.target === el.terms) { closeTerms(); } });
        el.acceptTerms.addEventListener('click', function () {
            el.agree.checked = true;
            setError('agree', '');
            closeTerms();
            el.agree.focus();
        });
    }

    /* ---------- Gửi biểu mẫu ---------- */
    function setBusy(on) {
        busy = on;
        el.submit.classList.toggle('is-loading', on);
        el.submit.setAttribute('aria-busy', on ? 'true' : 'false');
        el.label.textContent = on ? 'Đang tạo tài khoản…' : 'Tạo tài khoản';
    }

    function clearSecrets() {
        el.password.value = '';
        el.password2.value = '';
        updateMeter();
        updateMatch();
    }

    function postCodeRequest(endpoint, fields, button, successText) {
        if (busy) { return; }
        var emailError = validators.email(); setError('email', emailError);
        if (emailError) { showAlert('error', 'Email chưa hợp lệ', emailError); focusField('email'); return; }
        button.classList.add('is-loading'); button.disabled = true;
        var data = new FormData(); data.append('csrf', el.csrf.value); data.append('email', el.email.value.trim().toLowerCase());
        Object.keys(fields || {}).forEach(function(k) { data.append(k, fields[k]); });
        fetch(endpoint, {method:'POST', body:data, credentials:'same-origin', headers:{'Accept':'application/json','X-Requested-With':'fetch'}})
        .then(function(res){ return res.json().then(function(json){ return {status:res.status,json:json}; }); })
        .then(function(result){ var json=result.json || {}; if(json.csrf) el.csrf.value=json.csrf;
            if(!json.ok){ showAlert('error','Chưa xác minh được email',json.message || 'Vui lòng thử lại.'); if(json.errors){Object.keys(json.errors).forEach(function(k){setError(k,json.errors[k]);});} return; }
            hideAlert(); button.classList.add('is-done'); showAlert('info','Thông báo',successText || json.message || 'Thành công.');
            if(endpoint === cfg.emailVerifyEndpoint){ el.emailVerified.hidden=false; el.email.readOnly=true; el.emailCode.readOnly=true; el.sendEmailCode.disabled=true; el.verifyEmailCode.disabled=true; }
        }).catch(function(){showAlert('error','Không thể kết nối','Kiểm tra mạng rồi thử lại.');})
        .then(function(){button.classList.remove('is-loading'); if(!el.emailVerified || el.emailVerified.hidden){button.disabled=false;}});
    }
    if (el.sendEmailCode) { el.sendEmailCode.addEventListener('click', function(){ postCodeRequest(cfg.emailCodeEndpoint, {}, el.sendEmailCode, 'Đã gửi mã. Kiểm tra hộp thư đến và Spam.'); }); }
    if (el.verifyEmailCode) { el.verifyEmailCode.addEventListener('click', function(){ postCodeRequest(cfg.emailVerifyEndpoint, {email_code:el.emailCode.value.trim()}, el.verifyEmailCode, 'Email đã xác minh.'); }); }

    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        if (busy) { return; }
        hideAlert();

        var bad = validateAll();
        if (bad) {
            showAlert('error', 'Chưa gửi được biểu mẫu', 'Vui lòng kiểm tra lại các trường được đánh dấu màu đỏ.');
            focusField(bad);
            return;
        }

        el.username.value = el.username.value.trim().toLowerCase();
        var data = new FormData(form);
        var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timer = ctrl ? window.setTimeout(function () { ctrl.abort(); }, 20000) : 0;
        var status = 0;

        setBusy(true);

        fetch(cfg.endpoint, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' },
            signal: ctrl ? ctrl.signal : undefined
        }).then(function (res) {
            status = res.status;
            return res.json().catch(function () { return null; });
        }).then(function (json) {
            window.clearTimeout(timer);
            setBusy(false);

            if (!json) {
                showAlert('error', 'Máy chủ phản hồi không hợp lệ', 'Vui lòng thử lại sau ít phút.');
                return;
            }
            if (json.csrf && el.csrf) { el.csrf.value = json.csrf; }

            if (json.ok) { onSuccess(json); return; }
            onFailure(json, status);
        }).catch(function () {
            window.clearTimeout(timer);
            setBusy(false);
            showAlert('error', 'Không thể kết nối tới máy chủ', 'Hãy kiểm tra kết nối mạng rồi thử lại.');
        });
    });

    function onFailure(json, status) {
        clearErrors();
        var first = null;
        var errs = json.errors || {};
        ['username', 'phone', 'email', 'email_code', 'password', 'password2', 'captcha', 'agree'].forEach(function (n) {
            if (errs[n]) {
                setError(n, errs[n]);
                if (!first) { first = n; }
            }
        });

        var type = status === 429 || json.code === 'rate_limit' ? 'warn' : 'error';
        var title = json.code === 'csrf' ? 'Phiên làm việc đã hết hạn'
            : status === 429 ? 'Vui lòng thử lại sau'
            : first ? 'Chưa tạo được tài khoản'
            : 'Có lỗi xảy ra';
        showAlert(type, title, json.message || 'Vui lòng thử lại.');

        // Giữ lại dữ liệu hợp lệ, xoá mật khẩu; mã CAPTCHA đã bị huỷ nên đổi ảnh mới.
        clearSecrets();
        refreshCaptcha(true);
        focusField(first || 'password');
    }

    function onSuccess(json) {
        clearErrors();
        clearSecrets();
        el.successUser.textContent = json.username || el.username.value;
        el.formView.hidden = true;
        el.successView.hidden = false;
        el.successTitle.focus();
        window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
    }

    if (el.again) {
        el.again.addEventListener('click', function () {
            var token = el.csrf ? el.csrf.value : '';
            form.reset();               // reset() sẽ đưa token ẩn về giá trị cũ -> khôi phục token mới nhất
            if (el.email) { el.email.readOnly = false; }
            if (el.emailCode) { el.emailCode.readOnly = false; }
            if (el.emailVerified) { el.emailVerified.hidden = true; }
            if (el.sendEmailCode) { el.sendEmailCode.disabled = false; el.sendEmailCode.classList.remove('is-done'); }
            if (el.verifyEmailCode) { el.verifyEmailCode.disabled = false; el.verifyEmailCode.classList.remove('is-done'); }
            if (el.csrf) { el.csrf.value = token; }
            clearErrors();
            hideAlert();
            updateMeter();
            updateMatch();
            Array.prototype.forEach.call(form.querySelectorAll('[data-touched]'), function (n) { n.removeAttribute('data-touched'); });
            refreshCaptcha(true);
            el.successView.hidden = true;
            el.formView.hidden = false;
            el.username.focus();
        });
    }
})();
