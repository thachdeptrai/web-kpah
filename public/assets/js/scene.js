/* Hiệu ứng tàn lửa/ánh sáng bay nhẹ ở nền. Tắt khi người dùng chọn giảm chuyển động. */
(function () {
    'use strict';
    var canvas = document.getElementById('embers');
    if (!canvas || !canvas.getContext) { return; }
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }

    var ctx = canvas.getContext('2d');
    var dpr = Math.min(window.devicePixelRatio || 1, 1.5);
    var w = 0, h = 0, parts = [], raf = 0;

    function rand(a, b) { return a + Math.random() * (b - a); }

    function make(initial) {
        var gold = Math.random() < 0.65;
        return {
            x: rand(0, w),
            y: initial ? rand(0, h) : h + 10,
            r: rand(0.6, 2),
            vy: rand(0.12, 0.45),
            sway: rand(0.2, 0.8),
            phase: rand(0, 6.28),
            tw: rand(0.6, 1.6),
            c: gold ? '255,214,140' : '150,165,255'
        };
    }

    function resize() {
        w = window.innerWidth; h = window.innerHeight;
        canvas.width = Math.floor(w * dpr); canvas.height = Math.floor(h * dpr);
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        var n = Math.max(14, Math.min(46, Math.floor(w / 32)));
        parts = [];
        for (var i = 0; i < n; i++) { parts.push(make(true)); }
    }

    function frame(t) {
        ctx.clearRect(0, 0, w, h);
        for (var i = 0; i < parts.length; i++) {
            var p = parts[i];
            p.y -= p.vy;
            var x = p.x + Math.sin(t / 1000 * p.sway + p.phase) * 14;
            var a = 0.25 + 0.55 * (0.5 + 0.5 * Math.sin(t / 1000 * p.tw + p.phase));
            ctx.beginPath();
            ctx.fillStyle = 'rgba(' + p.c + ',' + a.toFixed(3) + ')';
            ctx.shadowColor = 'rgba(' + p.c + ',.8)';
            ctx.shadowBlur = 8;
            ctx.arc(x, p.y, p.r, 0, 6.2832);
            ctx.fill();
            if (p.y < -10) { parts[i] = make(false); }
        }
        raf = window.requestAnimationFrame(frame);
    }

    function start() { if (!raf) { raf = window.requestAnimationFrame(frame); } }
    function stop() { if (raf) { window.cancelAnimationFrame(raf); raf = 0; } }

    resize();
    start();
    window.addEventListener('resize', resize);
    document.addEventListener('visibilitychange', function () { if (document.hidden) { stop(); } else { start(); } });
})();
