document.addEventListener("DOMContentLoaded", function () {
    const root = document.getElementById("supportDeskRoot");
    if (!root) return;

    const cfg = JSON.parse(root.dataset.socketConfig || "{}");
    const templatesSeed = JSON.parse(root.dataset.templates || "[]");
    const tabs = Array.from(root.querySelectorAll(".support-desk-tab"));
    const panes = Array.from(root.querySelectorAll("[data-bucket-pane]"));
    const listWrap = document.getElementById("supportDeskList");
    const detailWrap = document.getElementById("supportDeskDetail");
    let activeBucket = "queue";
    let selectedTicketId = parseInt(root.dataset.selectedTicketId || "0", 10) || 0;
    let socket = null;
    let fallbackTimer = null;

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (char) {
            return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[char];
        });
    }

    function statusLabel(status) {
        return {
            new: "Нове",
            open: "В роботі",
            waiting_customer: "Очікує клієнта",
            resolved: "Вирішено",
            closed: "Закрито",
            spam: "Спам"
        }[status] || "Нове";
    }

    function renderTicketList(tickets) {
        if (!Array.isArray(tickets) || tickets.length === 0) {
            return '<div class="support-desk-empty">Поки що тут порожньо.</div>';
        }

        return tickets.map(function (ticket) {
            const selected = parseInt(ticket.id || 0, 10) === selectedTicketId ? " is-selected" : "";
            return '' +
                '<button type="button" class="support-ticket-list-item' + selected + '" data-ticket-open="' + parseInt(ticket.id || 0, 10) + '">' +
                '<div class="support-ticket-list-head"><strong>' + escapeHtml(ticket.requester_label || "Клієнт") + '</strong><span>#' + parseInt(ticket.id || 0, 10) + '</span></div>' +
                '<div class="support-ticket-list-preview">' + escapeHtml(ticket.last_message_preview || "Без повідомлень") + '</div>' +
                '<div class="support-ticket-list-meta"><span class="support-ticket-badge support-ticket-badge--' + escapeHtml(ticket.status || "new") + '">' + escapeHtml(statusLabel(ticket.status || "new")) + '</span><span>' + escapeHtml(ticket.last_message_at || "") + '</span></div>' +
                '</button>';
        }).join("");
    }

    function renderMessages(messages) {
        if (!Array.isArray(messages) || messages.length === 0) {
            return '<div class="support-empty-thread">Повідомлень поки немає.</div>';
        }

        return messages.map(function (message) {
            let html = '<article class="support-message support-message--' + escapeHtml(message.sender_type || "system") + '" data-message-id="' + parseInt(message.id || 0, 10) + '">';
            html += '<div class="support-message-meta"><strong>' + escapeHtml(message.display_name || "Система") + '</strong><span>' + escapeHtml(message.created_at || "") + '</span></div>';
            if (message.image_path) {
                html += '<div class="support-message-image"><a href="' + escapeHtml(message.image_path) + '" target="_blank" rel="noopener"><img src="' + escapeHtml(message.image_path) + '" alt=""></a></div>';
            }
            if (message.body) {
                html += '<div class="support-message-body">' + escapeHtml(message.body).replace(/\n/g, "<br>") + '</div>';
            }
            html += '<div class="support-message-type">' + escapeHtml(message.sender_type || "system") + '</div>';
            html += '</article>';
            return html;
        }).join("");
    }

    function renderTemplates(templates) {
        return (templates || []).map(function (template) {
            return '<button type="button" class="support-template-item" data-template-id="' + parseInt(template.id || 0, 10) + '" data-template-title="' + escapeHtml(template.title || "") + '" data-template-body="' + escapeHtml(template.body || "") + '">' + escapeHtml(template.title || "") + '</button>';
        }).join("");
    }

    function attachPaneHandlers() {
        listWrap.querySelectorAll("[data-ticket-open]").forEach(function (button) {
            button.addEventListener("click", function () {
                const ticketId = parseInt(button.dataset.ticketOpen || "0", 10) || 0;
                if (ticketId > 0) openTicket(ticketId);
            });
        });
    }

    async function loadBucket(bucket, preserveSelection) {
        try {
            const res = await fetch("/support-desk.php?action=support_list_bucket&bucket=" + encodeURIComponent(bucket), { credentials: "same-origin" });
            const data = await res.json();
            if (data.status !== "ok") return;

            const pane = panes.find(function (item) { return item.dataset.bucketPane === bucket; });
            if (pane) {
                pane.innerHTML = renderTicketList(data.tickets || []);
            }
            attachPaneHandlers();

            if (data.counts) {
                tabs.forEach(function (tab) {
                    const countEl = tab.querySelector("span");
                    if (countEl) countEl.textContent = String(data.counts[tab.dataset.bucket] || 0);
                });
            }

            if (!preserveSelection && Array.isArray(data.tickets) && data.tickets.length > 0 && !selectedTicketId) {
                openTicket(parseInt(data.tickets[0].id || 0, 10));
            }
        } catch (err) {
            console.error(err);
        }
    }

    async function openTicket(ticketId) {
        selectedTicketId = ticketId;
        root.dataset.selectedTicketId = String(ticketId);
        try {
            const res = await fetch("/support-desk.php?action=support_get_ticket&ticket_id=" + encodeURIComponent(ticketId), { credentials: "same-origin" });
            const data = await res.json();
            if (data.status !== "ok" || !data.ticket) return;

            renderDetail(data.ticket, data.messages || []);
            panes.forEach(function (pane) {
                pane.querySelectorAll(".support-ticket-list-item").forEach(function (item) {
                    item.classList.toggle("is-selected", parseInt(item.dataset.ticketOpen || "0", 10) === ticketId);
                });
            });

            if (socket) {
                socket.emit("support:subscribe", { room: "support:ticket:" + ticketId, ticketId: ticketId });
            }
        } catch (err) {
            console.error(err);
        }
    }

    function renderDetail(ticket, messages) {
        const webmasters = JSON.parse(root.dataset.webmasters || "[]");
        const templates = templatesSeed;
        let html = '';
        html += '<div class="support-desk-ticket-head">';
        html += '<div><p class="support-desk-ticket-kicker">Звернення #' + parseInt(ticket.id || 0, 10) + '</p><h2>' + escapeHtml(ticket.requester_label || "Клієнт") + '</h2></div>';
        html += '<div class="support-desk-ticket-meta"><span class="support-ticket-badge support-ticket-badge--' + escapeHtml(ticket.status || "new") + '">' + escapeHtml(statusLabel(ticket.status || "new")) + '</span><span class="support-desk-ticket-assignee">' + escapeHtml(ticket.assignee_label || "Без виконавця") + '</span></div>';
        html += '</div>';
        html += '<div class="support-desk-actions">';
        html += '<button type="button" class="support-action-btn" data-claim-ticket="' + parseInt(ticket.id || 0, 10) + '">Взяти в роботу</button>';
        html += '<select id="supportDeskTransferUser"><option value="">Передати...</option>';
        webmasters.forEach(function (webmaster) {
            html += '<option value="' + parseInt(webmaster.id || 0, 10) + '">' + escapeHtml(webmaster.name || "") + '</option>';
        });
        html += '</select><button type="button" class="support-action-btn support-action-btn--ghost" data-transfer-ticket="' + parseInt(ticket.id || 0, 10) + '">Передати</button>';
        html += '<select id="supportDeskStatus">';
        ["new", "open", "waiting_customer", "resolved", "closed", "spam"].forEach(function (status) {
            html += '<option value="' + escapeHtml(status) + '"' + (status === ticket.status ? " selected" : "") + '>' + escapeHtml(statusLabel(status)) + '</option>';
        });
        html += '</select><button type="button" class="support-action-btn support-action-btn--ghost" data-status-ticket="' + parseInt(ticket.id || 0, 10) + '">Змінити статус</button>';
        html += '</div>';
        html += '<div class="support-desk-messages" id="supportDeskMessages">' + renderMessages(messages) + '</div>';
        html += '<div class="support-desk-compose"><div class="support-desk-templates"><input type="search" id="supportDeskTemplateSearch" placeholder="Пошук шаблону"><div class="support-desk-template-list" id="supportDeskTemplateList">' + renderTemplates(templates) + '</div><form id="supportDeskTemplateManager" class="support-desk-template-manager"><input type="hidden" name="template_id" value=""><input type="text" name="title" id="supportDeskTemplateTitle" placeholder="Назва шаблону"><textarea name="body" id="supportDeskTemplateBody" rows="3" placeholder="Текст шаблону"></textarea><div class="support-desk-template-actions"><button type="submit" class="support-action-btn support-action-btn--ghost">Зберегти шаблон</button><button type="button" class="support-action-btn support-action-btn--ghost" id="supportDeskTemplateDelete">Видалити</button></div></form></div>';
        html += '<form id="supportDeskReplyForm" enctype="multipart/form-data"><input type="hidden" name="ticket_id" value="' + parseInt(ticket.id || 0, 10) + '"><input type="hidden" name="template_id" value=""><div class="support-desk-preview" id="supportDeskPreview" aria-hidden="true"></div><div class="support-desk-compose-row"><label class="support-desk-attach" for="supportDeskImg"><input type="file" name="img" id="supportDeskImg" accept="image/jpeg,image/png,image/gif,image/webp" hidden>+</label><textarea name="message" id="supportDeskReplyMessage" placeholder="Відповідь клієнту..." rows="3"></textarea><button type="submit">Надіслати</button></div></form></div>';

        detailWrap.innerHTML = html;
        bindDetailHandlers();
        const msgBox = document.getElementById("supportDeskMessages");
        if (msgBox) msgBox.scrollTop = msgBox.scrollHeight;
    }

    function bindPreview(fileInput, preview) {
        if (!fileInput || !preview) return;
        fileInput.addEventListener("change", function () {
            preview.innerHTML = "";
            preview.classList.remove("is-visible");
            if (!fileInput.files || !fileInput.files[0]) return;
            const reader = new FileReader();
            reader.onload = function () {
                preview.classList.add("is-visible");
                preview.innerHTML = '<div class="support-preview-card"><img src="' + escapeHtml(reader.result || "") + '" alt=""><button type="button" class="support-preview-remove">&times;</button></div>';
                const remove = preview.querySelector(".support-preview-remove");
                if (remove) {
                    remove.addEventListener("click", function () {
                        fileInput.value = "";
                        preview.innerHTML = "";
                        preview.classList.remove("is-visible");
                    });
                }
            };
            reader.readAsDataURL(fileInput.files[0]);
        });
    }

    function bindTemplateButtons() {
        const form = document.getElementById("supportDeskReplyForm");
        const templateManager = document.getElementById("supportDeskTemplateManager");
        const templateTitle = document.getElementById("supportDeskTemplateTitle");
        const templateBody = document.getElementById("supportDeskTemplateBody");
        const textarea = document.getElementById("supportDeskReplyMessage");
        const templateIdInput = form ? form.querySelector("input[name='template_id']") : null;

        detailWrap.querySelectorAll(".support-template-item").forEach(function (button) {
            if (button.dataset.bound === "1") return;
            button.dataset.bound = "1";
            button.addEventListener("click", function () {
                if (!textarea) return;
                textarea.value = button.dataset.templateBody || "";
                if (templateIdInput) templateIdInput.value = button.dataset.templateId || "";
                if (templateManager) {
                    const hiddenId = templateManager.querySelector("input[name='template_id']");
                    if (hiddenId) hiddenId.value = button.dataset.templateId || "";
                }
                if (templateTitle) templateTitle.value = button.dataset.templateTitle || "";
                if (templateBody) templateBody.value = button.dataset.templateBody || "";
            });
        });
    }

    function bindDetailHandlers() {
        const claimBtn = detailWrap.querySelector("[data-claim-ticket]");
        const transferBtn = detailWrap.querySelector("[data-transfer-ticket]");
        const statusBtn = detailWrap.querySelector("[data-status-ticket]");
        const form = document.getElementById("supportDeskReplyForm");
        const transferSelect = document.getElementById("supportDeskTransferUser");
        const statusSelect = document.getElementById("supportDeskStatus");
        const templateSearch = document.getElementById("supportDeskTemplateSearch");
        const templateList = document.getElementById("supportDeskTemplateList");
        const templateManager = document.getElementById("supportDeskTemplateManager");
        const templateDelete = document.getElementById("supportDeskTemplateDelete");
        const fileInput = document.getElementById("supportDeskImg");
        const preview = document.getElementById("supportDeskPreview");

        bindPreview(fileInput, preview);
        bindTemplateButtons();

        if (templateSearch && templateList) {
            templateSearch.addEventListener("input", function () {
                const needle = (templateSearch.value || "").toLowerCase().trim();
                templateList.querySelectorAll(".support-template-item").forEach(function (item) {
                    const visible = !needle || item.textContent.toLowerCase().includes(needle);
                    item.style.display = visible ? "" : "none";
                });
            });
        }

        if (claimBtn) {
            claimBtn.addEventListener("click", async function () {
                await postForm("/support-desk.php?action=support_claim_ticket", { ticket_id: selectedTicketId });
            });
        }

        if (transferBtn) {
            transferBtn.addEventListener("click", async function () {
                const toUserId = parseInt(transferSelect.value || "0", 10) || 0;
                if (!toUserId) return;
                await postForm("/support-desk.php?action=support_transfer_ticket", { ticket_id: selectedTicketId, to_user_id: toUserId });
            });
        }

        if (statusBtn) {
            statusBtn.addEventListener("click", async function () {
                await postForm("/support-desk.php?action=support_change_status", { ticket_id: selectedTicketId, status: statusSelect.value || "new" });
            });
        }

        if (form) {
            form.addEventListener("submit", async function (event) {
                event.preventDefault();
                const data = new FormData(form);
                try {
                    const res = await fetch("/support-desk.php?action=support_send_message", { method: "POST", body: data, credentials: "same-origin" });
                    const payload = await res.json();
                    if (payload.status === "ok") {
                        await openTicket(selectedTicketId);
                        await loadBucket(activeBucket, true);
                        form.reset();
                        if (preview) {
                            preview.innerHTML = "";
                            preview.classList.remove("is-visible");
                        }
                    } else if (payload.msg) {
                        alert(payload.msg);
                    }
                } catch (err) {
                    console.error(err);
                    alert("Не вдалося надіслати повідомлення.");
                }
            });
        }

        if (templateManager) {
            templateManager.addEventListener("submit", async function (event) {
                event.preventDefault();
                try {
                    const res = await fetch("/support-desk.php?action=support_template_save", {
                        method: "POST",
                        body: new FormData(templateManager),
                        credentials: "same-origin"
                    });
                    const payload = await res.json();
                    if (payload.status === "ok") {
                        await reloadTemplates();
                        templateManager.reset();
                    } else if (payload.msg) {
                        alert(payload.msg);
                    }
                } catch (err) {
                    console.error(err);
                }
            });
        }

        if (templateDelete && templateManager) {
            templateDelete.addEventListener("click", async function () {
                const hiddenId = templateManager.querySelector("input[name='template_id']");
                const templateId = parseInt(hiddenId && hiddenId.value ? hiddenId.value : "0", 10) || 0;
                if (!templateId) return;
                try {
                    const body = new URLSearchParams();
                    body.append("template_id", String(templateId));
                    const res = await fetch("/support-desk.php?action=support_template_delete", {
                        method: "POST",
                        body: body,
                        headers: { "Content-Type": "application/x-www-form-urlencoded" },
                        credentials: "same-origin"
                    });
                    const payload = await res.json();
                    if (payload.status === "ok") {
                        await reloadTemplates();
                        templateManager.reset();
                    } else if (payload.msg) {
                        alert(payload.msg);
                    }
                } catch (err) {
                    console.error(err);
                }
            });
        }
    }

    async function reloadTemplates() {
        try {
            const res = await fetch("/support-desk.php?action=support_templates_list", { credentials: "same-origin" });
            const data = await res.json();
            if (data.status === "ok" && Array.isArray(data.templates)) {
                templatesSeed.length = 0;
                data.templates.forEach(function (item) { templatesSeed.push(item); });
                const list = document.getElementById("supportDeskTemplateList");
                if (list) {
                    list.innerHTML = renderTemplates(templatesSeed);
                }
                bindTemplateButtons();
            }
        } catch (err) {
            console.error(err);
        }
    }

    async function postForm(url, data) {
        const body = new URLSearchParams();
        Object.keys(data || {}).forEach(function (key) {
            body.append(key, data[key]);
        });
        const res = await fetch(url, {
            method: "POST",
            body: body,
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            credentials: "same-origin"
        });
        const payload = await res.json();
        if (payload.status === "ok") {
            await openTicket(selectedTicketId);
            await loadBucket(activeBucket, true);
        } else if (payload.msg) {
            alert(payload.msg);
        }
    }

    function activateTab(bucket) {
        activeBucket = bucket;
        tabs.forEach(function (tab) {
            tab.classList.toggle("is-active", tab.dataset.bucket === bucket);
        });
        panes.forEach(function (pane) {
            pane.classList.toggle("is-active", pane.dataset.bucketPane === bucket);
        });
    }

    function initTabs() {
        tabs.forEach(function (tab) {
            tab.addEventListener("click", async function () {
                const bucket = tab.dataset.bucket || "queue";
                activateTab(bucket);
                await loadBucket(bucket, true);
            });
        });
        attachPaneHandlers();
    }

    function initSocket() {
        if (!cfg || !cfg.enabled || typeof window.io !== "function") {
            fallbackTimer = window.setInterval(function () { loadBucket(activeBucket, true); }, 8000);
            return;
        }

        socket = window.io(cfg.socket_url, {
            path: "/socket.io",
            withCredentials: true,
            transports: ["websocket", "polling"]
        });

        socket.on("connect", function () {
            if (fallbackTimer) {
                window.clearInterval(fallbackTimer);
                fallbackTimer = null;
            }
            socket.emit("support:subscribe", { room: "support:queue" });
            if (selectedTicketId > 0) {
                socket.emit("support:subscribe", { room: "support:ticket:" + selectedTicketId, ticketId: selectedTicketId });
            }
        });

        ["support:ticket:new", "support:ticket:update", "support:ticket:claimed", "support:ticket:transferred", "support:ticket:status_changed"].forEach(function (eventName) {
            socket.on(eventName, async function (payload) {
                await loadBucket(activeBucket, true);
                if (payload && payload.ticket && parseInt(payload.ticket.id || 0, 10) === selectedTicketId) {
                    await openTicket(selectedTicketId);
                }
            });
        });

        socket.on("support:message:new", async function (payload) {
            if (!payload || !payload.ticket) return;
            await loadBucket(activeBucket, true);
            if (parseInt(payload.ticket.id || 0, 10) === selectedTicketId) {
                await openTicket(selectedTicketId);
            }
        });

        socket.on("disconnect", function () {
            if (!fallbackTimer) fallbackTimer = window.setInterval(function () { loadBucket(activeBucket, true); }, 8000);
        });
    }

    initTabs();
    initSocket();
});
