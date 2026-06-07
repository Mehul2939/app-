import { useEffect, useState } from 'react';
import { getUserStatus } from '../api/api';
import UserAvatar from './UserAvatar';
import AudioCallButton from './calls/AudioCallButton';

function statusText(status) {
  if (!status?.last_seen) return status?.is_online ? 'Online' : 'Offline';
  if (status.is_online) return 'Online';
  const diff = Math.floor((Date.now() - new Date(status.last_seen.replace(' ', 'T')).getTime()) / 60000);
  if (diff < 60) return `Last seen: ${Math.max(1, diff)} min ago`;
  return `Last seen: ${new Date(status.last_seen.replace(' ', 'T')).toLocaleString()}`;
}

export default function ChatHeader({ peer, onBack, selectedCount = 0, onDeleteMe, onDeleteEveryone, onCancelSelect }) {
  const [status, setStatus] = useState(null);
  useEffect(() => {
    if (!peer?.id) return undefined;
    const load = () => getUserStatus(peer.id).then(({ data }) => setStatus(data)).catch(() => {});
    load();
    const timer = setInterval(load, 5000);
    return () => clearInterval(timer);
  }, [peer?.id]);

  return (
    <header className="wa-chat-header">
      <button className="chat-back-btn" onClick={onBack} aria-label="Back to messages"><i className="bi bi-arrow-left" /></button>
      {selectedCount > 0 ? (
        <div className="chat-select-toolbar">
          <strong>{selectedCount} selected</strong>
          <button onClick={onDeleteMe}>Delete for me</button>
          <button onClick={onDeleteEveryone}>Delete for everyone</button>
          <button onClick={onCancelSelect}>Cancel</button>
        </div>
      ) : (
        <>
          <UserAvatar user={peer || { name: 'User' }} />
          <div className="chat-peer-meta"><strong>{peer?.username || peer?.name || 'Chat'}</strong><span>{statusText(status || peer)}</span></div>
          <AudioCallButton peer={peer} />
        </>
      )}
    </header>
  );
}
