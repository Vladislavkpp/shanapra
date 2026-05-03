document.addEventListener("DOMContentLoaded", function () {
    const root = document.getElementById("admin-tools-root");
    if (!root) {
        return;
    }

    const navLinks = Array.from(root.querySelectorAll("[data-admin-tool-tab]"));
    const tabLinks = Array.from(root.querySelectorAll("[data-admin-tool-tab-link]"));
    const panels = Array.from(root.querySelectorAll("[data-admin-tool-panel]"));
    const heroTitle = root.querySelector("[data-admin-tools-hero-title]");
    const heroSubtitle = root.querySelector("[data-admin-tools-hero-subtitle]");
    const heroBadgePrimary = root.querySelector("[data-admin-tools-hero-badge-primary]");
    const heroBadgeSecondary = root.querySelector("[data-admin-tools-hero-badge-secondary]");
    let chartsInitialized = false;
    let transactionsRequestId = 0;

    function parseJson(value) {
        try {
            return JSON.parse(value || "{}");
        } catch (error) {
            return {};
        }
    }

    const chartData = parseJson(root.dataset.chart);
    const heroConfig = parseJson(root.dataset.hero);
    const titlePrefix = (document.title || "ІПС Shana").split("|")[0].trim() || "ІПС Shana";

    function initStatsCharts() {
        if (chartsInitialized || typeof ApexCharts === "undefined") {
            return;
        }

        const statsPanel = root.querySelector("[data-admin-tool-panel='stats']");
        if (!statsPanel || statsPanel.hidden) {
            return;
        }

        const flowChartEl = statsPanel.querySelector("#adminToolsFlowChart");
        if (flowChartEl) {
            new ApexCharts(flowChartEl, {
                chart: {
                    type: "area",
                    height: 340,
                    toolbar: { show: false },
                    fontFamily: "Manrope, Segoe UI, sans-serif"
                },
                states: {
                    hover: { filter: { type: "none" } },
                    active: {
                        allowMultipleDataPointsSelection: false,
                        filter: { type: "none" }
                    }
                },
                series: [
                    { name: "Внутрішні надходження", data: chartData.internal_income || [] },
                    { name: "Внутрішні витрати", data: chartData.internal_expense || [] },
                    { name: "UAH надходження", data: chartData.uah_income || [] },
                    { name: "UAH витрати", data: chartData.uah_expense || [] }
                ],
                colors: ["#177245", "#bf3f4d", "#1e5aa7", "#7c8aa5"],
                stroke: { curve: "smooth", width: 3 },
                fill: {
                    type: "gradient",
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.28,
                        opacityTo: 0.05,
                        stops: [0, 95, 100]
                    }
                },
                dataLabels: { enabled: false },
                xaxis: {
                    categories: chartData.labels || [],
                    labels: { style: { colors: "#5f7286" } }
                },
                yaxis: {
                    labels: { style: { colors: "#5f7286" } }
                },
                grid: {
                    borderColor: "#d9e4ee",
                    strokeDashArray: 4
                },
                legend: {
                    position: "top",
                    horizontalAlign: "left"
                },
                tooltip: {
                    shared: true,
                    intersect: false
                }
            }).render();
        }

        const currencyChartEl = statsPanel.querySelector("#adminToolsCurrencyChart");
        if (currencyChartEl) {
            new ApexCharts(currencyChartEl, {
                chart: {
                    type: "donut",
                    height: 320,
                    toolbar: { show: false },
                    fontFamily: "Manrope, Segoe UI, sans-serif"
                },
                states: {
                    hover: { filter: { type: "none" } },
                    active: {
                        allowMultipleDataPointsSelection: false,
                        filter: { type: "none" }
                    }
                },
                series: chartData.currency_series || [],
                labels: chartData.currency_labels || [],
                plotOptions: {
                    pie: {
                        expandOnClick: false
                    }
                },
                colors: ["#184879", "#4f7db8"],
                legend: { position: "bottom" },
                dataLabels: { enabled: true },
                stroke: { colors: ["#ffffff"] }
            }).render();
        }

        const rolesChartEl = statsPanel.querySelector("#adminToolsRolesChart");
        if (rolesChartEl) {
            new ApexCharts(rolesChartEl, {
                chart: {
                    type: "bar",
                    height: 320,
                    toolbar: { show: false },
                    fontFamily: "Manrope, Segoe UI, sans-serif"
                },
                states: {
                    hover: { filter: { type: "none" } },
                    active: {
                        allowMultipleDataPointsSelection: false,
                        filter: { type: "none" }
                    }
                },
                series: [{
                    name: "Кількість",
                    data: chartData.role_series || []
                }],
                xaxis: {
                    categories: chartData.role_labels || [],
                    labels: { style: { colors: "#5f7286" } }
                },
                yaxis: {
                    labels: { style: { colors: "#5f7286" } }
                },
                plotOptions: {
                    bar: {
                        borderRadius: 8,
                        columnWidth: "48%"
                    }
                },
                colors: ["#173d64"],
                dataLabels: { enabled: false },
                grid: {
                    borderColor: "#d9e4ee",
                    strokeDashArray: 4
                }
            }).render();
        }

        chartsInitialized = true;
    }

    function updateHero(toolName) {
        const meta = heroConfig[toolName] || heroConfig.accounting || {};
        if (heroTitle) {
            heroTitle.textContent = meta.title || "Бухгалтерія";
        }
        if (heroSubtitle) {
            heroSubtitle.textContent = meta.subtitle || "";
        }
        if (heroBadgePrimary) {
            heroBadgePrimary.textContent = meta.badge_primary || "";
        }
        if (heroBadgeSecondary) {
            heroBadgeSecondary.textContent = meta.badge_secondary || "";
        }
    }

    function updatePageTitle(toolName) {
        const meta = heroConfig[toolName] || heroConfig.accounting || {};
        const nextTitle = (meta.title || "Бухгалтерія").trim();
        document.title = titlePrefix + " | " + nextTitle;
    }

    function getPanel(toolName) {
        return root.querySelector("[data-admin-tool-panel='" + toolName + "']");
    }

    function setActiveTool(toolName, pushState) {
        root.dataset.activeTool = toolName;

        navLinks.forEach(function (link) {
            const isActive = link.dataset.adminToolTab === toolName;
            link.classList.toggle("active", isActive);
            link.classList.toggle("is-active", isActive);
            link.setAttribute("aria-current", isActive ? "page" : "false");
        });

        panels.forEach(function (panel) {
            const isActive = panel.dataset.adminToolPanel === toolName;
            panel.hidden = !isActive;
            panel.classList.toggle("is-active", isActive);
        });

        updateHero(toolName);
        updatePageTitle(toolName);

        if (toolName === "stats") {
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(initStatsCharts);
            });
        }

        if (pushState) {
            const activeLink = navLinks.find(function (link) {
                return link.dataset.adminToolTab === toolName;
            });
            if (activeLink) {
                window.history.pushState({ adminTool: toolName }, "", activeLink.href);
            }
        }
    }

    function toolFromLocation() {
        const path = (window.location.pathname || "").replace(/\/+$/, "");
        if (/\/accounting\/stats$/.test(path)) {
            return "stats";
        }
        if (/\/accounting\/wallets$/.test(path)) {
            return "wallets";
        }
        if (/\/accounting\/transactions$/.test(path)) {
            return "transactions";
        }
        if (/\/accounting\/accruals$/.test(path)) {
            return "accruals";
        }
        return "accounting";
    }

    function updateTransactionFilterPills(panel) {
        if (!panel) {
            return;
        }

        const currentUrl = new URL(window.location.href);
        const currentCurrency = (currentUrl.searchParams.get("currency") || "internal").toLowerCase();

        Array.from(panel.querySelectorAll("[data-transactions-currency-link]")).forEach(function (pill) {
            pill.classList.toggle("is-active", pill.dataset.transactionsCurrencyLink === currentCurrency);
        });
    }

    function serializeFilterForm(form) {
        const formData = new FormData(form);
        const params = new URLSearchParams();
        formData.forEach(function (value, key) {
            const normalizedValue = String(value || "").trim();
            if (normalizedValue !== "") {
                params.set(key, normalizedValue);
            }
        });
        return params;
    }

    function buildRequestUrl(baseUrl, params) {
        const requestUrl = new URL(baseUrl, window.location.origin);
        requestUrl.search = params.toString();
        return requestUrl;
    }

    function initTransactionsDatepicker(scope) {
        if (typeof window.initDatepicker === "function") {
            window.initDatepicker(scope || document);
        }
    }

    function syncTransactionsFilterMode(form) {
        if (!form) {
            return;
        }

        const activeModeInput = form.querySelector("input[name='filter_mode']:checked");
        const activeMode = activeModeInput ? activeModeInput.value : "range";
        form.dataset.filterMode = activeMode;

        Array.from(form.querySelectorAll("[data-transactions-filter-mode-option]")).forEach(function (option) {
            option.classList.toggle("is-active", option.dataset.transactionsFilterModeOption === activeMode);
        });

        Array.from(form.querySelectorAll("[data-transactions-filter-panel]")).forEach(function (panel) {
            const isActive = panel.dataset.transactionsFilterPanel === activeMode;
            panel.hidden = !isActive;

            Array.from(panel.querySelectorAll("input, select, textarea")).forEach(function (field) {
                field.disabled = !isActive;
            });
        });
    }

    function refreshTransactionsPanel(requestUrl, shouldPushState) {
        const currentPanel = getPanel("transactions");
        if (!currentPanel) {
            window.location.href = requestUrl.toString();
            return Promise.resolve();
        }

        const requestId = ++transactionsRequestId;
        const currentFilterShell = currentPanel.querySelector(".admin-tools-filter-form");
        if (currentFilterShell) {
            currentFilterShell.classList.add("is-loading");
        }

        return fetch(requestUrl.toString(), {
            method: "GET",
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            },
            credentials: "same-origin"
        }).then(function (response) {
            if (!response.ok) {
                throw new Error("Failed to load transactions panel");
            }
            return response.text();
        }).then(function (html) {
            if (requestId !== transactionsRequestId) {
                return;
            }

            const parser = new DOMParser();
            const doc = parser.parseFromString(html, "text/html");
            const nextRoot = doc.getElementById("admin-tools-root");
            const nextPanel = nextRoot ? nextRoot.querySelector("[data-admin-tool-panel='transactions']") : null;
            if (!nextPanel) {
                throw new Error("Transactions panel missing in response");
            }

            currentPanel.innerHTML = nextPanel.innerHTML;

            if (shouldPushState) {
                window.history.pushState({ adminTool: "transactions" }, "", requestUrl.pathname + requestUrl.search + requestUrl.hash);
            }

            initTransactionsDatepicker(currentPanel);
            bindTransactionsFilters();
            setActiveTool("transactions", false);
        }).catch(function () {
            window.location.href = requestUrl.toString();
        }).finally(function () {
            const updatedPanel = getPanel("transactions");
            const updatedFilterShell = updatedPanel ? updatedPanel.querySelector(".admin-tools-filter-form") : null;
            if (updatedFilterShell) {
                updatedFilterShell.classList.remove("is-loading");
            }
        });
    }

    function bindTransactionsFilters() {
        const panel = getPanel("transactions");
        if (!panel) {
            return;
        }

        const filterShell = panel.querySelector(".admin-tools-filter-form");
        const dateForm = panel.querySelector(".admin-tools-filter-group--dates");
        const tabLinks = Array.from(panel.querySelectorAll("[data-transactions-currency-link]"));
        initTransactionsDatepicker(panel);
        updateTransactionFilterPills(panel);
        syncTransactionsFilterMode(dateForm);

        if (filterShell && filterShell.dataset.tabsBound !== "1") {
            filterShell.dataset.tabsBound = "1";
            tabLinks.forEach(function (link) {
                link.addEventListener("click", function (event) {
                    event.preventDefault();
                    refreshTransactionsPanel(new URL(link.href, window.location.origin), true);
                });
            });
        }

        if (dateForm && dateForm.dataset.bound !== "1") {
            dateForm.dataset.bound = "1";
            Array.from(dateForm.querySelectorAll("[data-transactions-filter-mode-option]")).forEach(function (option) {
                option.addEventListener("click", function () {
                    window.requestAnimationFrame(function () {
                        syncTransactionsFilterMode(dateForm);
                    });
                });
            });
            dateForm.addEventListener("change", function (event) {
                if (event.target && event.target.name === "filter_mode") {
                    syncTransactionsFilterMode(dateForm);
                }
            });
            dateForm.addEventListener("submit", function (event) {
                event.preventDefault();
                const params = serializeFilterForm(dateForm);
                refreshTransactionsPanel(buildRequestUrl(dateForm.action, params), true);
            });

            const resetDatesLink = dateForm.querySelector("[data-transactions-date-reset]");
            if (resetDatesLink) {
                resetDatesLink.addEventListener("click", function (event) {
                    event.preventDefault();
                    refreshTransactionsPanel(new URL(resetDatesLink.href, window.location.origin), true);
                });
            }
        }
    }

    navLinks.concat(tabLinks).forEach(function (link) {
        link.addEventListener("click", function (event) {
            event.preventDefault();
            const nextTool = link.dataset.adminToolTab || link.dataset.adminToolTabLink || "accounting";
            setActiveTool(nextTool, true);
        });
    });

    window.addEventListener("popstate", function () {
        const nextTool = toolFromLocation();
        setActiveTool(nextTool, false);

        if (nextTool === "transactions") {
            refreshTransactionsPanel(new URL(window.location.href), false);
        }
    });

    bindTransactionsFilters();
    setActiveTool(root.dataset.activeTool || toolFromLocation(), false);
});
