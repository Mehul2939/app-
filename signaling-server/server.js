import { createServer } from 'node:http';
import { randomUUID } from 'node:crypto';
import mysql from 'mysql2/promise';
import { Server } from 'socket.io';

const PORT = Number(process.env.CALL_SIGNALING_PORT || 3001);
const CLIENT_ORIGIN = process.env.CALL_CLIENT_ORIGIN || 'http://localhost';
const pool = mysql.createPool({
  host: process.env.DB_HOST || 'localhost',
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASS || '',
  database: process.env.DB_NAME || 'mysocialmedia',
  waitForConnections: true,
  connectionLimit: 10,
  charset: 'utf8mb4',
});

const httpServer = createServer((request, response) => {
  response.writeHead(200, { 'Content-Type': 'application/json' });
  response.end(JSON.stringify({ service: 'myself-audio-call-signaling', status: 'ok' }));
});
const io = new Server(httpServer, {
  cors: { origin: CLIENT_ORIGIN === '*' ? true : CLIENT_ORIGIN.split(','), credentials: true },
  transports: ['websocket', 'polling'],
});

const calls = new Map();
const activeCallByUser = new Map();
const room = (userId) => `user:${userId}`;
const liveRoom = (roomId) => `live-room:${roomId}`;

async function authenticatedUser(token) {
  if (!token) return null;
  const [rows] = await pool.execute(
    `SELECT u.id, u.name, u.username, COALESCE(u.profile_photo, p.profile_photo) profile_photo
     FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id
     WHERE u.login_token = ? AND u.status = 'active' LIMIT 1`,
    [String(token)]
  );
  return rows[0] || null;
}

async function canCall(callerId, receiverId) {
  const [rows] = await pool.execute(
    `SELECT
      EXISTS(SELECT 1 FROM friends WHERE user_id = ? AND friend_id = ?) mutual_a,
      EXISTS(SELECT 1 FROM friends WHERE user_id = ? AND friend_id = ?) mutual_b,
      EXISTS(SELECT 1 FROM blocked_users WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)) blocked,
      EXISTS(SELECT 1 FROM users WHERE id = ? AND status = 'active') receiver_exists`,
    [callerId, receiverId, receiverId, callerId, callerId, receiverId, receiverId, callerId, receiverId]
  );
  const result = rows[0];
  return Boolean(result?.mutual_a && result?.mutual_b && !result?.blocked && result?.receiver_exists);
}

async function createCallLog(callerId, receiverId) {
  const [result] = await pool.execute(
    "INSERT INTO call_logs (caller_id, receiver_id, call_type, status) VALUES (?, ?, 'audio', 'started')",
    [callerId, receiverId]
  );
  await addChatLog(callerId, receiverId, 'Voice call started');
  return Number(result.insertId);
}

async function addChatLog(senderId, receiverId, message) {
  await pool.execute(
    "INSERT INTO messages (sender_id, receiver_id, message_text, media_type, delivered_at) VALUES (?, ?, ?, 'text', NOW())",
    [senderId, receiverId, message]
  );
}

async function finishCall(call, status, durationSeconds = 0) {
  if (!call || call.finished) return;
  call.finished = true;
  clearTimeout(call.timeout);
  activeCallByUser.delete(call.callerId);
  activeCallByUser.delete(call.receiverId);
  calls.delete(call.id);
  await pool.execute('UPDATE call_logs SET status = ?, duration_seconds = ? WHERE id = ?', [status, durationSeconds, call.logId]);
  const message = status === 'missed'
    ? 'Missed voice call'
    : status === 'rejected'
      ? 'Call rejected'
      : `Voice call duration: ${formatDuration(durationSeconds)}`;
  await addChatLog(call.callerId, call.receiverId, message);
}

function formatDuration(totalSeconds) {
  const minutes = Math.floor(totalSeconds / 60);
  const seconds = totalSeconds % 60;
  return `${minutes}:${String(seconds).padStart(2, '0')}`;
}

function getParticipantCall(socket, callId) {
  const call = calls.get(String(callId || ''));
  if (!call || ![call.callerId, call.receiverId].includes(socket.user.id)) return null;
  return call;
}

function otherUserId(call, userId) {
  return call.callerId === userId ? call.receiverId : call.callerId;
}

async function roomMember(roomId, userId) {
  const [rows] = await pool.execute(
    `SELECT rp.role, rp.mic_muted, rp.raised_hand, r.owner_id, r.status
     FROM room_participants rp JOIN rooms r ON r.id=rp.room_id
     WHERE rp.room_id=? AND rp.user_id=? AND rp.left_at IS NULL LIMIT 1`,
    [roomId, userId]
  );
  return rows[0] || null;
}

async function roomModerator(roomId, userId) {
  const member = await roomMember(roomId, userId);
  return member && ['owner', 'coadmin'].includes(member.role) ? member : null;
}

async function roomSystemMessage(roomId, message) {
  const [result] = await pool.execute("INSERT INTO room_messages(room_id,message_type,message_text) VALUES(?,'system',?)", [roomId, message]);
  return { id: Number(result.insertId), room_id: roomId, message_type: 'system', message_text: message, created_at: new Date().toISOString() };
}

io.use(async (socket, next) => {
  try {
    const user = await authenticatedUser(socket.handshake.auth?.token);
    if (!user) return next(new Error('Authentication required'));
    socket.user = { ...user, id: Number(user.id) };
    next();
  } catch (error) {
    next(new Error('Authentication failed'));
  }
});

io.on('connection', (socket) => {
  socket.join(room(socket.user.id));
  socket.emit('call:ready', { userId: socket.user.id });

  socket.on('room_join', async ({ roomId } = {}, reply = () => {}) => {
    roomId = Number(roomId);
    const member = await roomMember(roomId, socket.user.id);
    if (!member || member.status !== 'active') return reply({ ok: false, message: 'Join the room first' });
    socket.join(liveRoom(roomId));
    socket.data.roomIds ||= new Set();
    socket.data.roomIds.add(roomId);
    const peers = (await io.in(liveRoom(roomId)).fetchSockets())
      .filter((peer) => peer.id !== socket.id)
      .map((peer) => ({ socketId: peer.id, user: peer.user }));
    socket.to(liveRoom(roomId)).emit('room:user-joined', { roomId, socketId: socket.id, user: socket.user, role: member.role });
    reply({ ok: true, peers, role: member.role });
  });

  socket.on('room_leave', ({ roomId } = {}) => {
    roomId = Number(roomId);
    socket.leave(liveRoom(roomId));
    socket.data.roomIds?.delete(roomId);
    socket.to(liveRoom(roomId)).emit('room:user-left', { roomId, socketId: socket.id, userId: socket.user.id });
  });

  socket.on('room:message', async ({ roomId, messageText, messageType = 'text' } = {}, reply = () => {}) => {
    roomId = Number(roomId);
    const member = await roomMember(roomId, socket.user.id);
    const text = String(messageText || '').trim().slice(0, 1000);
    if (!member || !text || !['text', 'emoji', 'gif'].includes(messageType)) return reply({ ok: false, message: 'Invalid room message' });
    const [result] = await pool.execute('INSERT INTO room_messages(room_id,user_id,message_type,message_text) VALUES(?,?,?,?)', [roomId, socket.user.id, messageType, text]);
    const message = { id: Number(result.insertId), room_id: roomId, user_id: socket.user.id, message_type: messageType, message_text: text, name: socket.user.name, username: socket.user.username, profile_photo: socket.user.profile_photo, created_at: new Date().toISOString() };
    io.to(liveRoom(roomId)).emit('room:message', message);
    reply({ ok: true, message });
  });

  socket.on('room:reaction', async ({ roomId, emoji } = {}) => {
    roomId = Number(roomId);
    if (await roomMember(roomId, socket.user.id)) io.to(liveRoom(roomId)).emit('room:reaction', { roomId, emoji: String(emoji || '').slice(0, 8), user: socket.user });
  });

  socket.on('room:user-state', async ({ roomId, action, userId } = {}) => {
    roomId = Number(roomId);
    if (await roomMember(roomId, socket.user.id)) {
      io.to(liveRoom(roomId)).emit('room:user-state', { roomId, action: String(action || ''), userId: Number(userId) || socket.user.id, actor: socket.user });
    }
  });

  socket.on('seat_request', async ({ roomId, seatNumber } = {}, reply = () => {}) => {
    roomId = Number(roomId);
    if (!(await roomMember(roomId, socket.user.id))) return reply({ ok: false });
    await pool.execute("INSERT INTO room_seat_requests(room_id,user_id,seat_number,status) VALUES(?,?,?,'pending') ON DUPLICATE KEY UPDATE seat_number=VALUES(seat_number),status='pending'", [roomId, socket.user.id, Number(seatNumber) || null]);
    io.to(liveRoom(roomId)).emit('seat_request', { roomId, seatNumber, user: socket.user });
    reply({ ok: true });
  });

  for (const event of ['seat_accept', 'seat_reject', 'room_lock', 'room_unlock', 'mic_mute', 'mic_unmute', 'announcement', 'room_close']) {
    socket.on(event, async (payload = {}, reply = () => {}) => {
      const roomId = Number(payload.roomId);
      if (!(await roomModerator(roomId, socket.user.id))) return reply({ ok: false, message: 'Room permission denied' });
      io.to(liveRoom(roomId)).emit(event, { ...payload, roomId, actor: socket.user });
      reply({ ok: true });
    });
  }

  socket.on('room:webrtc-offer', async ({ roomId, targetSocketId, offer } = {}) => {
    roomId = Number(roomId);
    if (await roomMember(roomId, socket.user.id)) io.to(String(targetSocketId)).emit('room:webrtc-offer', { roomId, fromSocketId: socket.id, user: socket.user, offer });
  });
  socket.on('room:webrtc-answer', async ({ roomId, targetSocketId, answer } = {}) => {
    roomId = Number(roomId);
    if (await roomMember(roomId, socket.user.id)) io.to(String(targetSocketId)).emit('room:webrtc-answer', { roomId, fromSocketId: socket.id, answer });
  });
  socket.on('room:webrtc-ice', async ({ roomId, targetSocketId, candidate } = {}) => {
    roomId = Number(roomId);
    if (await roomMember(roomId, socket.user.id)) io.to(String(targetSocketId)).emit('room:webrtc-ice', { roomId, fromSocketId: socket.id, candidate });
  });

  socket.on('video_sync', async ({ roomId, action, queueId, position = 0 } = {}, reply = () => {}) => {
    roomId = Number(roomId);
    if (!(await roomModerator(roomId, socket.user.id))) return reply({ ok: false, message: 'Room permission denied' });
    if (!['play', 'pause', 'seek', 'next', 'previous'].includes(action)) return reply({ ok: false });
    io.to(liveRoom(roomId)).emit(`video_${action}`, { roomId, queueId: Number(queueId) || null, position: Math.max(0, Number(position) || 0), serverTime: Date.now(), actor: socket.user });
    reply({ ok: true });
  });

  socket.on('call:request', async ({ receiverId } = {}, reply = () => {}) => {
    try {
      receiverId = Number(receiverId);
      if (!receiverId || receiverId === socket.user.id) return reply({ ok: false, message: 'Invalid call recipient' });
      if (!(await canCall(socket.user.id, receiverId))) return reply({ ok: false, message: 'Only mutual friends can call each other.' });
      if (activeCallByUser.has(socket.user.id) || activeCallByUser.has(receiverId)) return reply({ ok: false, message: 'User is busy' });
      const receiverSockets = await io.in(room(receiverId)).fetchSockets();
      if (receiverSockets.length === 0) return reply({ ok: false, message: 'User is offline' });

      const call = {
        id: randomUUID(),
        callerId: socket.user.id,
        receiverId,
        logId: await createCallLog(socket.user.id, receiverId),
        answeredAt: null,
        finished: false,
      };
      calls.set(call.id, call);
      activeCallByUser.set(call.callerId, call.id);
      activeCallByUser.set(call.receiverId, call.id);
      call.timeout = setTimeout(async () => {
        io.to(room(call.callerId)).to(room(call.receiverId)).emit('call:missed', { callId: call.id, message: 'Missed voice call' });
        await finishCall(call, 'missed');
      }, 30000);
      reply({ ok: true, callId: call.id });
      io.to(room(receiverId)).emit('call:request', { callId: call.id, caller: socket.user });
      io.to(room(socket.user.id)).emit('call:ringing', { callId: call.id, receiverId });
    } catch (error) {
      console.error('call:request', error);
      reply({ ok: false, message: 'Unable to start call' });
    }
  });

  socket.on('call:offer', ({ callId, offer } = {}) => {
    const call = getParticipantCall(socket, callId);
    if (call) io.to(room(otherUserId(call, socket.user.id))).emit('call:offer', { callId: call.id, offer });
  });

  socket.on('call:accept', async ({ callId } = {}) => {
    const call = getParticipantCall(socket, callId);
    if (!call || socket.user.id !== call.receiverId || call.answeredAt) return;
    call.answeredAt = Date.now();
    clearTimeout(call.timeout);
    await pool.execute("UPDATE call_logs SET status = 'answered' WHERE id = ?", [call.logId]);
    io.to(room(call.callerId)).emit('call:accept', { callId: call.id });
  });

  socket.on('call:answer', ({ callId, answer } = {}) => {
    const call = getParticipantCall(socket, callId);
    if (call) io.to(room(otherUserId(call, socket.user.id))).emit('call:answer', { callId: call.id, answer });
  });

  socket.on('call:media-state', ({ callId, videoOn, facingMode } = {}) => {
    const call = getParticipantCall(socket, callId);
    if (call) io.to(room(otherUserId(call, socket.user.id))).emit('call:media-state', {
      callId: call.id,
      videoOn: Boolean(videoOn),
      facingMode: facingMode === 'environment' ? 'environment' : 'user',
    });
  });

  socket.on('call:ice-candidate', ({ callId, candidate } = {}) => {
    const call = getParticipantCall(socket, callId);
    if (call && candidate) io.to(room(otherUserId(call, socket.user.id))).emit('call:ice-candidate', { callId: call.id, candidate });
  });

  socket.on('call:reject', async ({ callId } = {}) => {
    const call = getParticipantCall(socket, callId);
    if (!call || socket.user.id !== call.receiverId || call.answeredAt) return;
    io.to(room(call.callerId)).emit('call:reject', { callId: call.id, message: 'Call rejected' });
    await finishCall(call, 'rejected');
  });

  socket.on('call:end', async ({ callId } = {}) => {
    const call = getParticipantCall(socket, callId);
    if (!call) return;
    const duration = call.answeredAt ? Math.max(0, Math.floor((Date.now() - call.answeredAt) / 1000)) : 0;
    io.to(room(otherUserId(call, socket.user.id))).emit('call:end', { callId: call.id, durationSeconds: duration });
    await finishCall(call, call.answeredAt ? 'ended' : 'missed', duration);
  });

  socket.on('disconnect', async () => {
    for (const roomId of socket.data.roomIds || []) {
      socket.to(liveRoom(roomId)).emit('room:user-left', { roomId, socketId: socket.id, userId: socket.user.id });
    }
    const callId = activeCallByUser.get(socket.user.id);
    const remainingSockets = await io.in(room(socket.user.id)).fetchSockets();
    if (!callId || remainingSockets.length > 0) return;
    const call = calls.get(callId);
    if (!call) return;
    const duration = call.answeredAt ? Math.max(0, Math.floor((Date.now() - call.answeredAt) / 1000)) : 0;
    io.to(room(otherUserId(call, socket.user.id))).emit('call:end', { callId: call.id, durationSeconds: duration, message: 'Call disconnected' });
    await finishCall(call, call.answeredAt ? 'ended' : 'missed', duration);
  });
});

httpServer.listen(PORT, () => console.log(`Audio call signaling server listening on port ${PORT}`));
