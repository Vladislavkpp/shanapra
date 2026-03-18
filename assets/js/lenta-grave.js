(function () {
    var flash = document.querySelector("[data-lenta-test-flash]");
    if (flash) {
        window.setTimeout(function () {
            flash.classList.add("is-leaving");
            window.setTimeout(function () {
                if (flash.parentNode) {
                    flash.parentNode.removeChild(flash);
                }
            }, 260);
        }, 3200);
    }

    var forms = document.querySelectorAll("[data-lenta-test-form]");
    if (!forms.length) {
        return;
    }

    forms.forEach(function (form) {
        var textarea = form.querySelector("textarea[name='message']");
        var counter = form.querySelector("[data-lenta-test-counter]");
        var submit = form.querySelector("[data-lenta-test-submit]");
        if (!textarea || !counter || !submit) {
            return;
        }

        function resizeTextarea() {
            textarea.style.height = "auto";
            var nextHeight = Math.min(textarea.scrollHeight, 220);
            textarea.style.height = String(nextHeight) + "px";
            textarea.style.overflowY = textarea.scrollHeight > 220 ? "auto" : "hidden";
        }

        function syncState() {
            var length = textarea.value.length;
            counter.textContent = String(length) + " / 2000";
            counter.classList.toggle("is-limit", length > 2000);
            submit.disabled = length === 0 || length > 2000;
        }

        textarea.addEventListener("input", function () {
            resizeTextarea();
            syncState();
        });

        form.addEventListener("submit", function (event) {
            syncState();
            if (submit.disabled) {
                event.preventDefault();
                return;
            }

            submit.disabled = true;
            submit.textContent = "Публікація...";
        });

        resizeTextarea();
        syncState();
    });
})();
