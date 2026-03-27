document.addEventListener("DOMContentLoaded", function () {
    const root = document.getElementById("supportClientRoot");
    if (!root) return;

    if (document.body) {
        document.body.classList.add("support-chat-mobile-open");
    }

    const messagesEl = document.getElementById("supportClientMessages");
    const form = document.getElementById("supportClientForm");
    const textarea = document.getElementById("supportClientMessage");
    const fileInput = document.getElementById("supportClientImg");
    const preview = document.getElementById("supportClientPreview");
    const sendBtn = document.getElementById("supportClientSend");
    const ticketInput = form ? form.querySelector("input[name='ticket_id']") : null;
    const captchaModal = document.getElementById("supportClientCaptchaModal");
    const captchaQuestion = document.getElementById("supportClientCaptchaQuestion");
    const captchaAnswer = document.getElementById("supportClientCaptchaAnswer");
    const captchaSubmit = document.getElementById("supportClientCaptchaSubmit");
    const captchaError = document.getElementById("supportClientCaptchaError");
    const resolutionWrap = document.getElementById("supportClientResolution");

    let ticketId = parseInt(root.dataset.ticketId || "0", 10) || 0;
    let lastMessageId = 0;
    let lastRenderedDateKey = null;
    let pollTimer = null;
    let currentTicket = null;
    let isAuthenticated = root.dataset.isAuthenticated === "1";
    let captchaPassed = root.dataset.captchaPassed === "1";
    let captchaCorrectAnswer = 0;

    if (resolutionWrap) {
        const initialConfirmBtn = resolutionWrap.querySelector("[data-support-confirm-resolution]");
        if (initialConfirmBtn) {
            initialConfirmBtn.addEventListener("click", confirmResolution);
        }
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (char) {
            return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[char];
        });
    }

    function setCookie(name, value, days) {
        const expires = new Date(Date.now() + days * 864e5).toUTCString();
        document.cookie = name + "=" + encodeURIComponent(value) + "; expires=" + expires + "; path=/";
    }

    function showCaptchaError(message) {
        if (!captchaError) return;
        captchaError.hidden = !message;
        captchaError.textContent = message || "";
    }

    function generateCaptcha() {
        const a = Math.floor(Math.random() * 5) + 5;
        const b = Math.floor(Math.random() * 5) + 1;
        captchaCorrectAnswer = a + b;
        if (captchaQuestion) {
            captchaQuestion.textContent = a + " + " + b;
        }
        if (captchaAnswer) {
            captchaAnswer.value = "";
        }
        showCaptchaError("");
    }

    function openCaptchaModal() {
        if (!captchaModal || isAuthenticated || captchaPassed) return;
        generateCaptcha();
        captchaModal.classList.add("show");
        captchaModal.setAttribute("aria-hidden", "false");
        if (captchaAnswer) {
            window.setTimeout(function () {
                captchaAnswer.focus();
            }, 10);
        }
    }

    function closeCaptchaModal() {
        if (!captchaModal) return;
        captchaModal.classList.remove("show");
        captchaModal.setAttribute("aria-hidden", "true");
        showCaptchaError("");
    }

    function ensureCaptchaPassed() {
        if (isAuthenticated || captchaPassed) {
            return true;
        }
        openCaptchaModal();
        return false;
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
            customer: "Ви",
            staff: "Підтримка",
            system: "Система"
        }[senderType] || "Система";
    }

    function messageClass(senderType) {
        if (senderType === "customer") return "message--me";
        if (senderType === "staff") return "message--other";
        return "message--system";
    }

    function parseMessageDate(message) {
        const value = String(message && message.created_at ? message.created_at : "").trim();
        if (!value) return null;
        const normalized = value.replace(" ", "T");
        const parsed = new Date(normalized);
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    function formatDateKey(date) {
        if (!date) return "";
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");
        return year + "-" + month + "-" + day;
    }

    function formatTime(date) {
        if (!date) return "";
        return String(date.getHours()).padStart(2, "0") + ":" + String(date.getMinutes()).padStart(2, "0");
    }

    function formatDateLabel(date) {
        if (!date) return "";
        const today = new Date();
        const yesterday = new Date();
        yesterday.setDate(today.getDate() - 1);
        const dateKey = formatDateKey(date);

        if (dateKey === formatDateKey(today)) return "Сьогодні";
        if (dateKey === formatDateKey(yesterday)) return "Вчора";

        return String(date.getDate()).padStart(2, "0") + "." + String(date.getMonth() + 1).padStart(2, "0") + "." + date.getFullYear();
    }

    function syncRenderedDateKey() {
        const renderedMessages = messagesEl ? messagesEl.querySelectorAll("[data-date-key]") : [];
        if (!renderedMessages.length) {
            lastRenderedDateKey = null;
            return;
        }

        const last = renderedMessages[renderedMessages.length - 1];
        lastRenderedDateKey = String(last.getAttribute("data-date-key") || "") || null;
    }

    function scrollToBottom() {
        if (messagesEl) {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }
    }

    function resetPreview() {
        if (!preview) return;
        preview.innerHTML = "";
        preview.classList.remove("is-visible");
        preview.setAttribute("aria-hidden", "true");
    }

    function updateLastMessageId(messages) {
        (messages || []).forEach(function (message) {
            const id = parseInt(message.id || 0, 10) || 0;
            if (id > lastMessageId) {
                lastMessageId = id;
            }
        });
    }

    function renderPreview() {
        if (!fileInput || !preview) return;
        resetPreview();

        if (!fileInput.files || !fileInput.files[0]) {
            return;
        }

        const reader = new FileReader();
        reader.onload = function () {
            preview.classList.add("is-visible");
            preview.removeAttribute("aria-hidden");
            preview.innerHTML = '<div class="chat-image-preview-card"><img src="' + escapeHtml(reader.result || "") + '" alt=""><button type="button" class="chat-image-preview-remove" aria-label="Видалити зображення">&times;</button></div>';
            const removeBtn = preview.querySelector(".chat-image-preview-remove");
            if (removeBtn) {
                removeBtn.addEventListener("click", function () {
                    fileInput.value = "";
                    resetPreview();
                    updateSendState();
                });
            }
        };
        reader.readAsDataURL(fileInput.files[0]);
    }

    function updateSendState() {
        if (!sendBtn || !textarea) return;
        const hasText = (textarea.value || "").trim() !== "";
        const hasImage = !!(fileInput && fileInput.files && fileInput.files.length > 0);
        sendBtn.disabled = !hasText && !hasImage;
    }

    function autoResize() {
        if (!textarea) return;
        textarea.style.height = "auto";
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + "px";
    }

    function buildMessageHtml(message, includeDateDivider) {
        const senderType = String(message.sender_type || "system");
        const createdAt = parseMessageDate(message);
        const dateKey = formatDateKey(createdAt);
        let html = "";

        if (includeDateDivider && dateKey) {
            html += '<div class="date-divider"><span>' + escapeHtml(formatDateLabel(createdAt)) + "</span></div>";
        }

        html += '<article class="message ' + messageClass(senderType) + " support-chat-message support-chat-message--" + escapeHtml(senderType) + '" data-message-id="' + parseInt(message.id || 0, 10) + '" data-date-key="' + escapeHtml(dateKey) + '">';
        if (senderType !== "customer") {
            html += '<div class="support-chat-message__sender">' + escapeHtml(senderLabel(senderType)) + "</div>";
        }
        if (message.image_path) {
            html += '<div class="message__image support-chat-message__image"><a href="' + escapeHtml(message.image_path) + '" target="_blank" rel="noopener"><img src="' + escapeHtml(message.image_path) + '" alt=""></a></div>';
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

    function renderMessagesHtml(messages) {
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

    function appendMessage(message) {
        if (!messagesEl || !message || !message.id) return;
        if (messagesEl.querySelector('[data-message-id="' + String(message.id) + '"]')) return;

        const empty = messagesEl.querySelector(".chat-empty-thread");
        if (empty) {
            empty.remove();
        }

        const nextDateKey = formatDateKey(parseMessageDate(message));
        messagesEl.insertAdjacentHTML("beforeend", buildMessageHtml(message, nextDateKey !== "" && nextDateKey !== lastRenderedDateKey));
        updateLastMessageId([message]);
        if (nextDateKey) {
            lastRenderedDateKey = nextDateKey;
        }
    }

    function replaceMessages(messages) {
        if (!messagesEl) return;
        if (!Array.isArray(messages) || messages.length === 0) {
            messagesEl.innerHTML = '<div class="chat-empty-thread">Повідомлень поки немає. Напишіть першим.</div>';
            lastMessageId = 0;
            lastRenderedDateKey = null;
            return;
        }

        messagesEl.innerHTML = renderMessagesHtml(messages);
        lastMessageId = 0;
        updateLastMessageId(messages);
        syncRenderedDateKey();
        scrollToBottom();
    }

    function renderResolutionPanel() {
        if (!resolutionWrap) return;

        const pending = !!(currentTicket && currentTicket.resolution_confirmation_pending);
        resolutionWrap.hidden = !pending;
        if (!pending) {
            resolutionWrap.innerHTML = "";
            return;
        }

        resolutionWrap.innerHTML = '' +
            '<div class="support-client-resolution__card">' +
            '<div class="support-client-resolution__copy">' +
            '<strong>Підтвердіть, будь ласка, що проблему вирішено</strong>' +
            '<span>Якщо питання ще актуальне, просто надішліть нове повідомлення в чат.</span>' +
            '</div>' +
            '<button type="button" class="support-client-resolution__btn" data-support-confirm-resolution>Підтвердити вирішення</button>' +
            '</div>';

        const confirmBtn = resolutionWrap.querySelector("[data-support-confirm-resolution]");
        if (confirmBtn) {
            confirmBtn.addEventListener("click", confirmResolution);
        }
    }

    function setTicket(nextTicket) {
        if (!nextTicket) return;

        currentTicket = nextTicket;
        ticketId = parseInt(nextTicket.id || 0, 10) || 0;
        root.dataset.ticketId = String(ticketId);
        if (ticketInput) {
            ticketInput.value = String(ticketId);
        }

        const nextCode = ticketId > 0 ? ("#" + ticketId) : "Нове звернення";
        root.querySelectorAll("[data-support-ticket-code], [data-support-ticket-code-label]").forEach(function (node) {
            node.textContent = nextCode;
        });

        const status = nextTicket.status || "new";
        root.querySelectorAll("[data-support-ticket-status]").forEach(function (badge) {
            badge.className = "support-ticket-badge support-ticket-badge--" + status;
            badge.textContent = statusLabel(status);
        });
        root.querySelectorAll("[data-support-ticket-status-label]").forEach(function (node) {
            node.textContent = statusLabel(status);
        });

        renderResolutionPanel();
    }

    async function confirmResolution() {
        if (!ticketId) return;

        const confirmBtn = resolutionWrap ? resolutionWrap.querySelector("[data-support-confirm-resolution]") : null;
        if (confirmBtn) {
            confirmBtn.disabled = true;
        }

        try {
            const formData = new FormData();
            formData.append("ticket_id", String(ticketId));

            const res = await fetch("/messenger.php?type=3&action=support_confirm_resolution", {
                method: "POST",
                body: formData,
                credentials: "same-origin"
            });
            const data = await res.json();
            if (data.status === "ok") {
                if (data.ticket) {
                    setTicket(data.ticket);
                }
                if (Array.isArray(data.messages)) {
                    replaceMessages(data.messages);
                } else if (data.message) {
                    appendMessage(data.message);
                    scrollToBottom();
                } else {
                    await refreshThread();
                }
            } else if (data.msg) {
                alert(data.msg);
            }
        } catch (err) {
            console.error(err);
            alert("Не вдалося підтвердити вирішення.");
        } finally {
            const freshBtn = resolutionWrap ? resolutionWrap.querySelector("[data-support-confirm-resolution]") : null;
            if (freshBtn) {
                freshBtn.disabled = false;
            }
        }
    }

    async function refreshThread() {
        const action = ticketId > 0 ? "support_get_messages&ticket_id=" + encodeURIComponent(ticketId) + "&last_id=" + encodeURIComponent(lastMessageId) : "support_get_ticket";

        try {
            const res = await fetch("/messenger.php?type=3&action=" + action, {
                credentials: "same-origin",
                cache: "no-store"
            });
            const data = await res.json();
            if (data.status !== "ok") return;

            if (data.ticket) {
                const ticketChanged = parseInt(data.ticket.id || 0, 10) !== ticketId;
                setTicket(data.ticket);

                if (ticketChanged) {
                    replaceMessages(Array.isArray(data.messages) ? data.messages : []);
                    return;
                }
            }

            if (Array.isArray(data.messages) && data.messages.length > 0) {
                if (lastMessageId === 0 || ticketId === 0) {
                    replaceMessages(data.messages);
                } else {
                    const stickToBottom = messagesEl ? (messagesEl.scrollHeight - messagesEl.scrollTop - messagesEl.clientHeight) < 80 : true;
                    data.messages.forEach(appendMessage);
                    if (stickToBottom) {
                        scrollToBottom();
                    }
                }
            }
        } catch (err) {
            console.error(err);
        }
    }

    function startPolling() {
        if (pollTimer) {
            window.clearInterval(pollTimer);
        }
        pollTimer = window.setInterval(refreshThread, 4000);
    }

    if (captchaModal && captchaSubmit) {
        captchaSubmit.addEventListener("click", function () {
            const userAnswer = parseInt(captchaAnswer ? captchaAnswer.value : "", 10);
            if (userAnswer === captchaCorrectAnswer) {
                captchaPassed = true;
                root.dataset.captchaPassed = "1";
                setCookie("captcha_passed", "true", 30);
                closeCaptchaModal();
            } else {
                showCaptchaError("Невірна відповідь. Спробуйте ще раз.");
                generateCaptcha();
            }
        });
    }

    if (captchaAnswer) {
        captchaAnswer.addEventListener("keydown", function (event) {
            if (event.key === "Enter") {
                event.preventDefault();
                if (captchaSubmit) {
                    captchaSubmit.click();
                }
            }
        });
    }

    if (textarea) {
        textarea.addEventListener("input", function () {
            autoResize();
            updateSendState();
        });
        textarea.addEventListener("keydown", function (event) {
            if (event.key === "Enter" && !event.shiftKey) {
                event.preventDefault();
                if (form && !sendBtn.disabled) {
                    if (typeof form.requestSubmit === "function") {
                        form.requestSubmit();
                    } else {
                        form.dispatchEvent(new Event("submit", { cancelable: true, bubbles: true }));
                    }
                }
            }
        });
        autoResize();
    }

    if (fileInput) {
        fileInput.addEventListener("change", function () {
            renderPreview();
            updateSendState();
        });
    }

    if (form) {
        form.addEventListener("submit", async function (event) {
            event.preventDefault();
            if (!textarea) return;
            if (!ensureCaptchaPassed()) return;

            const text = (textarea.value || "").trim();
            const hasImage = !!(fileInput && fileInput.files && fileInput.files.length > 0);
            if (!text && !hasImage) return;

            sendBtn.disabled = true;

            try {
                const formData = new FormData(form);
                const res = await fetch("/messenger.php?type=3&action=support_send_message", {
                    method: "POST",
                    body: formData,
                    credentials: "same-origin"
                });
                const data = await res.json();

                if (data.status === "captcha_required") {
                    captchaPassed = false;
                    root.dataset.captchaPassed = "0";
                    openCaptchaModal();
                    return;
                }

                if (data.status === "ok") {
                    if (data.ticket) {
                        setTicket(data.ticket);
                    }
                    if (Array.isArray(data.messages)) {
                        replaceMessages(data.messages);
                    } else if (data.message) {
                        appendMessage(data.message);
                        scrollToBottom();
                    } else {
                        await refreshThread();
                    }

                    textarea.value = "";
                    autoResize();
                    if (fileInput) {
                        fileInput.value = "";
                    }
                    resetPreview();
                } else if (data.msg) {
                    alert(data.msg);
                }
            } catch (err) {
                console.error(err);
                alert("Не вдалося надіслати повідомлення.");
            } finally {
                updateSendState();
            }
        });
    }

    updateSendState();
    updateLastMessageId(Array.from(messagesEl ? messagesEl.querySelectorAll("[data-message-id]") : []).map(function (node) {
        return { id: parseInt(node.getAttribute("data-message-id") || "0", 10) || 0 };
    }));
    syncRenderedDateKey();
    startPolling();
    if (!isAuthenticated && !captchaPassed && ticketId === 0) {
        openCaptchaModal();
    }
    refreshThread();
    scrollToBottom();
});
