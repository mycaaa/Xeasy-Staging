(function () {
    var needsPlugins =
        document.querySelectorAll("[toast-list]").length ||
        document.querySelectorAll("[data-choices]").length ||
        document.querySelectorAll("[data-provider]").length;

    if (!needsPlugins) return;

    function loadScript(src) {
        var s = document.createElement("script");
        s.type = "text/javascript";
        s.src = src;
        document.head.appendChild(s);
    }

    loadScript("https://cdn.jsdelivr.net/npm/toastify-js");
    loadScript("assets/libs/choices.js/public/assets/scripts/choices.min.js");
    loadScript("assets/libs/flatpickr/flatpickr.min.js");
})();
