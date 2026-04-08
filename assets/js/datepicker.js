(function () {
    var MIN_YEAR = 1900;
    var MONTHS = [
        "Січень", "Лютий", "Березень", "Квітень", "Травень", "Червень",
        "Липень", "Серпень", "Вересень", "Жовтень", "Листопад", "Грудень"
    ];

    var today = startOfDay(new Date());
    var todayMonthStart = new Date(today.getFullYear(), today.getMonth(), 1);
    var stateMap = new WeakMap();
    var popup = null;
    var popupElements = {};
    var activeState = null;

    function startOfDay(date) {
        return new Date(date.getFullYear(), date.getMonth(), date.getDate());
    }

    function parseDotDate(value) {
        var match = String(value || "").trim().match(/^(\d{2})\.(\d{2})\.(\d{4})$/);
        if (!match) return null;
        var day = Number(match[1]);
        var month = Number(match[2]) - 1;
        var year = Number(match[3]);
        var date = new Date(year, month, day);
        if (date.getFullYear() !== year || date.getMonth() !== month || date.getDate() !== day) return null;
        return date;
    }

    function parseIsoDate(value) {
        var match = String(value || "").trim().match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!match) return null;
        var year = Number(match[1]);
        var month = Number(match[2]) - 1;
        var day = Number(match[3]);
        var date = new Date(year, month, day);
        if (date.getFullYear() !== year || date.getMonth() !== month || date.getDate() !== day) return null;
        return date;
    }

    function parseAnyDate(value) {
        return parseDotDate(value) || parseIsoDate(value);
    }

    function formatDotDate(date) {
        var d = String(date.getDate()).padStart(2, "0");
        var m = String(date.getMonth() + 1).padStart(2, "0");
        return d + "." + m + "." + date.getFullYear();
    }

    function formatIsoDate(date) {
        var d = String(date.getDate()).padStart(2, "0");
        var m = String(date.getMonth() + 1).padStart(2, "0");
        return date.getFullYear() + "-" + m + "-" + d;
    }

    function isFutureDate(date) {
        return startOfDay(date) > today;
    }

    function sameDay(a, b) {
        return !!a && !!b &&
            a.getFullYear() === b.getFullYear() &&
            a.getMonth() === b.getMonth() &&
            a.getDate() === b.getDate();
    }

    function sanitizeViewDate(year, month) {
        var view = new Date(year, month, 1);
        if (view.getFullYear() < MIN_YEAR) view = new Date(MIN_YEAR, 0, 1);
        if (view > todayMonthStart) view = new Date(todayMonthStart.getFullYear(), todayMonthStart.getMonth(), 1);
        return view;
    }

    function buildPopup() {
        if (popup) return;

        popup = document.createElement("div");
        popup.className = "xdp-popup";
        popup.setAttribute("aria-hidden", "true");
        popup.innerHTML =
            '<div class="xdp-head">' +
                '<button type="button" class="xdp-arrow xdp-prev" aria-label="Попередній місяць">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M15 6l-6 6l6 6"></path></svg>' +
                '</button>' +
                '<div class="xdp-pickers">' +
                    '<div class="xdp-select" data-kind="month">' +
                        '<button type="button" class="xdp-select-btn" aria-expanded="false" aria-label="Вибір місяця"></button>' +
                        '<div class="xdp-options" role="listbox"></div>' +
                    '</div>' +
                    '<div class="xdp-select" data-kind="year">' +
                        '<button type="button" class="xdp-select-btn" aria-expanded="false" aria-label="Вибір року"></button>' +
                        '<div class="xdp-options" role="listbox"></div>' +
                    '</div>' +
                '</div>' +
                '<button type="button" class="xdp-arrow xdp-next" aria-label="Наступний місяць">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M9 6l6 6l-6 6"></path></svg>' +
                '</button>' +
            '</div>' +
            '<div class="xdp-weekdays"><span>Пн</span><span>Вт</span><span>Ср</span><span>Чт</span><span>Пт</span><span>Сб</span><span>Нд</span></div>' +
            '<div class="xdp-grid"></div>' +
            '<div class="xdp-actions">' +
                '<button type="button" class="xdp-today">Сьогодні</button>' +
                '<button type="button" class="xdp-clear">Очистити</button>' +
            '</div>';

        document.body.appendChild(popup);

        popupElements.prev = popup.querySelector(".xdp-prev");
        popupElements.next = popup.querySelector(".xdp-next");
        popupElements.grid = popup.querySelector(".xdp-grid");
        popupElements.today = popup.querySelector(".xdp-today");
        popupElements.clear = popup.querySelector(".xdp-clear");
        popupElements.monthSelect = popup.querySelector('.xdp-select[data-kind="month"]');
        popupElements.yearSelect = popup.querySelector('.xdp-select[data-kind="year"]');
        popupElements.monthBtn = popupElements.monthSelect.querySelector(".xdp-select-btn");
        popupElements.yearBtn = popupElements.yearSelect.querySelector(".xdp-select-btn");
        popupElements.monthOptions = popupElements.monthSelect.querySelector(".xdp-options");
        popupElements.yearOptions = popupElements.yearSelect.querySelector(".xdp-options");

        popup.addEventListener("pointerdown", function (event) {
            event.stopPropagation();
        });

        popup.addEventListener("click", function (event) {
            event.stopPropagation();
            if (!event.target.closest(".xdp-select")) {
                closeSelectMenus();
            }
        });

        popupElements.prev.addEventListener("click", function () {
            if (!activeState) return;
            activeState.viewDate = sanitizeViewDate(activeState.viewDate.getFullYear(), activeState.viewDate.getMonth() - 1);
            renderPopup();
        });

        popupElements.next.addEventListener("click", function () {
            if (!activeState) return;
            activeState.viewDate = sanitizeViewDate(activeState.viewDate.getFullYear(), activeState.viewDate.getMonth() + 1);
            renderPopup();
        });

        popupElements.today.addEventListener("click", function () {
            if (!activeState) return;
            activeState.selectedDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
            activeState.viewDate = new Date(today.getFullYear(), today.getMonth(), 1);
            applyDateToInput(activeState);
            renderPopup();
            closePopup();
        });

        popupElements.clear.addEventListener("click", function () {
            if (!activeState) return;
            clearInputValue(activeState);
            renderPopup();
            closePopup();
        });

        popupElements.monthBtn.addEventListener("click", function (event) {
            event.stopPropagation();
            toggleSelectMenu("month");
        });

        popupElements.yearBtn.addEventListener("click", function (event) {
            event.stopPropagation();
            toggleSelectMenu("year");
        });

        window.addEventListener("resize", function () {
            if (activeState) positionPopup(activeState.input);
        });

        window.addEventListener("scroll", function () {
            if (activeState) positionPopup(activeState.input);
        }, true);
    }

    function closeSelectMenus() {
        popupElements.monthOptions.classList.remove("is-open");
        popupElements.yearOptions.classList.remove("is-open");
        popupElements.monthBtn.setAttribute("aria-expanded", "false");
        popupElements.yearBtn.setAttribute("aria-expanded", "false");
    }

    function toggleSelectMenu(kind) {
        var openMonth = kind === "month";
        var targetOptions = openMonth ? popupElements.monthOptions : popupElements.yearOptions;
        var targetBtn = openMonth ? popupElements.monthBtn : popupElements.yearBtn;
        var otherOptions = openMonth ? popupElements.yearOptions : popupElements.monthOptions;
        var otherBtn = openMonth ? popupElements.yearBtn : popupElements.monthBtn;

        otherOptions.classList.remove("is-open");
        otherBtn.setAttribute("aria-expanded", "false");

        var shouldOpen = !targetOptions.classList.contains("is-open");
        targetOptions.classList.toggle("is-open", shouldOpen);
        targetBtn.setAttribute("aria-expanded", shouldOpen ? "true" : "false");
    }

    function fillMonthOptions() {
        popupElements.monthOptions.innerHTML = "";
        var selectedYear = activeState.viewDate.getFullYear();

        for (var m = 0; m < MONTHS.length; m++) {
            var option = document.createElement("button");
            option.type = "button";
            option.className = "xdp-option";
            option.textContent = MONTHS[m];

            if (m === activeState.viewDate.getMonth()) option.classList.add("is-selected");
            if (selectedYear === today.getFullYear() && m > today.getMonth()) {
                option.disabled = true;
                option.classList.add("is-disabled");
            } else {
                option.addEventListener("click", monthClickHandler(m));
            }

            popupElements.monthOptions.appendChild(option);
        }
    }

    function monthClickHandler(month) {
        return function (event) {
            event.stopPropagation();
            if (!activeState) return;
            activeState.viewDate = sanitizeViewDate(activeState.viewDate.getFullYear(), month);
            renderPopup();
            closeSelectMenus();
        };
    }

    function fillYearOptions() {
        popupElements.yearOptions.innerHTML = "";

        for (var year = today.getFullYear(); year >= MIN_YEAR; year--) {
            var option = document.createElement("button");
            option.type = "button";
            option.className = "xdp-option";
            option.textContent = String(year);
            if (year === activeState.viewDate.getFullYear()) option.classList.add("is-selected");
            option.addEventListener("click", yearClickHandler(year));
            popupElements.yearOptions.appendChild(option);
        }
    }

    function yearClickHandler(year) {
        return function (event) {
            event.stopPropagation();
            if (!activeState) return;
            var targetMonth = activeState.viewDate.getMonth();
            if (year === today.getFullYear() && targetMonth > today.getMonth()) {
                targetMonth = today.getMonth();
            }
            activeState.viewDate = sanitizeViewDate(year, targetMonth);
            renderPopup();
            closeSelectMenus();
        };
    }

    function renderPopup() {
        if (!activeState) return;

        popupElements.monthBtn.textContent = MONTHS[activeState.viewDate.getMonth()];
        popupElements.yearBtn.textContent = String(activeState.viewDate.getFullYear());

        fillMonthOptions();
        fillYearOptions();
        popupElements.grid.innerHTML = "";

        var first = new Date(activeState.viewDate.getFullYear(), activeState.viewDate.getMonth(), 1);
        var startWeekday = (first.getDay() + 6) % 7;
        var currentMonthDays = new Date(activeState.viewDate.getFullYear(), activeState.viewDate.getMonth() + 1, 0).getDate();
        var prevMonthDays = new Date(activeState.viewDate.getFullYear(), activeState.viewDate.getMonth(), 0).getDate();

        for (var i = 0; i < 42; i++) {
            var dayNumber;
            var cellDate;
            var isOutside = false;

            if (i < startWeekday) {
                dayNumber = prevMonthDays - startWeekday + i + 1;
                cellDate = new Date(activeState.viewDate.getFullYear(), activeState.viewDate.getMonth() - 1, dayNumber);
                isOutside = true;
            } else if (i >= startWeekday + currentMonthDays) {
                dayNumber = i - (startWeekday + currentMonthDays) + 1;
                cellDate = new Date(activeState.viewDate.getFullYear(), activeState.viewDate.getMonth() + 1, dayNumber);
                isOutside = true;
            } else {
                dayNumber = i - startWeekday + 1;
                cellDate = new Date(activeState.viewDate.getFullYear(), activeState.viewDate.getMonth(), dayNumber);
            }

            var dayBtn = document.createElement("button");
            dayBtn.type = "button";
            dayBtn.className = "xdp-day";
            dayBtn.textContent = String(dayNumber);

            if (isOutside) dayBtn.classList.add("is-outside");
            if (sameDay(cellDate, today)) dayBtn.classList.add("is-today");
            if (sameDay(cellDate, activeState.selectedDate)) dayBtn.classList.add("is-selected");

            if (isFutureDate(cellDate)) {
                dayBtn.disabled = true;
                dayBtn.classList.add("is-disabled");
            } else {
                dayBtn.addEventListener("click", dayClickHandler(cellDate));
            }

            popupElements.grid.appendChild(dayBtn);
        }

        popupElements.next.disabled = activeState.viewDate.getFullYear() === todayMonthStart.getFullYear() &&
            activeState.viewDate.getMonth() === todayMonthStart.getMonth();
    }

    function dayClickHandler(date) {
        return function () {
            if (!activeState) return;
            activeState.selectedDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
            activeState.viewDate = new Date(date.getFullYear(), date.getMonth(), 1);
            applyDateToInput(activeState);
            closePopup();
        };
    }

    function applyDateToInput(state) {
        if (!state || !state.input || !state.selectedDate) return;
        var formattedDot = formatDotDate(state.selectedDate);
        state.input.value = formattedDot;
        state.lastValid = new Date(state.selectedDate.getFullYear(), state.selectedDate.getMonth(), state.selectedDate.getDate());
        setInputValidity(state.input, "");

        if (state.storage === "iso" && state.hiddenInput) {
            state.hiddenInput.value = formatIsoDate(state.selectedDate);
        }
    }

    function clearInputValue(state) {
        if (!state || !state.input) return;
        state.input.value = "";
        state.selectedDate = null;
        state.lastValid = null;
        setInputValidity(state.input, "");
        if (state.storage === "iso" && state.hiddenInput) {
            state.hiddenInput.value = "";
        }
    }

    function setInputValidity(input, message) {
        if (typeof input.setCustomValidity === "function") {
            input.setCustomValidity(message || "");
        }
    }

    function normalizeInputMask(input) {
        var value = input.value.replace(/[^\d]/g, "").slice(0, 8);
        if (value.length >= 5) value = value.replace(/(\d{2})(\d{2})(\d+)/, "$1.$2.$3");
        else if (value.length >= 3) value = value.replace(/(\d{2})(\d+)/, "$1.$2");
        input.value = value;
    }

    function syncStateFromInput(state, strict) {
        var raw = state.input.value.trim();
        if (!raw) {
            if (state.storage === "iso" && state.hiddenInput) state.hiddenInput.value = "";
            state.selectedDate = null;
            if (strict) state.lastValid = null;
            setInputValidity(state.input, "");
            return;
        }

        var parsed = parseDotDate(raw) || parseIsoDate(raw);
        if (!parsed || isFutureDate(parsed)) {
            if (strict) {
                if (state.lastValid) {
                    state.selectedDate = new Date(state.lastValid.getFullYear(), state.lastValid.getMonth(), state.lastValid.getDate());
                    state.input.value = formatDotDate(state.selectedDate);
                } else {
                    state.selectedDate = null;
                    state.input.value = "";
                    if (state.storage === "iso" && state.hiddenInput) state.hiddenInput.value = "";
                }
            }
            setInputValidity(state.input, "Введіть коректну дату, не пізніше сьогодні.");
            return;
        }

        state.selectedDate = new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate());
        state.lastValid = new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate());
        state.input.value = formatDotDate(parsed);
        if (state.storage === "iso" && state.hiddenInput) {
            state.hiddenInput.value = formatIsoDate(parsed);
        }
        setInputValidity(state.input, "");
    }

    function positionPopup(input) {
        if (!popup || !input) return;
        var rect = input.getBoundingClientRect();
        var scrollY = window.pageYOffset || document.documentElement.scrollTop;
        var scrollX = window.pageXOffset || document.documentElement.scrollLeft;

        popup.style.visibility = "hidden";
        popup.classList.add("is-open");
        var popupRect = popup.getBoundingClientRect();
        var popupHeight = popupRect.height || 310;
        var popupWidth = popupRect.width || 320;

        var top = rect.bottom + scrollY + 8;
        var left = rect.left + scrollX;

        if (window.innerHeight - rect.bottom < popupHeight + 10 && rect.top > popupHeight + 10) {
            top = rect.top + scrollY - popupHeight - 8;
        }

        if (left + popupWidth > scrollX + window.innerWidth - 10) {
            left = scrollX + window.innerWidth - popupWidth - 10;
        }
        if (left < scrollX + 10) left = scrollX + 10;

        popup.style.top = top + "px";
        popup.style.left = left + "px";
        popup.style.visibility = "visible";
    }

    function openPopupForInput(input) {
        var state = stateMap.get(input);
        if (!state) return;
        buildPopup();

        syncStateFromInput(state, true);
        state.viewDate = state.selectedDate
            ? new Date(state.selectedDate.getFullYear(), state.selectedDate.getMonth(), 1)
            : sanitizeViewDate(today.getFullYear(), today.getMonth());

        activeState = state;
        popup.classList.add("is-open");
        popup.setAttribute("aria-hidden", "false");
        closeSelectMenus();
        renderPopup();
        positionPopup(input);
    }

    function closePopup() {
        if (!popup) return;
        popup.classList.remove("is-open");
        popup.setAttribute("aria-hidden", "true");
        closeSelectMenus();
        activeState = null;
    }

    function attachFormSync(input, state) {
        if (state.storage !== "iso" || !input.form) return;
        var form = input.form;
        if (form.dataset.dpBound === "1") return;
        form.dataset.dpBound = "1";

        form.addEventListener("submit", function () {
            var managedInputs = form.querySelectorAll("input[data-dp-init='1']");
            managedInputs.forEach(function (managedInput) {
                var managedState = stateMap.get(managedInput);
                if (!managedState) return;
                syncStateFromInput(managedState, true);
            });
        });
    }

    function prepareNativeDateInput(input) {
        var originalName = input.getAttribute("name");
        var hidden = document.createElement("input");
        hidden.type = "hidden";
        hidden.name = originalName || "";
        hidden.value = input.value || "";
        if (input.disabled) hidden.disabled = true;

        input.insertAdjacentElement("afterend", hidden);
        input.removeAttribute("name");
        if (originalName) input.name = originalName + "_display";

        var parsed = parseIsoDate(hidden.value);
        input.type = "text";
        input.inputMode = "numeric";
        if (!input.placeholder) input.placeholder = "дд.мм.рррр";
        input.value = parsed ? formatDotDate(parsed) : "";

        return hidden;
    }

    function prepareLegacyDatepickerInput(input) {
        var holder = input.closest(".datepicker");
        if (!holder) return;
        var legacy = holder.querySelector(".calendar.popup");
        if (legacy) {
            legacy.style.display = "none";
            legacy.setAttribute("aria-hidden", "true");
        }
    }

    function initInput(input) {
        if (!input || input.dataset.dpInit === "1" || input.dataset.noCustomDatepicker === "1") return;

        var hiddenInput = null;
        var storage = "dot";
        var isNative = input.type === "date";

        if (isNative) {
            hiddenInput = prepareNativeDateInput(input);
            storage = "iso";
        } else {
            prepareLegacyDatepickerInput(input);
            var parsedExisting = parseAnyDate(input.value);
            if (parsedExisting) input.value = formatDotDate(parsedExisting);
            if (!input.placeholder) input.placeholder = "дд.мм.рррр";
            input.inputMode = "numeric";
        }

        var initialParsed = storage === "iso" && hiddenInput
            ? parseIsoDate(hiddenInput.value)
            : parseAnyDate(input.value);

        var state = {
            input: input,
            hiddenInput: hiddenInput,
            storage: storage,
            selectedDate: initialParsed ? new Date(initialParsed.getFullYear(), initialParsed.getMonth(), initialParsed.getDate()) : null,
            lastValid: initialParsed ? new Date(initialParsed.getFullYear(), initialParsed.getMonth(), initialParsed.getDate()) : null,
            viewDate: initialParsed
                ? new Date(initialParsed.getFullYear(), initialParsed.getMonth(), 1)
                : sanitizeViewDate(today.getFullYear(), today.getMonth())
        };

        input.dataset.dpInit = "1";
        stateMap.set(input, state);

        attachFormSync(input, state);

        input.addEventListener("focus", function () {
            openPopupForInput(input);
        });

        input.addEventListener("click", function () {
            openPopupForInput(input);
        });

        input.addEventListener("input", function () {
            normalizeInputMask(input);
            syncStateFromInput(state, false);
            if (activeState && activeState.input === input) {
                state.viewDate = state.selectedDate
                    ? new Date(state.selectedDate.getFullYear(), state.selectedDate.getMonth(), 1)
                    : state.viewDate;
                renderPopup();
                positionPopup(input);
            }
        });

        input.addEventListener("blur", function () {
            setTimeout(function () {
                syncStateFromInput(state, true);
            }, 40);
        });

        input.addEventListener("keydown", function (event) {
            var allowed = /[0-9]/.test(event.key) ||
                event.key === "Backspace" ||
                event.key === "Delete" ||
                event.key === "ArrowLeft" ||
                event.key === "ArrowRight" ||
                event.key === "Tab" ||
                event.key === "Enter" ||
                event.key === "Escape";

            if (!allowed) event.preventDefault();

            if (event.key === "Escape") {
                closePopup();
            }
        });
    }

    function collectInputs(root) {
        var list = [];
        root.querySelectorAll(".datepicker .date-input").forEach(function (input) {
            if (input.dataset.noCustomDatepicker !== "1") list.push(input);
        });
        root.querySelectorAll('input[type="date"]').forEach(function (input) {
            if (input.dataset.noCustomDatepicker !== "1") list.push(input);
        });
        return list;
    }

    function initDatepicker(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var inputs = collectInputs(scope);
        inputs.forEach(initInput);
    }

    document.addEventListener("pointerdown", function (event) {
        if (!popup || !popup.classList.contains("is-open")) return;
        if (popup.contains(event.target)) return;
        if (activeState && activeState.input === event.target) return;
        closePopup();
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") closePopup();
    });

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function () {
            initDatepicker(document);
        });
    } else {
        initDatepicker(document);
    }

    window.initDatepicker = initDatepicker;
})();
