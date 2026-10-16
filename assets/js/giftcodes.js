'use strict';
document.addEventListener('click', async function (event) {
    const button = event.target.closest('[data-gift-code]');
    if (!button) return;
    const code = button.getAttribute('data-gift-code');
    if (!code || !/^[A-Za-z0-9_-]{4,50}$/.test(code)) return;
    const original = button.textContent;
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(code);
        } else {
            const input = document.createElement('textarea');
            input.value = code;
            input.setAttribute('readonly', '');
            input.style.position = 'fixed';
            input.style.opacity = '0';
            document.body.appendChild(input);
            input.select();
            const ok = document.execCommand('copy');
            input.remove();
            if (!ok) throw new Error('clipboard unavailable');
        }
        button.textContent = 'Đã sao chép ✓';
    } catch (error) {
        button.textContent = 'Chọn mã để chép';
        const field = button.parentElement.querySelector('code');
        if (field) {
            const range = document.createRange();
            range.selectNodeContents(field);
            const selection = window.getSelection();
            if (selection) { selection.removeAllRanges(); selection.addRange(range); }
        }
    }
    button.addEventListener('blur', function () { button.textContent = original; }, { once: true });
});
