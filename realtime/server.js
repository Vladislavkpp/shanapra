const path = require("path");
const express = require("express");
const http = require("http");
const { Server } = require("socket.io");
const mysql = require("mysql2/promise");
const cookie = require("cookie");
require("dotenv").config({ path: path.resolve(__dirname, "..", ".env") });

const app = express();
app.use(express.json({ limit: "1mb" }));

const server = http.createServer(app);
const io = new Server(server, {
  cors: {
    origin: true,
    credentials: true
  }
});

const port = Number(process.env.SOCKET_IO_PORT || 3201);
const internalSecret = String(process.env.SOCKET_INTERNAL_SECRET || "");

const pool = mysql.createPool({
  host: process.env.DB_HOST || "localhost",
  user: process.env.DB_USER || "",
  password: process.env.DB_PASS || "",
  database: process.env.DB_NAME || "",
  waitForConnections: true,
  connectionLimit: 10,
  charset: "utf8mb4"
});

async function getIdentityFromCookies(rawCookieHeader) {
  const parsed = cookie.parse(rawCookieHeader || "");
  const userAuth = Number(parsed.user_auth || 0);
  const phpSessionId = String(parsed.PHPSESSID || "");
  const guestId = Number(parsed.guest_id || 0);

  if (userAuth > 0 && phpSessionId) {
    const [rows] = await pool.query(
      `SELECT u.idx, u.status
       FROM user_sessions us
       INNER JOIN users u ON u.idx = us.user_id
       WHERE us.user_id = ? AND us.session_id = ?
       LIMIT 1`,
      [userAuth, phpSessionId]
    );

    if (rows && rows[0]) {
      const status = Number(rows[0].status || 0);
      const isWebmaster = (status & 32) === 32;
      return { type: isWebmaster ? "staff" : "user", userId: Number(rows[0].idx), isWebmaster };
    }
  }

  if (guestId !== 0) {
    return { type: "guest", guestId };
  }

  return { type: "anonymous" };
}

async function canAccessTicket(ticketId, identity) {
  const [rows] = await pool.query(
    `SELECT id, requester_user_id, requester_guest_id, assignee_user_id
     FROM support_tickets
     WHERE id = ?
     LIMIT 1`,
    [ticketId]
  );

  const ticket = rows && rows[0] ? rows[0] : null;
  if (!ticket) return false;

  if (identity.isWebmaster) return true;
  if (identity.type === "user" && Number(ticket.requester_user_id || 0) === Number(identity.userId || 0)) return true;
  if (identity.type === "guest" && Number(ticket.requester_guest_id || 0) === Number(identity.guestId || 0)) return true;

  return false;
}

io.use(async (socket, next) => {
  try {
    socket.data.identity = await getIdentityFromCookies(socket.handshake.headers.cookie || "");
    next();
  } catch (error) {
    next(error);
  }
});

io.on("connection", (socket) => {
  socket.on("support:subscribe", async (payload = {}) => {
    try {
      const room = String(payload.room || "");
      const identity = socket.data.identity || { type: "anonymous" };

      if (room === "support:queue") {
        if (!identity.isWebmaster) return;
        socket.join(room);
        if (identity.userId) socket.join(`support:webmaster:${identity.userId}`);
        return;
      }

      if (room.startsWith("support:ticket:")) {
        const ticketId = Number(payload.ticketId || room.split(":").pop() || 0);
        if (!ticketId) return;
        const allowed = await canAccessTicket(ticketId, identity);
        if (!allowed) return;
        socket.join(`support:ticket:${ticketId}`);
      }
    } catch (error) {
      socket.emit("support:error", { message: "subscription_failed" });
    }
  });
});

function ensureInternalAuth(req, res, next) {
  if (!internalSecret || req.header("X-Support-Internal-Secret") !== internalSecret) {
    res.status(403).json({ ok: false });
    return;
  }
  next();
}

app.post("/internal/support/message", ensureInternalAuth, (req, res) => {
  const payload = req.body || {};
  const ticket = payload.ticket || {};
  const message = payload.message || {};
  const ticketId = Number(ticket.id || message.ticket_id || 0);

  if (ticketId > 0) {
    io.to(`support:ticket:${ticketId}`).emit("support:message:new", { ticket, message });
    io.to("support:queue").emit("support:ticket:update", { ticket });
    if (ticket.assignee_user_id) {
      io.to(`support:webmaster:${ticket.assignee_user_id}`).emit("support:ticket:update", { ticket });
    }
  }

  res.json({ ok: true });
});

app.post("/internal/support/ticket-update", ensureInternalAuth, (req, res) => {
  const payload = req.body || {};
  const ticket = payload.ticket || {};
  const event = String(payload.event || "support:ticket:update");
  const ticketId = Number(ticket.id || 0);

  if (ticketId > 0) {
    io.to("support:queue").emit(event, { ticket });
    io.to(`support:ticket:${ticketId}`).emit(event, { ticket });
    if (ticket.assignee_user_id) {
      io.to(`support:webmaster:${ticket.assignee_user_id}`).emit(event, { ticket });
    }
  }

  res.json({ ok: true });
});

app.get("/health", (_req, res) => {
  res.json({ ok: true });
});

server.listen(port, () => {
  console.log(`Support realtime server listening on ${port}`);
});
