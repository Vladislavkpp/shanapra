(function () {
    var REACTION_COOLDOWN_MS = 700;
    var branchNavigationToken = 0;

    function initFlash(root) {
        (root || document).querySelectorAll("[data-lenta-test-flash]").forEach(function (flash) {
            if (flash.dataset.flashReady === "1") {
                return;
            }

            flash.dataset.flashReady = "1";
            window.setTimeout(function () {
                flash.classList.add("is-leaving");
                window.setTimeout(function () {
                    if (flash.parentNode) {
                        flash.parentNode.removeChild(flash);
                    }
                }, 260);
            }, 3200);
        });
    }

    function initAutosizeForm(form, config) {
        if (!form || form.dataset.formReady === "1") {
            return;
        }

        var textarea = form.querySelector(config.textareaSelector);
        var counter = form.querySelector(config.counterSelector);
        var submit = form.querySelector(config.submitSelector);
        if (!textarea || !counter || !submit) {
            return;
        }

        form.dataset.formReady = "1";

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
            submit.textContent = config.submittingText;
        });

        resizeTextarea();
        syncState();
    }

    function initDynamicContent(root) {
        var scope = root || document;
        initFlash(scope);

        scope.querySelectorAll("[data-lenta-test-form]").forEach(function (form) {
            initAutosizeForm(form, {
                textareaSelector: "textarea[name='message']",
                counterSelector: "[data-lenta-test-counter]",
                submitSelector: "[data-lenta-test-submit]",
                submittingText: "Публікація..."
            });
        });

        scope.querySelectorAll("[data-lenta-comment-form]").forEach(function (form) {
            initAutosizeForm(form, {
                textareaSelector: "textarea[name='comment_text']",
                counterSelector: "[data-lenta-comment-counter]",
                submitSelector: "[data-lenta-comment-submit]",
                submittingText: "Надсилання..."
            });
        });
    }

    initDynamicContent(document);

    var hoverQuery = window.matchMedia ? window.matchMedia("(hover: hover) and (pointer: fine)") : null;
    var mobileQuery = window.matchMedia ? window.matchMedia("(max-width: 640px)") : null;
    var longPressTimer = null;
    var activeTouchWidget = null;
    var activeTouchId = null;
    var activeTouchOption = null;
    var suppressClickUntil = 0;

    function supportsHoverPicker() {
        return hoverQuery ? hoverQuery.matches : window.innerWidth >= 992;
    }

    function isMobileViewport() {
        return mobileQuery ? mobileQuery.matches : window.innerWidth <= 640;
    }

    function getWidget(node) {
        return node && node.closest ? node.closest("[data-ltt-reaction-widget]") : null;
    }

    function getTrigger(widget) {
        return widget ? widget.querySelector("[data-reaction-trigger]") : null;
    }

    function closePicker(widget) {
        if (!widget) {
            return;
        }

        widget.classList.remove("is-picker-open");
        var trigger = getTrigger(widget);
        if (trigger) {
            trigger.setAttribute("aria-expanded", "false");
        }
    }

    function openPicker(widget) {
        if (!widget) {
            return;
        }

        closeAllPickers(widget);
        widget.classList.add("is-picker-open");
        var trigger = getTrigger(widget);
        if (trigger) {
            trigger.setAttribute("aria-expanded", "true");
        }
    }

    function closeAllPickers(exceptWidget) {
        document.querySelectorAll("[data-ltt-reaction-widget].is-picker-open").forEach(function (widget) {
            if (exceptWidget && widget === exceptWidget) {
                return;
            }
            closePicker(widget);
        });
    }

    function clearLongPress() {
        if (longPressTimer) {
            window.clearTimeout(longPressTimer);
            longPressTimer = null;
        }
    }

    function setTouchOption(option) {
        if (activeTouchOption && activeTouchOption !== option) {
            activeTouchOption.classList.remove("is-touch-hover");
        }

        activeTouchOption = option || null;

        if (activeTouchOption) {
            activeTouchOption.classList.add("is-touch-hover");
        }
    }

    function clearTouchState() {
        clearLongPress();
        setTouchOption(null);
        activeTouchWidget = null;
        activeTouchId = null;
    }

    function findActiveTouch(touchList) {
        if (!touchList || activeTouchId === null) {
            return null;
        }

        for (var i = 0; i < touchList.length; i += 1) {
            if (touchList[i].identifier === activeTouchId) {
                return touchList[i];
            }
        }

        return null;
    }

    function resolveTouchOption(touch) {
        if (!touch) {
            return null;
        }

        var node = document.elementFromPoint(touch.clientX, touch.clientY);
        if (!node || !node.closest) {
            return null;
        }

        var option = node.closest("[data-reaction-option]");
        if (!option) {
            return null;
        }

        var widget = getWidget(option);
        return widget && widget === activeTouchWidget ? option : null;
    }

    function replaceNode(target, html) {
        if (!target || !target.parentNode || typeof html !== "string" || html === "") {
            return null;
        }

        var template = document.createElement("template");
        template.innerHTML = html.trim();
        var nextNode = template.content.firstElementChild;
        if (!nextNode) {
            return null;
        }

        target.parentNode.replaceChild(nextNode, target);
        return nextNode;
    }

    function getPublicationsPanel(root) {
        return (root || document).querySelector("[data-ltt-publications-panel]");
    }

    function getCurrentGraveId() {
        var panel = getPublicationsPanel(document);
        return panel ? String(panel.getAttribute("data-grave-id") || "") : "";
    }

    function isManagedBranchUrl(url) {
        try {
            var targetUrl = new URL(url, window.location.href);
            if (targetUrl.origin !== window.location.origin) {
                return false;
            }

            var pathname = targetUrl.pathname.replace(/\/+$/, "");
            if (pathname !== "/cardout/branch" && pathname !== "/cardout" && pathname !== "/cardout.php") {
                return false;
            }

            var targetIdx = String(targetUrl.searchParams.get("idx") || "");
            return targetIdx !== "" && targetIdx === getCurrentGraveId();
        } catch (error) {
            return false;
        }
    }

    function resolveBranchTargetSelector(targetUrl) {
        var hash = targetUrl.hash || "";
        var fallbackCommentId = targetUrl.searchParams.get("comment_id") || "";

        if (hash && hash !== "#publications") {
            return hash;
        }

        if (fallbackCommentId) {
            return "#ltt-comment-" + String(fallbackCommentId);
        }

        var heroComment = document.querySelector(".ltt-comment--hero[id]");
        return heroComment ? ("#" + heroComment.id) : "";
    }

    function scrollToPanelTarget(href) {
        try {
            var targetUrl = new URL(href, window.location.href);
            var hash = targetUrl.hash || "";
            var targetSelector = hash || resolveBranchTargetSelector(targetUrl);
            if (!targetSelector) {
                return;
            }

            window.requestAnimationFrame(function () {
                var target = document.querySelector(targetSelector);
                if (target && typeof target.scrollIntoView === "function") {
                    target.scrollIntoView({ behavior: "smooth", block: "start" });
                }
            });
        } catch (error) {
            // noop
        }
    }

    function pulseHashTarget(href) {
        try {
            var targetUrl = new URL(href || window.location.href, window.location.href);
            var targetSelector = resolveBranchTargetSelector(targetUrl);

            if (!targetSelector) {
                return;
            }

            window.requestAnimationFrame(function () {
                var target = document.querySelector(targetSelector);
                if (!target) {
                    return;
                }

                target.classList.remove("is-hash-target");
                void target.offsetWidth;
                target.classList.add("is-hash-target");
                window.setTimeout(function () {
                    target.classList.remove("is-hash-target");
                }, 2700);
            });
        } catch (error) {
            // noop
        }
    }

    function setPanelLoading(panel, isLoading) {
        if (!panel) {
            return;
        }

        panel.classList.toggle("is-branch-loading", !!isLoading);
        panel.setAttribute("aria-busy", isLoading ? "true" : "false");
    }

    function swapPublicationsPanel(nextPanel) {
        var currentPanel = getPublicationsPanel(document);
        if (!currentPanel || !nextPanel) {
            return null;
        }

        currentPanel.replaceWith(nextPanel);
        nextPanel.classList.add("is-branch-entering");
        window.setTimeout(function () {
            nextPanel.classList.remove("is-branch-entering");
        }, 260);

        initDynamicContent(nextPanel);
        return nextPanel;
    }

    function loadBranchPanel(href, options) {
        if (!isManagedBranchUrl(href)) {
            window.location.href = href;
            return;
        }

        var currentPanel = getPublicationsPanel(document);
        if (!currentPanel) {
            window.location.href = href;
            return;
        }

        var navigationId = branchNavigationToken + 1;
        branchNavigationToken = navigationId;
        setPanelLoading(currentPanel, true);

        fetch(href, {
            method: "GET",
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "X-Branch-Partial": "1"
            },
            credentials: "same-origin"
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error("bad_response");
                }

                return response.text();
            })
            .then(function (html) {
                if (navigationId !== branchNavigationToken) {
                    return;
                }

                var template = document.createElement("template");
                template.innerHTML = html.trim();
                var nextPanel = template.content.firstElementChild;
                if (!nextPanel || !nextPanel.matches("[data-ltt-publications-panel]")) {
                    throw new Error("panel_not_found");
                }

                var mountedPanel = swapPublicationsPanel(nextPanel);
                if (!mountedPanel) {
                    throw new Error("panel_swap_failed");
                }

                if (!options || options.historyMode !== "replace") {
                    window.history.pushState({ branchPanel: true }, "", href);
                } else {
                    window.history.replaceState({ branchPanel: true }, "", href);
                }

                scrollToPanelTarget(href);
                pulseHashTarget(href);
            })
            .catch(function () {
                if (navigationId !== branchNavigationToken) {
                    return;
                }

                window.location.href = href;
            })
            .finally(function () {
                var panel = getPublicationsPanel(document);
                setPanelLoading(panel, false);
            });
    }

    function setBusy(widget, isBusy) {
        if (!widget) {
            return;
        }

        widget.classList.toggle("is-busy", isBusy);
        var buttons = widget.querySelectorAll("button");
        buttons.forEach(function (button) {
            button.disabled = !!isBusy;
        });
    }

    pulseHashTarget(window.location.href);

    function submitReaction(widget, reactionType) {
        if (!widget || !reactionType || widget.classList.contains("is-busy")) {
            return;
        }

        var now = Date.now();
        var cooldownUntil = Number(widget.dataset.cooldownUntil || "0");
        if (cooldownUntil > now) {
            return;
        }

        var lentaId = widget.getAttribute("data-lenta-id") || "";
        var idxabon = widget.getAttribute("data-idxabon") || "";
        if (!lentaId || !idxabon) {
            return;
        }

        widget.dataset.cooldownUntil = String(now + REACTION_COOLDOWN_MS);
        setBusy(widget, true);

        var formData = new FormData();
        formData.append("action", "lenta_reaction_toggle");
        formData.append("ajax", "1");
        formData.append("lenta_id", lentaId);
        formData.append("idxabon", idxabon);
        formData.append("reaction_type", reactionType);

        fetch("/cardout.php?idx=" + encodeURIComponent(String(idxabon)), {
            method: "POST",
            body: formData,
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            }
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (payload) {
                if (!payload || payload.status === "error") {
                    throw new Error(payload && payload.message ? payload.message : "Не вдалося оновити реакцію.");
                }

                if (payload.status === "unauthorized") {
                    window.location.href = payload.login_url || "/auth.php";
                    return;
                }

                var post = widget.closest("[data-ltt-post]");
                if (!post) {
                    return;
                }

                var summary = post.querySelector("[data-reaction-summary]");
                var nextWidget = replaceNode(widget, payload.widget_html) || widget;
                replaceNode(summary, payload.summary_html);
                closePicker(nextWidget);
            })
            .catch(function (error) {
                window.alert(error && error.message ? error.message : "Не вдалося оновити реакцію.");
            })
            .finally(function () {
                var currentWidget = document.querySelector("[data-ltt-reaction-widget][data-lenta-id='" + String(lentaId) + "']");
                var resolvedWidget = currentWidget || widget;
                setBusy(resolvedWidget, false);
                window.setTimeout(function () {
                    if (resolvedWidget) {
                        resolvedWidget.dataset.cooldownUntil = "0";
                    }
                }, REACTION_COOLDOWN_MS);
            });
    }

    document.addEventListener("mouseover", function (event) {
        if (!supportsHoverPicker()) {
            return;
        }

        var widget = getWidget(event.target);
        if (!widget || widget.contains(event.relatedTarget)) {
            return;
        }

        openPicker(widget);
    });

    document.addEventListener("mouseout", function (event) {
        if (!supportsHoverPicker()) {
            return;
        }

        var widget = getWidget(event.target);
        if (!widget || widget.contains(event.relatedTarget)) {
            return;
        }

        closePicker(widget);
    });

    document.addEventListener("focusin", function (event) {
        var widget = getWidget(event.target);
        if (!widget) {
            closeAllPickers();
            return;
        }

        openPicker(widget);
    });

    document.addEventListener("focusout", function (event) {
        var widget = getWidget(event.target);
        if (!widget) {
            return;
        }

        window.setTimeout(function () {
            if (!widget.contains(document.activeElement)) {
                closePicker(widget);
            }
        }, 0);
    });

    document.addEventListener("touchstart", function (event) {
        var trigger = event.target.closest("[data-reaction-trigger]");
        if (!trigger) {
            return;
        }

        var widget = getWidget(trigger);
        if (!widget) {
            return;
        }

        clearLongPress();
        setTouchOption(null);
        activeTouchWidget = widget;
        activeTouchId = event.changedTouches && event.changedTouches[0] ? event.changedTouches[0].identifier : null;
        widget.dataset.longPressOpened = "0";

        longPressTimer = window.setTimeout(function () {
            widget.dataset.longPressOpened = "1";
            openPicker(widget);
        }, 420);
    }, { passive: true });

    document.addEventListener("touchmove", function (event) {
        if (!activeTouchWidget) {
            clearLongPress();
            return;
        }

        var touch = findActiveTouch(event.touches);
        if (!touch) {
            clearTouchState();
            return;
        }

        if (activeTouchWidget.dataset.longPressOpened === "1" && activeTouchWidget.classList.contains("is-picker-open")) {
            event.preventDefault();
            setTouchOption(resolveTouchOption(touch));
            return;
        }

        clearLongPress();
        activeTouchWidget = null;
        activeTouchId = null;
        setTouchOption(null);
    }, { passive: false });

    document.addEventListener("touchcancel", function () {
        if (activeTouchWidget && activeTouchWidget.dataset.longPressOpened === "1") {
            closePicker(activeTouchWidget);
        }

        clearTouchState();
    }, { passive: true });

    document.addEventListener("touchend", function (event) {
        if (!activeTouchWidget) {
            clearLongPress();
            return;
        }

        var touch = findActiveTouch(event.changedTouches);
        if (!touch) {
            clearTouchState();
            return;
        }

        if (activeTouchWidget.dataset.longPressOpened === "1" && activeTouchWidget.classList.contains("is-picker-open")) {
            event.preventDefault();
            var selectedOption = activeTouchOption || resolveTouchOption(touch);
            var selectedWidget = activeTouchWidget;
            suppressClickUntil = Date.now() + 700;
            clearTouchState();

            if (selectedOption) {
                submitReaction(selectedWidget, selectedOption.getAttribute("data-reaction-type") || "");
            } else {
                closePicker(selectedWidget);
            }
            return;
        }

        clearTouchState();
    }, { passive: false });

    window.addEventListener("scroll", function () {
        if (isMobileViewport()) {
            closeAllPickers();
        }
    }, { passive: true });

    window.addEventListener("popstate", function () {
        if (!isManagedBranchUrl(window.location.href)) {
            window.location.reload();
            return;
        }

        loadBranchPanel(window.location.href, {
            historyMode: "replace"
        });
    });

    document.addEventListener("click", function (event) {
        if (Date.now() < suppressClickUntil) {
            event.preventDefault();
            return;
        }

        var branchLink = event.target.closest("[data-branch-open]");
        if (branchLink) {
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            var href = branchLink.getAttribute("href");
            if (!href) {
                return;
            }

            event.preventDefault();
            loadBranchPanel(href);
            return;
        }

        var option = event.target.closest("[data-reaction-option]");
        if (option) {
            event.preventDefault();
            var optionWidget = getWidget(option);
            if (!optionWidget) {
                return;
            }

            submitReaction(optionWidget, option.getAttribute("data-reaction-type") || "");
            return;
        }

        var trigger = event.target.closest("[data-reaction-trigger]");
        if (trigger) {
            event.preventDefault();
            var widget = getWidget(trigger);
            if (!widget) {
                return;
            }

            if (widget.dataset.longPressOpened === "1") {
                widget.dataset.longPressOpened = "0";
                return;
            }

            var currentReaction = widget.getAttribute("data-current-reaction") || "";
            var defaultReaction = widget.getAttribute("data-default-reaction") || "memory";
            submitReaction(widget, currentReaction || defaultReaction);
            return;
        }

        if (!getWidget(event.target)) {
            closeAllPickers();
        }
    });
})();
