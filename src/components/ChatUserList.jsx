import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { getChatUsers, heartbeat } from '../api/api';
import { timeAgo } from '../utils/time';
import UserAvatar from './UserAvatar';

function preview(chat) {
  if (chat.message_text) return chat.message_text;
  if (chat.media_type === 'image') return 'Photo';
  if (chat.media_type === 'audio') return 'Audio';
  if (chat.media_type === 'video') return 'Video';
  return 'No messages yet';
}

function statusLabel(chat) {
  if (Number(chat.is_online) === 1) return 'Online';
  const last = chat.last_seen || chat.last_active;
  if (!last) return 'Offline';
  const diff = Math.floor((Date.now() - new Date(last.replace(' ', 'T')).getTime()) / 60000);
  return diff < 60 ? `Last seen: ${Math.max(1, diff)} min ago` : `Last seen: ${new Date(last.replace(' ', 'T')).toLocaleDateString()}`;
}

export default function ChatUserList() {
  const [chats, setChats] = useState([]);
  useEffect(() => {
    const load = () => getChatUsers().then(({ data }) => setChats(data.chats || [])).catch(() => {});
    load();
    const chatTimer = setInterval(load, 5000);
    const heartbeatTimer = setInterval(() => heartbeat().catch(() => {}), 30000);
    heartbeat().catch(() => {});
    return () => { clearInterval(chatTimer); clearInterval(heartbeatTimer); };
  }, []);

  return <div className="chat-list">
    {chats.length === 0 && <p className="text-secondary px-3">No chats yet. Add a friend to start messaging.</p>}
    {chats.map((chat) => (
      <Link className="chat-list-item" to={`/chat/${chat.id}`} key={chat.id}>
        <div className="presence-avatar">
          <UserAvatar user={chat} />
          <span className={`online-dot ${Number(chat.is_online) === 1 ? 'online' : ''}`} />
        </div>
        <div className="chat-list-main">
          <div className="chat-list-title"><strong>{chat.username || chat.name}</strong><small>{chat.last_message_at ? timeAgo(chat.last_message_at) : ''}</small></div>
          <p>{preview(chat)}</p>
          <small className="last-seen-line">{statusLabel(chat)}</small>
        </div>
        {Number(chat.unread_count) > 0 && <span className="badge-dot">{chat.unread_count}</span>}
      </Link>
    ))}
  </div>;
}
