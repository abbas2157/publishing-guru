// Admin: mobile sidebar toggle.
(function () {
    var body = document.body;
    function set(open) { body.classList.toggle('sidebar-open', open); }
    document.querySelectorAll('[data-sidebar-open]').forEach(function (el) {
        el.addEventListener('click', function () { set(true); });
    });
    document.querySelectorAll('[data-sidebar-close]').forEach(function (el) {
        el.addEventListener('click', function () { set(false); });
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') set(false); });
})();
