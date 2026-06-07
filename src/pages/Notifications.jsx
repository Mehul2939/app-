import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../api/client';
import UserAvatar from '../components/UserAvatar';

export default function Notifications() {
  const [items, setItems] = useState([]);
  const navigate = useNavigate();
  const load = async () => setItems((await api.get('/notification/list.php')).data.notifications || []);
  useEffect(() => { load(); const timer = setInterval(load, 3000); return () => clearInterval(timer); }, []);
  const markAll = async () => { await api.post('/notification/read.php', {}); load(); };
  const openItem = async (item) => {
    await api.post('/notification/read.php', { notification_id: item.id });
    if (item.type === 'new_message' && item.actor_id) navigate(`/chat/${item.actor_id}`);
    else if ((item.type === 'post_like' || item.type === 'post_comment') && item.post_slug) navigate(`/post/${item.post_slug}`);
    else if (item.actor_username) navigate(`/user/${item.actor_username}`);
    else load();
  };
  return <main className="narrow-page"><section className="panel"><div className="title-row"><h2>Notifications</h2><button className="btn btn-sm btn-dark" onClick={markAll}>Read all</button></div>{items.map(n => <button className={`notice notice-row notification-page-item ${Number(n.is_read) === 0 ? 'unread' : ''}`} key={n.id} onClick={() => openItem(n)}><UserAvatar user={{ profile_photo: n.actor_photo, name: n.actor_name || 'System' }} /><span>{n.message}<small>{n.created_at}</small></span></button>)}</section></main>;
}
