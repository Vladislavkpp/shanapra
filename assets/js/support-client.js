document.addEventListener("DOMContentLoaded", function () {
    const root = document.getElementById("supportClientRoot");
    if (!root) return;

    const messagesEl = document.getElementById("supportClientMessages");
    const form = document.getElementById("supportClientForm");
    const textarea = document.getElementById("supportClientMessage");
    const fileInput = document.getElementById("supportClientImg");
    const preview = document.getElementById("supportClientPreview");
    const sendBtn = document.getElementById("supportClientSend");
    const ticketInput = form ? form.querySelector("input[name='ticket_id']") : null;
    const cfg = JSON.parse(root.dataset.socketConfig || "{}");
    let socket = null;
    let ticketId = parseInt(root.dataset.ticketId || "0", 10) || 0;
    let fallbackTimer = null;

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (char) {
            return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[char];
        });
    }

    function scrollToBottom() {
        if (messagesEl) messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function renderPreview() {
        if (!preview || !fileInput) return;
        preview.innerHTML = "";
        preview.classList.remove("is-visible");
        preview.setAttribute("aria-hidden", "true");
        if (!fileInput.files || !fileInput.files[0]) return;

        const reader = new FileReader();
        reader.onload = function () {
            preview.classList.add("is-visible");
            preview.removeAttribute("aria-hidden");
            preview.innerHTML = '<div class="support-preview-card"><img src="' + escapeHtml(reader.result || "") + '" alt=""><button type="button" class="support-preview-remove">&times;</button></div>';
            const removeBtn = preview.querySelector(".support-preview-remove");
            if (removeBtn) {
                removeBtn.addEventListener("click", function () {
                    fileInput.value = "";
                    renderPreview();
                });
            }
        };
        reader.readAsDataURL(fileInput.files[0]);
    }

    function appendMessage(message) {
        if (!messagesEl || !message || !message.id) return;
        if (messagesEl.querySelector('[data-message-id="' + String(message.id) + '"]')) return;

        const article = document.createElement("article");
        article.className = "support-message support-message--" + escapeHtml(message.sender_type || "system");
        article.dataset.messageId = String(message.id);

        let html = '<div class="support-message-meta"><strong>' + escapeHtml(message.display_name || "Система") + '</strong><span>' + escapeHtml(message.created_at || "") + '</span></div>';
        if (message.image_path) {
            html += '<div class="support-message-image"><a href="' + escapeHtml(message.image_path) + '" target="_blank" rel="noopener"><img src="' + escapeHtml(message.image_path) + '" alt=""></a></div>';
        }
        if (message.body) {
            html += '<div class="support-message-body">' + escapeHtml(message.body).replace(/\n/g, "<br>") + "</div>";
        }
        article.innerHTML = html;

        const empty = messagesEl.querySelector(".support-empty-thread");
        if (empty) empty.remove();
        messagesEl.appendChild(article);
        scrollToBottom();
    }

    function setTicket(nextTicket) {
        if (!nextTicket) return;
        ticketId = parseInt(nextTicket.id || 0, 10) || 0;
        root.dataset.ticketId = String(ticketId);
        if (ticketInput) ticketInput.value = String(ticketId);
        const code = root.querySelector(".support-ticket-code");
        if (code) code.textContent = ticketId > 0 ? ("#" + ticketId) : "Нове звернення";
        const badge = root.querySelector(".support-ticket-badge");
        if (badge) {
            badge.className = "support-ticket-badge support-ticket-badge--" + (nextTicket.status || "new");
            const labels = {
                new: "Нове",
                open: "В роботі",
                waiting_customer: "Очікує клієнта",
                resolved: "Вирішено",
                closed: "Закрито",
                spam: "Спам"
            };
            badge.textContent = labels[nextTicket.status] || "Нове";
        }
        if (socket && ticketId > 0) {
            socket.emit("support:subscribe", { room: "support:ticket:" + ticketId, ticketId: ticketId });
        }
    }

    async function refreshMessages() {
        if (!ticketId) return;
        try {
            const res = await fetch("/messenger.php?type=3&action=support_get_messages&ticket_id=" + encodeURIComponent(ticketId), { credentials: "same-origin" });
            const data = await res.json();
            if (data.status !== "ok" || !Array.isArray(data.messages)) return;
            if (data.ticket) setTicket(data.ticket);
            data.messages.forEach(appendMessage);
        } catch (err) {
            console.error(err);
        }
    }

    function initSocket() {
        if (!cfg || !cfg.enabled || typeof window.io !== "function") {
            fallbackTimer = window.setInterval(refreshMessages, 8000);
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
            if (ticketId > 0) {
                socket.emit("support:subscribe", { room: "support:ticket:" + ticketId, ticketId: ticketId });
            }
            refreshMessages();
        });

        socket.on("support:message:new", function (payload) {
            if (!payload || !payload.ticket || parseInt(payload.ticket.id || 0, 10) !== ticketId) return;
            setTicket(payload.ticket);
            if (payload.message) appendMessage(payload.message);
        });

        socket.on("support:ticket:update", function (payload) {
            if (!payload || !payload.ticket) return;
            const nextId = parseInt(payload.ticket.id || 0, 10) || 0;
            if (!ticketId || nextId === ticketId) setTicket(payload.ticket);
        });

        socket.on("disconnect", function () {
            if (!fallbackTimer) fallbackTimer = window.setInterval(refreshMessages, 8000);
        });
    }

    if (fileInput) fileInput.addEventListener("change", renderPreview);

    if (form) {
        form.addEventListener("submit", async function (event) {
            event.preventDefault();
            if (!textarea) return;
            const text = (textarea.value || "").trim();
            const hasImage = fileInput && fileInput.files && fileInput.files.length > 0;
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
                if (data.status === "ok") {
                    if (data.ticket) setTicket(data.ticket);
                    if (Array.isArray(data.messages)) {
                        messagesEl.innerHTML = "";
                        data.messages.forEach(appendMessage);
                    } else if (data.message) {
                        appendMessage(data.message);
                    }
                    textarea.value = "";
                    if (fileInput) fileInput.value = "";
                    renderPreview();
                    scrollToBottom();
                } else if (data.msg) {
                    alert(data.msg);
                }
            } catch (err) {
                console.error(err);
                alert("Не вдалося надіслати повідомлення.");
            } finally {
                sendBtn.disabled = false;
            }
        });
    }

    initSocket();
    scrollToBottom();
});
