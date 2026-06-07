import { useEffect, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import api from '../api/client';
import { heartbeat } from '../api/api';
import ChatHeader from '../components/ChatHeader';
import UserAvatar from '../components/UserAvatar';
import { mediaUrl } from '../utils/media';

const EMOJIS = ['😀', '😁', '😂', '🤣', '😊', '😍', '😘', '😎', '😢', '😡', '👍', '🙏', '🔥', '❤️', '💔', '🎉', '✨', '💯', '☕', '🌹'];

function statusTicks(message, mine) {
  if (!mine) return null;
  if (message.seen_at) return <span className="ticks seen">✓✓</span>;
  if (message.delivered_at) return <span className="ticks">✓✓</span>;
  return <span className="ticks">✓</span>;
}

export default function Chat() {
  const { userId } = useParams();
  const navigate = useNavigate();
  const [messages, setMessages] = useState([]);
  const [currentUserId, setCurrentUserId] = useState(0);
  const [peer, setPeer] = useState(null);
  const [text, setText] = useState('');
  const [media, setMedia] = useState(null);
  const [replyTo, setReplyTo] = useState(null);
  const [selectedIds, setSelectedIds] = useState([]);
  const [error, setError] = useState('');
  const [pickerOpen, setPickerOpen] = useState(false);
  const [pickerTab, setPickerTab] = useState('emoji');
  const [gifs, setGifs] = useState([]);
  const bottomRef = useRef(null);

  const load = async () => {
    const { data } = await api.get(`/chat/messages.php?user_id=${userId}`);
    setMessages(data.messages || []);
    setCurrentUserId(Number(data.current_user_id || 0));
    setPeer(data.peer || null);
  };

  const loadGifs = async () => {
    const { data } = await api.get('/chat/gifs.php');
    setGifs(data.gifs || []);
  };

  useEffect(() => {
    load();
    heartbeat().catch(() => {});
    loadGifs();
    const timer = setInterval(load, 2500);
    const heartbeatTimer = setInterval(() => heartbeat().catch(() => {}), 30000);
    return () => { clearInterval(timer); clearInterval(heartbeatTimer); };
  }, [userId]);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
  }, [messages.length]);

  const toggleSelect = (id) => {
    setSelectedIds((ids) => ids.includes(id) ? ids.filter((item) => item !== id) : [...ids, id]);
  };

  const deleteSelected = async (mode = 'me') => {
    if (selectedIds.length === 0) return;
    await api.post('/chat/delete.php', { message_ids: selectedIds, mode });
    setSelectedIds([]);
    load();
  };

  const send = async (event, gifPath = '') => {
    event?.preventDefault?.();
    if (!text.trim() && !media && !gifPath) return;
    const fd = new FormData();
    fd.append('receiver_id', userId);
    fd.append('message_text', gifPath ? '' : text);
    if (replyTo?.id) fd.append('reply_to_message_id', replyTo.id);
    if (gifPath) fd.append('gif_path', gifPath);
    if (media) fd.append('media', media);
    try {
      await api.post('/chat/send.php', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
      setText('');
      setMedia(null);
      setReplyTo(null);
      setPickerOpen(false);
      setError('');
      load();
    } catch (err) {
      setError(err.response?.data?.message || 'Message failed');
    }
  };

  const uploadGif = async (file) => {
    if (!file) return;
    const fd = new FormData();
    fd.append('gif', file);
    try {
      const { data } = await api.post('/chat/gifs.php', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
      setError(data.message || '');
      loadGifs();
    } catch (err) {
      setError(err.response?.data?.message || 'GIF upload failed');
    }
  };

  const addEmoji = (emoji) => setText((value) => value + emoji);

  return (
    <main className="wa-chat-page">
      <ChatHeader
        peer={peer}
        selectedCount={selectedIds.length}
        onBack={() => navigate('/messages')}
        onDeleteMe={() => deleteSelected('me')}
        onDeleteEveryone={() => deleteSelected('everyone')}
        onCancelSelect={() => setSelectedIds([])}
      />

      <section className="wa-chat-box">
        {messages.map((message) => {
          const mine = Number(message.sender_id) === currentUserId;
          const selected = selectedIds.includes(Number(message.id));
          return (
            <div key={message.id} className={`wa-message-row ${mine ? 'mine' : 'theirs'} ${selected ? 'selected' : ''}`}>
              <button className="message-select" onClick={() => toggleSelect(Number(message.id))} aria-label="Select message">
                {selected ? '✓' : ''}
              </button>
              {!mine && <UserAvatar user={message} />}
              <div className="wa-bubble">
                {message.reply_to_message_id && <div className="reply-preview"><span>@{message.reply_username || 'user'}</span><p>{message.reply_text || message.reply_media_type || 'message'}</p></div>}
                {message.message_text && <p>{message.message_text}</p>}
                {message.media_path && (message.media_type === 'video'
                  ? <video controls src={mediaUrl(message.media_path)} />
                  : message.media_type === 'audio'
                    ? <audio controls src={mediaUrl(message.media_path)} />
                    : <img src={mediaUrl(message.media_path)} alt="Chat media" loading="lazy" />)}
                <small>{message.created_at} {statusTicks(message, mine)}</small>
                <div className="message-tools"><button type="button" onClick={() => setReplyTo(message)}>Reply</button><button type="button" onClick={() => toggleSelect(Number(message.id))}>Select</button></div>
              </div>
            </div>
          );
        })}
        <div ref={bottomRef} />
      </section>

      {error && <div className="alert alert-info wa-error">{error}</div>}
      {replyTo && <div className="reply-strip"><span>Replying to: {replyTo.message_text || replyTo.media_type}</span><button onClick={() => setReplyTo(null)}>Cancel</button></div>}

      {pickerOpen && (
        <section className="emoji-gif-panel">
          <div className="picker-tabs">
            <button className={pickerTab === 'emoji' ? 'active' : ''} onClick={() => setPickerTab('emoji')}>Emoji</button>
            <button className={pickerTab === 'gif' ? 'active' : ''} onClick={() => setPickerTab('gif')}>GIF</button>
          </div>
          {pickerTab === 'emoji' && <div className="emoji-grid">{EMOJIS.map((emoji) => <button key={emoji} onClick={() => addEmoji(emoji)}>{emoji}</button>)}</div>}
          {pickerTab === 'gif' && <div className="gif-panel">
            <label className="gif-upload-tile"><i className="bi bi-plus-lg" /><span>Add GIF</span><input type="file" accept="image/gif" onChange={(event) => uploadGif(event.target.files?.[0])} /></label>
            {gifs.map((gif) => <button key={gif.id} className="gif-tile" onClick={() => send(null, gif.gif_path)}><img src={mediaUrl(gif.gif_path)} alt={gif.title || 'Saved GIF'} /></button>)}
            {gifs.length === 0 && <p className="gif-empty">Your uploaded GIFs will appear here only for you.</p>}
          </div>}
        </section>
      )}

      <form className="wa-chat-form" onSubmit={send}>
        <button className="emoji-trigger" type="button" onClick={() => setPickerOpen(!pickerOpen)} aria-label="Open emoji and GIF picker">
          <i className="bi bi-emoji-smile" />
        </button>
        <label className="media-pick" aria-label="Attach media">
          <i className="bi bi-paperclip" />
          <input type="file" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,audio/mpeg,audio/wav,audio/webm" onChange={(event) => setMedia(event.target.files?.[0] || null)} />
        </label>
        <input value={text} onChange={(event) => setText(event.target.value)} placeholder={media ? `Attached: ${media.name}` : 'Message'} />
        <button aria-label="Send message"><i className="bi bi-send" /></button>
      </form>
    </main>
  );
}
