import { Link } from 'react-router-dom';
import UserAvatar from './UserAvatar';

export default function NotificationBar({ open, notifications, onClose, onMarkAll, onOpenItem }) {
  if (!open) return null;
  return <>
    <button className="notification-sheet-backdrop" onClick={onClose} aria-label="Close notifications" />
    <section className="notification-menu" aria-label="Notifications">
      <div className="notification-menu-handle" />
      <div className="notification-menu-head">
        <div><strong>Notifications</strong><small>Recent activity</small></div>
        <button onClick={onMarkAll}>Read all</button>
      </div>
      <div className="notification-scroll">
        {notifications.length === 0 && <div className="notification-empty">No notifications</div>}
        {notifications.slice(0, 8).map(item => <button key={item.id} className={`notification-item ${Number(item.is_read) === 0 ? 'unread' : ''}`} onClick={() => onOpenItem(item)}>
          <UserAvatar user={{ profile_photo: item.actor_photo, name: item.actor_name || 'System' }} />
          <span className="notification-copy"><b>{item.actor_name || 'Notification'}</b><span>{item.message}</span><small>{item.created_at}</small></span>
        </button>)}
      </div>
      <Link to="/notifications" onClick={onClose} className="notification-all">View all notifications</Link>
    </section>
  </>;
}

