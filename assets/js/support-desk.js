document.addEventListener("DOMContentLoaded", function () {
    const root = document.getElementById("supportDeskRoot");
    if (!root) return;

    const tabs = Array.from(root.querySelectorAll(".support-desk-tab"));
    const panes = Array.from(root.querySelectorAll("[data-bucket-pane]"));
    const listWrap = document.getElementById("supportDeskList");
    const detailWrap = document.getElementById("supportDeskDetail");
    const infoWrap = document.getElementById("supportDeskInfoPanel");
    const templateModal = document.getElementById("supportDeskTemplatesModal");
    const spamModal = document.getElementById("supportDeskSpamModal");
    const templateSearchInput = document.getElementById("supportDeskTemplateSearch");
    const templateFiltersWrap = document.getElementById("supportDeskTemplateFilters");
    const templateListWrap = document.getElementById("supportDeskTemplateList");
    const spamConfirmBtn = document.getElementById("supportDeskSpamConfirm");
    const templatesSeed = JSON.parse(root.dataset.templates || "[]");
    const currentUserId = parseInt(root.dataset.currentUserId || "0", 10) || 0;
    const bucketStorageKey = "supportDeskActiveBucket";

    let activeBucket = "queue";
    let selectedTicketId = parseInt(root.dataset.selectedTicketId || "0", 10) || 0;
    let selectedTicketStatus = detailWrap ? (detailWrap.dataset.ticketStatus || "new") : "new";
    let lastMessageId = 0;
    let lastRenderedDateKey = null;
    let pollTimer = null;
    let activeTemplateCategory = "all";

    const templateCategories = [
        { key: "all", label: "Всі" },
        { key: "general", label: "Загальні" },
        { key: "auth", label: "Авторизація" },
        { key: "tech", label: "Технічні" }
    ];

    const defaultTemplates = [
        {
            id: 0,
            title: "Вітання клієнта",
            body: "Вітаємо!\nДякуємо, що звернулися до служби підтримки Шана.\nЧим можемо вам допомогти?",
            category: "general",
            command: "/привіт"
        },
        {
            id: 0,
            title: "Вітання після опису проблеми",
            body: "Вітаємо!\nДякуємо за звернення.\n\nОтримали ваш запит, перевіряємо.",
            category: "general",
            command: "/запит"
        },
        {
            id: 0,
            title: "Запит додаткової інформації",
            body: "Для того, щоб ми могли допомогти якнайшвидше, будь ласка, надішліть більше деталей про проблему: що саме сталося, коли це виникло та чи зʼявляється помилка на екрані.",
            category: "general",
            command: "/інфо"
        },
        {
            id: 0,
            title: "Скидання пароля",
            body: "Для скидання пароля, будь ласка, скористайтеся посиланням \"Забули пароль?\" на сторінці входу. Якщо лист не надходить, перевірте папку \"Спам\" або напишіть нам ще раз.",
            category: "auth",
            command: "/пароль"
        },
        {
            id: 0,
            title: "Технічні роботи",
            body: "На даний момент проводяться планові технічні роботи. Ми вже займаємося перевіркою. Дякуємо за розуміння.",
            category: "tech",
            command: "/роботи"
        },
        {
            id: 0,
            title: "Завершення розмови",
            body: "Раді, що змогли допомогти. Якщо у Вас виникнуть додаткові запитання, будь ласка, звертайтесь. Гарного дня!",
            category: "general",
            command: "/бувай"
        }
    ];

    const templateCatalog = buildTemplateCatalog(templatesSeed);

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

    function senderLabel(senderType) {
        return {
            staff: "Вебмайстер",
            customer: "Клієнт",
            system: "Система"
        }[senderType] || "Система";
    }

    function messageClass(senderType) {
        if (senderType === "staff") return "message--me";
        if (senderType === "customer") return "message--other";
        return "message--system";
    }

    function parseMessageDate(message) {
        const value = String(message && message.created_at ? message.created_at : "").trim();
        if (!value) return null;
        const parsed = new Date(value.replace(" ", "T"));
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    function formatDateKey(date) {
        if (!date) return "";
        return date.getFullYear() + "-" + String(date.getMonth() + 1).padStart(2, "0") + "-" + String(date.getDate()).padStart(2, "0");
    }

    function formatDateLabel(date) {
        if (!date) return "";
        const today = new Date();
        const yesterday = new Date();
        yesterday.setDate(today.getDate() - 1);
        const key = formatDateKey(date);

        if (key === formatDateKey(today)) return "Сьогодні";
        if (key === formatDateKey(yesterday)) return "Вчора";

        return String(date.getDate()).padStart(2, "0") + "." + String(date.getMonth() + 1).padStart(2, "0") + "." + date.getFullYear();
    }

    function formatTime(date) {
        if (!date) return "";
        return String(date.getHours()).padStart(2, "0") + ":" + String(date.getMinutes()).padStart(2, "0");
    }

    function formatTicketMoment(value) {
        const parsed = parseMessageDate({ created_at: value });
        if (!parsed) return "";

        if (formatDateKey(parsed) === formatDateKey(new Date())) {
            return formatTime(parsed);
        }

        return String(parsed.getDate()).padStart(2, "0") + "." + String(parsed.getMonth() + 1).padStart(2, "0");
    }

    function formatInfoDate(value) {
        const parsed = parseMessageDate({ created_at: value });
        if (!parsed) return "";
        return String(parsed.getDate()).padStart(2, "0") + "." + String(parsed.getMonth() + 1).padStart(2, "0") + "." + parsed.getFullYear();
    }

    function categoryLabel(category) {
        const found = templateCategories.find(function (item) {
            return item.key === category;
        });
        return found ? found.label : "Загальні";
    }

    function inferTemplateCategory(title, body) {
        const haystack = (String(title || "") + " " + String(body || "")).toLowerCase();
        if (/(парол|логін|авториз|вхід)/.test(haystack)) return "auth";
        if (/(техн|робот|помилк|сервер|сайт|недоступ)/.test(haystack)) return "tech";
        return "general";
    }

    function inferTemplateCommand(title, body, fallbackIndex) {
        const haystack = (String(title || "") + " " + String(body || "")).toLowerCase();
        if (/(вітан|доброго|вітаємо)/.test(haystack)) return "/привіт";
        if (/(додатков|детал|уточн)/.test(haystack)) return "/інфо";
        if (/(парол|логін|авториз|вхід)/.test(haystack)) return "/пароль";
        if (/(техн|робот|сервер|недоступ)/.test(haystack)) return "/роботи";
        if (/(гарного дня|раді, що змогли допомогти|заверш)/.test(haystack)) return "/бувай";
        return "/шаблон" + String(fallbackIndex + 1);
    }

    function buildTemplateCatalog(seed) {
        const merged = [];
        const seen = {};

        function pushTemplate(raw, fallbackIndex) {
            const title = String(raw && raw.title ? raw.title : "").trim();
            const body = String(raw && raw.body ? raw.body : "").trim();
            if (!title || !body) return;

            const key = title.toLowerCase() + "|" + body.toLowerCase();
            const normalized = {
                id: parseInt(raw && raw.id ? raw.id : 0, 10) || 0,
                title: title,
                body: body,
                category: raw && raw.category ? String(raw.category) : inferTemplateCategory(title, body),
                command: raw && raw.command ? String(raw.command) : inferTemplateCommand(title, body, fallbackIndex)
            };

            if (seen[key]) {
                if (!seen[key].id && normalized.id) {
                    seen[key].id = normalized.id;
                }
                return;
            }

            seen[key] = normalized;
            merged.push(normalized);
        }

        defaultTemplates.forEach(function (template, index) {
            pushTemplate(template, index);
        });

        (Array.isArray(seed) ? seed : []).forEach(function (template, index) {
            pushTemplate(template, index + defaultTemplates.length);
        });

        return merged;
    }

    function filterTemplates() {
        const query = templateSearchInput ? String(templateSearchInput.value || "").trim().toLowerCase() : "";
        return templateCatalog.filter(function (template) {
            if (activeTemplateCategory !== "all" && template.category !== activeTemplateCategory) {
                return false;
            }

            if (!query) {
                return true;
            }

            const haystack = [
                template.title,
                template.body,
                template.command,
                categoryLabel(template.category)
            ].join(" ").toLowerCase();

            return haystack.indexOf(query) !== -1;
        });
    }

    function renderTemplateFilters() {
        if (!templateFiltersWrap) return;

        templateFiltersWrap.innerHTML = templateCategories.map(function (category) {
            return '<button type="button" class="support-desk-template-filter' + (category.key === activeTemplateCategory ? ' is-active' : '') + '" data-template-category="' + escapeHtml(category.key) + '">' + escapeHtml(category.label) + '</button>';
        }).join("");

        templateFiltersWrap.querySelectorAll("[data-template-category]").forEach(function (button) {
            button.addEventListener("click", function () {
                activeTemplateCategory = button.dataset.templateCategory || "all";
                renderTemplateFilters();
                renderTemplateList();
            });
        });
    }

    function renderTemplateList() {
        if (!templateListWrap) return;

        const items = filterTemplates();
        if (!items.length) {
            templateListWrap.innerHTML = '<div class="support-desk-template-empty">Нічого не знайдено. Спробуйте інший запит або категорію.</div>';
            return;
        }

        templateListWrap.innerHTML = items.map(function (template, index) {
            return '' +
                '<button type="button" class="support-desk-template-card" data-template-apply="' + index + '">' +
                '<span class="support-desk-template-card__top">' +
                '<strong>' + escapeHtml(template.title) + '</strong>' +
                '<span class="support-desk-template-card__meta">' +
                '<span class="support-desk-template-card__command">' + escapeHtml(template.command) + '</span>' +
                '<span class="support-desk-template-card__tag">' + escapeHtml(categoryLabel(template.category)) + '</span>' +
                '</span>' +
                '</span>' +
                '<span class="support-desk-template-card__body">' + escapeHtml(template.body).replace(/\n/g, "<br>") + '</span>' +
                '</button>';
        }).join("");

        templateListWrap.querySelectorAll("[data-template-apply]").forEach(function (button) {
            button.addEventListener("click", function () {
                const template = items[parseInt(button.dataset.templateApply || "-1", 10)];
                if (template) {
                    applyTemplate(template);
                }
            });
        });
    }

    function openTemplateModal() {
        if (!templateModal) return;
        activeTemplateCategory = "all";
        if (templateSearchInput) {
            templateSearchInput.value = "";
        }
        renderTemplateFilters();
        renderTemplateList();
        templateModal.classList.add("show");
        templateModal.setAttribute("aria-hidden", "false");
        if (templateSearchInput) {
            window.setTimeout(function () {
                templateSearchInput.focus();
                templateSearchInput.select();
            }, 10);
        }
    }

    function closeTemplateModal() {
        if (!templateModal) return;
        templateModal.classList.remove("show");
        templateModal.setAttribute("aria-hidden", "true");
    }

    function openSpamModal() {
        if (!spamModal) return;
        spamModal.classList.add("show");
        spamModal.setAttribute("aria-hidden", "false");
    }

    function closeSpamModal() {
        if (!spamModal) return;
        spamModal.classList.remove("show");
        spamModal.setAttribute("aria-hidden", "true");
    }

    function applyTemplate(template) {
        const textarea = document.getElementById("supportDeskReplyMessage");
        const templateIdInput = document.getElementById("supportDeskTemplateId");
        const sendBtn = document.getElementById("supportDeskSendBtn");
        if (!textarea) return;

        const current = String(textarea.value || "").trim();
        textarea.value = current ? current + "\n\n" + template.body : template.body;
        if (templateIdInput) {
            templateIdInput.value = template.id > 0 ? String(template.id) : "";
        }
        autoResize(textarea);
        updateSendState(textarea, sendBtn);
        textarea.focus();
        textarea.setSelectionRange(textarea.value.length, textarea.value.length);
        closeTemplateModal();
    }

    function initTemplateModal() {
        if (!templateModal) return;

        templateModal.querySelectorAll("[data-template-modal-close]").forEach(function (button) {
            button.addEventListener("click", closeTemplateModal);
        });

        if (templateSearchInput) {
            templateSearchInput.addEventListener("input", renderTemplateList);
        }

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && templateModal.classList.contains("show")) {
                closeTemplateModal();
            }
        });

        renderTemplateFilters();
        renderTemplateList();
    }

    function initSpamModal() {
        if (!spamModal) return;

        spamModal.querySelectorAll("[data-spam-modal-close]").forEach(function (button) {
            button.addEventListener("click", closeSpamModal);
        });

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && spamModal.classList.contains("show")) {
                closeSpamModal();
            }
        });

        if (spamConfirmBtn) {
            spamConfirmBtn.addEventListener("click", markTicketAsSpam);
        }
    }

    function renderInfoContact(label, value, type) {
        const icon = type === "phone"
            ? '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5l1.5 -2.5l5 2v4a2 2 0 0 1 -2 2a16 16 0 0 1 -16 -16a2 2 0 0 1 2 -2" /></svg>'
            : '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10" /><path d="M3 7l9 6l9 -6" /></svg>';
        const safeValue = value || "Не вказано";
        let action = "";
        if (safeValue !== "Не вказано") {
            const href = type === "phone"
                ? "tel:" + String(safeValue).replace(/[^0-9+]/g, "")
                : "mailto:" + safeValue;
            action = '<a class="support-desk-info__contact-action" href="' + escapeHtml(href) + '" target="_blank" rel="noopener" aria-label="' + escapeHtml(label) + '"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 7h-5a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-5"/><path d="M10 14l10 -10"/><path d="M15 4h5v5"/></svg></a>';
        }

        return '<div class="support-desk-info__contact"><span class="support-desk-info__contact-icon" aria-hidden="true">' + icon + '</span><div class="support-desk-info__contact-copy"><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(safeValue) + '</strong></div>' + action + '</div>';
    }

    function renderInfoPanel(ticket) {
        if (!ticket) {
            return '<div class="chat-info support-desk-info"><div class="chat-info__mobile-head"><span>Інформація</span></div><div class="chat-info__empty">Оберіть звернення, щоб побачити інформацію про клієнта.</div></div>';
        }

        const name = ticket.requester_label || "Клієнт";
        const avatar = ticket.requester_avatar || "";
        const initial = ticket.requester_initial || "K";
        const since = formatInfoDate(ticket.requester_since || "") || "";
        const created = formatInfoDate(ticket.created_at || "") || "";
        const total = String(parseInt(ticket.requester_tickets_count || 0, 10) || 0);

        let html = '<div class="chat-info support-desk-info">';
        html += '<div class="chat-info__mobile-head"><span>Інформація</span></div>';
        html += '<div class="support-desk-info__head"><h2>Інформація про клієнта</h2></div>';
        html += '<div class="chat-info__card support-desk-info__card">';
        if (avatar) {
            html += '<img src="' + escapeHtml(avatar) + '" alt="' + escapeHtml(name) + '" class="chat-info__avatar">';
        } else {
            html += '<div class="support-desk-info__avatar-fallback">' + escapeHtml(initial) + '</div>';
        }
        html += '<h2>' + escapeHtml(name) + '</h2>';
        html += '<p>' + escapeHtml(since ? ("Клієнт у підтримці з " + since) : "Інформація по зверненнях клієнта") + '</p>';
        html += '</div>';
        html += '<div class="chat-info__section support-desk-info__section"><h3>Контактна інформація</h3><div class="support-desk-info__contact-grid">';
        html += renderInfoContact("Email", ticket.requester_email || "Не вказано", "mail");
        html += renderInfoContact("Телефон", ticket.requester_phone || "Не вказано", "phone");
        html += '</div></div>';
        html += '<div class="chat-info__section support-desk-info__section"><h3>Статистика</h3><div class="support-desk-info__stats">';
        html += '<div class="support-desk-info__stat"><strong>' + escapeHtml(total) + '</strong><span>Всього звернень</span></div>';
        html += '<div class="support-desk-info__stat"><strong>' + escapeHtml(created || "Немає") + '</strong><span>Створено звернення</span></div>';
        html += '</div></div>';
        html += '</div>';
        return html;
    }

    function setMobileView(view) {
        root.dataset.mobileView = view;
    }

    function syncRenderedDateKey() {
        const renderedMessages = detailWrap ? detailWrap.querySelectorAll("[data-date-key]") : [];
        if (!renderedMessages.length) {
            lastRenderedDateKey = null;
            return;
        }

        const last = renderedMessages[renderedMessages.length - 1];
        lastRenderedDateKey = String(last.getAttribute("data-date-key") || "") || null;
    }

    function renderTicketList(tickets) {
        if (!Array.isArray(tickets) || tickets.length === 0) {
            return '<div class="support-desk-empty">Поки що тут порожньо.</div>';
        }

        return tickets.map(function (ticket) {
            const ticketId = parseInt(ticket.id || 0, 10) || 0;
            const selected = ticketId === selectedTicketId ? " is-active is-selected" : "";

            return '' +
                '<button type="button" class="chat-item support-ticket-list-item' + selected + '" data-ticket-open="' + ticketId + '">' +
                '<span class="support-ticket-list-side">' +
                '<span class="support-ticket-list-avatar" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h4" /><path d="M15 19l2 2l4 -4" /></svg></span>' +
                '<span class="support-ticket-badge support-ticket-badge--' + escapeHtml(ticket.status || "new") + ' support-ticket-badge--list">' + escapeHtml(statusLabel(ticket.status || "new")) + '</span>' +
                '</span>' +
                '<span class="chat-item__body">' +
                '<span class="chat-item__top"><strong>' + escapeHtml(ticket.requester_label || "Клієнт") + '</strong><span class="chat-item__time">' + escapeHtml(formatTicketMoment(ticket.last_message_at || "")) + '</span></span>' +
                '<span class="chat-item__bottom"><span class="chat-item__preview">' + escapeHtml(ticket.last_message_preview || "Без повідомлень") + '</span></span>' +
                '<span class="support-ticket-list-meta"><span class="support-ticket-list-code">#' + ticketId + '</span></span>' +
                '</span>' +
                '</button>';
        }).join("");
    }

    function buildMessageHtml(message, includeDateDivider) {
        const senderType = String(message.sender_type || "system");
        const createdAt = parseMessageDate(message);
        const dateKey = formatDateKey(createdAt);
        let html = "";

        if (includeDateDivider && dateKey) {
            html += '<div class="date-divider"><span>' + escapeHtml(formatDateLabel(createdAt)) + "</span></div>";
        }

        html += '<article class="message ' + messageClass(senderType) + '" data-message-id="' + parseInt(message.id || 0, 10) + '" data-date-key="' + escapeHtml(dateKey) + '">';
        html += '<div class="support-chat-message__sender">' + escapeHtml(senderLabel(senderType)) + "</div>";
        if (message.image_path) {
            html += '<div class="message__image"><a href="' + escapeHtml(message.image_path) + '" target="_blank" rel="noopener"><img src="' + escapeHtml(message.image_path) + '" alt=""></a></div>';
        }
        if (message.body) {
            html += '<div class="message__text">' + escapeHtml(message.body).replace(/\n/g, "<br>") + "</div>";
        }
        if (createdAt) {
            html += '<div class="message__time">' + escapeHtml(formatTime(createdAt)) + "</div>";
        }
        html += "</article>";
        return html;
    }

    function renderMessages(messages) {
        if (!Array.isArray(messages) || messages.length === 0) {
            return '<div class="chat-empty-thread">Повідомлень поки немає.</div>';
        }

        let previousDateKey = null;
        return messages.map(function (message) {
            const currentDateKey = formatDateKey(parseMessageDate(message));
            const html = buildMessageHtml(message, currentDateKey !== "" && currentDateKey !== previousDateKey);
            if (currentDateKey) {
                previousDateKey = currentDateKey;
            }
            return html;
        }).join("");
    }

    function canMarkResolved(ticket) {
        const assigneeId = parseInt(ticket && ticket.assignee_user_id ? ticket.assignee_user_id : 0, 10) || 0;
        return !!ticket && ticket.status === "open" && assigneeId === currentUserId;
    }

    function renderResolveAction(ticket) {
        if (!canMarkResolved(ticket)) {
            return "";
        }

        return '<button type="button" class="support-desk-resolve-btn" data-ticket-resolve><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-check"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M5 12l5 5l10 -10" /></svg><span>Відзначити як вирішено</span></button>';
    }

    function canMarkSpam(ticket) {
        return !!ticket && ticket.status !== "spam";
    }

    function updateLastMessageId(messages) {
        (messages || []).forEach(function (message) {
            const messageId = parseInt(message.id || 0, 10) || 0;
            if (messageId > lastMessageId) {
                lastMessageId = messageId;
            }
        });
    }

    function attachPaneHandlers() {
        if (!listWrap) return;
        listWrap.querySelectorAll("[data-ticket-open]").forEach(function (button) {
            if (button.dataset.bound === "1") return;
            button.dataset.bound = "1";
            button.addEventListener("click", function () {
                const ticketId = parseInt(button.dataset.ticketOpen || "0", 10) || 0;
                if (ticketId > 0) {
                    openTicket(ticketId);
                }
            });
        });
    }

    function updateCounts(counts) {
        tabs.forEach(function (tab) {
            const countEl = tab.querySelector(".support-desk-tab__count");
            if (countEl) {
                countEl.textContent = String((counts && counts[tab.dataset.bucket]) || 0);
            }
        });

        const queueStat = document.querySelector("[data-support-desk-header-queue]");
        if (queueStat) {
            queueStat.textContent = String((counts && counts.queue) || 0);
        }

        const myStat = document.querySelector("[data-support-desk-header-my]");
        if (myStat) {
            myStat.textContent = String((counts && counts.my) || 0);
        }
    }

    function readStoredBucket() {
        try {
            const value = window.localStorage.getItem(bucketStorageKey);
            if (!value) return "";
            return tabs.some(function (tab) {
                return tab.dataset.bucket === value;
            }) ? value : "";
        } catch (err) {
            return "";
        }
    }

    function storeActiveBucket(bucket) {
        try {
            window.localStorage.setItem(bucketStorageKey, bucket);
        } catch (err) {
            return;
        }
    }

    async function loadBucket(bucket, preserveSelection) {
        try {
            const res = await fetch("/support-desk.php?action=support_list_bucket&bucket=" + encodeURIComponent(bucket), {
                credentials: "same-origin",
                cache: "no-store"
            });
            const data = await res.json();
            if (data.status !== "ok") return;

            const pane = panes.find(function (item) {
                return item.dataset.bucketPane === bucket;
            });
            if (pane) {
                pane.innerHTML = renderTicketList(data.tickets || []);
            }

            attachPaneHandlers();
            updateCounts(data.counts || {});

            if (!preserveSelection && Array.isArray(data.tickets) && data.tickets.length > 0 && !selectedTicketId) {
                openTicket(parseInt(data.tickets[0].id || 0, 10) || 0);
            }
        } catch (err) {
            console.error(err);
        }
    }

    function renderDetail(ticket, messages) {
        selectedTicketId = parseInt(ticket.id || 0, 10) || 0;
        selectedTicketStatus = ticket.status || "new";
        root.dataset.selectedTicketId = String(selectedTicketId);

        let html = "";
        html += '<div class="chat-window support-desk-chat-window">';
        html += '<header class="chat-header support-desk-chat-header">';
        html += '<button type="button" class="chat-nav-btn chat-back-btn support-desk-back-btn" aria-label="Назад до списку звернень"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6l6-6"/></svg></button>';
        html += '<div class="chat-header__meta"><span class="support-desk-chat-avatar" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M6 21v-2a4 4 0 0 1 4 -4h4" /><path d="M15 19l2 2l4 -4" /></svg></span><div class="support-desk-chat-headline"><strong>' + escapeHtml(ticket.requester_label || "Клієнт") + '</strong><span>Звернення #' + selectedTicketId + '</span></div></div>';
        html += '<div class="support-desk-ticket-side"><div class="support-desk-ticket-meta"><span class="support-ticket-badge support-ticket-badge--' + escapeHtml(selectedTicketStatus) + '" data-ticket-status-badge>' + escapeHtml(statusLabel(selectedTicketStatus)) + '</span><span class="support-desk-ticket-assignee" data-ticket-assignee>' + escapeHtml(ticket.assignee_label || "Без виконавця") + '</span></div><div class="support-desk-ticket-actions" data-ticket-header-actions>' + renderResolveAction(ticket) + '</div></div>';
        html += '</header>';
        html += '<div class="chat-messages support-desk-messages" id="supportDeskMessages">' + renderMessages(messages) + '</div>';
        html += '<div class="support-desk-compose-shell">';
        html += '<form id="supportDeskReplyForm" class="chat-input support-desk-compose" enctype="multipart/form-data">';
        html += '<input type="hidden" name="ticket_id" value="' + selectedTicketId + '">';
        html += '<div class="chat-input__row support-desk-compose-row">';
        html += '<button type="button" class="chat-input__attach support-desk-action-trigger support-desk-template-trigger" title="Відкрити шаблони відповідей" aria-label="Відкрити шаблони відповідей"><span class="support-desk-action-trigger__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-file-text"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4" /><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2" /><path d="M9 9l1 0" /><path d="M9 13l6 0" /><path d="M9 17l6 0" /></svg></span><span class="support-desk-action-trigger__label">Шаблон</span></button>';
        html += '<button type="button" class="chat-input__attach support-desk-action-trigger support-desk-action-trigger--danger support-desk-spam-trigger" title="Позначити звернення як спам" aria-label="Позначити звернення як спам" data-ticket-spam><span class="support-desk-action-trigger__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-flag"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M5 5a5 5 0 0 1 7 0a5 5 0 0 0 7 0v9a5 5 0 0 1 -7 0a5 5 0 0 0 -7 0v-9" /><path d="M5 21v-7" /></svg></span><span class="support-desk-action-trigger__label">Спам</span></button>';
        html += '<textarea name="message" id="supportDeskReplyMessage" placeholder="Відповідь клієнту..." rows="1" maxlength="2000"></textarea>';
        html += '<input type="hidden" name="template_id" id="supportDeskTemplateId" value="">';
        html += '<button type="submit" id="supportDeskSendBtn" disabled><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 14l11 -11" /><path d="M21 3l-6.5 18a.55 .55 0 0 1 -1 0l-3.5 -7l-7 -3.5a.55 .55 0 0 1 0 -1l18 -6.5" /></svg></button>';
        html += '</div>';
        html += '<div class="chat-input__meta"><span>Шаблони відповідей доступні через кнопку ліворуч.</span><span>Enter для відправки, Shift+Enter для нового рядка</span></div>';
        html += '</form>';
        html += '</div>';
        html += '</div>';

        detailWrap.innerHTML = html;
        if (infoWrap) {
            infoWrap.innerHTML = renderInfoPanel(ticket);
        }
        detailWrap.dataset.ticketId = String(selectedTicketId);
        detailWrap.dataset.ticketStatus = selectedTicketStatus;
        lastMessageId = 0;
        updateLastMessageId(messages);
        syncRenderedDateKey();
        bindDetailHandlers();

        const messagesBox = document.getElementById("supportDeskMessages");
        if (messagesBox) {
            messagesBox.scrollTop = messagesBox.scrollHeight;
        }
    }

    async function openTicket(ticketId) {
        if (!ticketId) return;

        selectedTicketId = ticketId;
        root.dataset.selectedTicketId = String(ticketId);
        setMobileView("chat");

        try {
            const res = await fetch("/support-desk.php?action=support_get_ticket&ticket_id=" + encodeURIComponent(ticketId), {
                credentials: "same-origin",
                cache: "no-store"
            });
            const data = await res.json();
            if (data.status !== "ok" || !data.ticket) return;

            renderDetail(data.ticket, data.messages || []);

            panes.forEach(function (pane) {
                pane.querySelectorAll(".support-ticket-list-item").forEach(function (item) {
                    item.classList.toggle("is-selected", parseInt(item.dataset.ticketOpen || "0", 10) === ticketId);
                    item.classList.toggle("is-active", parseInt(item.dataset.ticketOpen || "0", 10) === ticketId);
                });
            });
        } catch (err) {
            console.error(err);
        }
    }

    function autoResize(textarea) {
        if (!textarea) return;
        textarea.style.height = "auto";
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + "px";
    }

    function updateSendState(textarea, sendBtn) {
        if (!textarea || !sendBtn) return;
        const hasText = (textarea.value || "").trim() !== "";
        sendBtn.disabled = !hasText;
    }

    function bindDetailHandlers() {
        const form = document.getElementById("supportDeskReplyForm");
        const textarea = document.getElementById("supportDeskReplyMessage");
        const sendBtn = document.getElementById("supportDeskSendBtn");
        const backBtn = detailWrap.querySelector(".support-desk-back-btn");
        const templateBtn = detailWrap.querySelector(".support-desk-template-trigger");
        const spamBtn = detailWrap.querySelector("[data-ticket-spam]");
        const templateIdInput = document.getElementById("supportDeskTemplateId");
        const resolveBtn = detailWrap.querySelector("[data-ticket-resolve]");

        if (backBtn) {
            backBtn.addEventListener("click", function () {
                setMobileView("list");
            });
        }

        if (templateBtn) {
            templateBtn.addEventListener("click", openTemplateModal);
        }

        if (spamBtn) {
            spamBtn.addEventListener("click", openSpamModal);
            spamBtn.disabled = !canMarkSpam({ status: selectedTicketStatus });
        }

        if (resolveBtn) {
            resolveBtn.addEventListener("click", requestResolutionConfirmation);
        }

        if (textarea) {
            textarea.addEventListener("input", function () {
                if (templateIdInput) {
                    templateIdInput.value = "";
                }
                autoResize(textarea);
                updateSendState(textarea, sendBtn);
            });
            textarea.addEventListener("keydown", function (event) {
                if (event.key === "Enter" && !event.shiftKey) {
                    event.preventDefault();
                    if (form && sendBtn && !sendBtn.disabled) {
                        if (typeof form.requestSubmit === "function") {
                            form.requestSubmit();
                        } else {
                            form.dispatchEvent(new Event("submit", { cancelable: true, bubbles: true }));
                        }
                    }
                }
            });
            autoResize(textarea);
        }

        updateSendState(textarea, sendBtn);

        if (form) {
            form.addEventListener("submit", async function (event) {
                event.preventDefault();

                const text = textarea ? (textarea.value || "").trim() : "";
                if (!text) return;

                if (sendBtn) {
                    sendBtn.disabled = true;
                }

                const data = new FormData(form);
                try {
                    const res = await fetch("/support-desk.php?action=support_send_message", {
                        method: "POST",
                        body: data,
                        credentials: "same-origin"
                    });
                    const payload = await res.json();
                    if (payload.status === "ok") {
                        await Promise.all([
                            loadBucket(activeBucket, true),
                            openTicket(selectedTicketId)
                        ]);
                    } else if (payload.msg) {
                        alert(payload.msg);
                    }
                } catch (err) {
                    console.error(err);
                    alert("Не вдалося надіслати повідомлення.");
                } finally {
                    updateSendState(textarea, sendBtn);
                }
            });
        }

    }

    async function requestResolutionConfirmation() {
        if (!selectedTicketId) return;

        const resolveBtn = detailWrap.querySelector("[data-ticket-resolve]");
        if (resolveBtn) {
            resolveBtn.disabled = true;
        }

        try {
            const formData = new FormData();
            formData.append("ticket_id", String(selectedTicketId));

            const res = await fetch("/support-desk.php?action=support_request_resolution", {
                method: "POST",
                body: formData,
                credentials: "same-origin"
            });
            const payload = await res.json();
            if (payload.status === "ok") {
                await Promise.all([
                    loadBucket(activeBucket, true),
                    openTicket(selectedTicketId)
                ]);
            } else if (payload.msg) {
                alert(payload.msg);
            }
        } catch (err) {
            console.error(err);
            alert("Не вдалося надіслати запит на підтвердження вирішення.");
        } finally {
            const freshResolveBtn = detailWrap.querySelector("[data-ticket-resolve]");
            if (freshResolveBtn) {
                freshResolveBtn.disabled = false;
            }
        }
    }

    async function markTicketAsSpam() {
        if (!selectedTicketId || !spamConfirmBtn) return;

        spamConfirmBtn.disabled = true;
        try {
            const formData = new FormData();
            formData.append("ticket_id", String(selectedTicketId));
            formData.append("status", "spam");

            const res = await fetch("/support-desk.php?action=support_change_status", {
                method: "POST",
                body: formData,
                credentials: "same-origin"
            });
            const payload = await res.json();
            if (payload.status === "ok") {
                closeSpamModal();
                await Promise.all([
                    loadBucket(activeBucket, true),
                    loadBucket("spam", true),
                    openTicket(selectedTicketId)
                ]);
            } else if (payload.msg) {
                alert(payload.msg);
            }
        } catch (err) {
            console.error(err);
            alert("Не вдалося позначити звернення як спам.");
        } finally {
            spamConfirmBtn.disabled = false;
        }
    }

    async function refreshSelectedTicket() {
        if (!selectedTicketId) return;

        const messagesBox = document.getElementById("supportDeskMessages");
        const stickToBottom = messagesBox ? (messagesBox.scrollHeight - messagesBox.scrollTop - messagesBox.clientHeight) < 80 : true;

        try {
            const res = await fetch("/support-desk.php?action=support_get_messages&ticket_id=" + encodeURIComponent(selectedTicketId) + "&last_id=" + encodeURIComponent(lastMessageId), {
                credentials: "same-origin",
                cache: "no-store"
            });
            const data = await res.json();
            if (data.status !== "ok") return;

            if (data.ticket) {
                selectedTicketStatus = data.ticket.status || selectedTicketStatus;
                detailWrap.dataset.ticketStatus = selectedTicketStatus;
                if (infoWrap) {
                    infoWrap.innerHTML = renderInfoPanel(data.ticket);
                }

                const badge = detailWrap.querySelector("[data-ticket-status-badge]");
                if (badge) {
                    badge.className = "support-ticket-badge support-ticket-badge--" + selectedTicketStatus;
                    badge.textContent = statusLabel(selectedTicketStatus);
                }

                const assignee = detailWrap.querySelector("[data-ticket-assignee]");
                if (assignee) {
                    assignee.textContent = data.ticket.assignee_label || "Без виконавця";
                }

                const headerActions = detailWrap.querySelector("[data-ticket-header-actions]");
                if (headerActions) {
                    headerActions.innerHTML = renderResolveAction(data.ticket);
                    const resolveBtn = headerActions.querySelector("[data-ticket-resolve]");
                    if (resolveBtn) {
                        resolveBtn.addEventListener("click", requestResolutionConfirmation);
                    }
                }

                const spamBtn = detailWrap.querySelector("[data-ticket-spam]");
                if (spamBtn) {
                    spamBtn.disabled = !canMarkSpam(data.ticket);
                }

            }

            if (!messagesBox || !Array.isArray(data.messages) || data.messages.length === 0) {
                return;
            }

            const empty = messagesBox.querySelector(".chat-empty-thread");
            if (empty) {
                empty.remove();
            }

            data.messages.forEach(function (message) {
                const messageId = parseInt(message.id || 0, 10) || 0;
                if (!messageId || messagesBox.querySelector('[data-message-id="' + String(messageId) + '"]')) {
                    return;
                }
                const nextDateKey = formatDateKey(parseMessageDate(message));
                messagesBox.insertAdjacentHTML("beforeend", buildMessageHtml(message, nextDateKey !== "" && nextDateKey !== lastRenderedDateKey));
                if (nextDateKey) {
                    lastRenderedDateKey = nextDateKey;
                }
            });
            updateLastMessageId(data.messages);

            if (stickToBottom) {
                messagesBox.scrollTop = messagesBox.scrollHeight;
            }
        } catch (err) {
            console.error(err);
        }
    }

    async function pollUpdates() {
        const tasks = [loadBucket(activeBucket, true)];
        if (activeBucket !== "queue") {
            tasks.push(loadBucket("queue", true));
        }
        await Promise.all(tasks);
        await refreshSelectedTicket();
    }

    function startPolling() {
        if (pollTimer) {
            window.clearInterval(pollTimer);
        }
        pollTimer = window.setInterval(pollUpdates, 4000);
    }

    function activateTab(bucket) {
        activeBucket = bucket;
        storeActiveBucket(bucket);
        tabs.forEach(function (tab) {
            tab.classList.toggle("is-active", tab.dataset.bucket === bucket);
        });
        panes.forEach(function (pane) {
            pane.classList.toggle("is-active", pane.dataset.bucketPane === bucket);
        });
    }

    function detectInitialBucket() {
        const storedBucket = readStoredBucket();
        if (storedBucket) {
            activeBucket = storedBucket;
            return;
        }

        const selectedPane = panes.find(function (pane) {
            return !!pane.querySelector(".support-ticket-list-item.is-selected");
        });

        if (selectedPane && selectedPane.dataset.bucketPane) {
            activeBucket = selectedPane.dataset.bucketPane;
        }
    }

    function initTabs() {
        tabs.forEach(function (tab) {
            tab.addEventListener("click", async function () {
                const bucket = tab.dataset.bucket || "queue";
                activateTab(bucket);
                setMobileView("list");
                await loadBucket(bucket, true);
            });
        });
        attachPaneHandlers();
    }

    detectInitialBucket();
    activateTab(activeBucket);
    initTabs();
    initTemplateModal();
    initSpamModal();
    startPolling();
    loadBucket(activeBucket, true);
    if (selectedTicketId > 0) {
        openTicket(selectedTicketId);
    }
});
