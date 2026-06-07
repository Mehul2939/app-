import { Link, NavLink, useNavigate } from 'react-router-dom';
import { useEffect, useState } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { useTheme } from '../contexts/ThemeContext';
import api from '../api/client';
import UserAvatar from './UserAvatar';
import NotificationBar from './NotificationBar';

export default function Navbar() {
  const { user, logout, isLoggedIn } = useAuth();
  const { isDark, toggleTheme } = useTheme();
  const navigate = useNavigate();
  const [notificationCount, setNotificationCount] = useState(0);
  const [messageCount, setMessageCount] = useState(0);
  const [notifications, setNotifications] = useState([]);
  const [menuOpen, setMenuOpen] = useState(false);
  const [bellOpen, setBellOpen] = useState(false);

  const loadCounts = async () => {
    if (!isLoggedIn) return;
    const [n, m] = await Promise.all([
      api.get('/notification/list.php').catch(() => ({ data: {} })),
      api.get('/chat/unread_count.php').catch(() => ({ data: {} }))
    ]);
    setNotificationCount(n.data.unread_count || 0);
    setNotifications(n.data.notifications || []);
    setMessageCount(m.data.unread_count || 0);
  };

  useEffect(() => {
    loadCounts();
    if (!isLoggedIn) return undefined;
    const timer = setInterval(loadCounts, 3000);
    return () => clearInterval(timer);
  }, [isLoggedIn]);

  const closeMenu = () => setMenuOpen(false);
  const markAll = async () => { await api.post('/notification/read.php', {}); loadCounts(); };
  const openNotification = async item => {
    await api.post('/notification/read.php', { notification_id: item.id });
    setBellOpen(false);
    loadCounts();
    if (item.type === 'new_message' && item.actor_id) navigate(`/chat/${item.actor_id}`);
    else if ((item.type === 'post_like' || item.type === 'post_comment') && item.post_slug) navigate(`/post/${item.post_slug}`);
    else if (item.actor_username) navigate(`/user/${item.actor_username}`);
    else navigate('/notifications');
  };

  const links = <>
    <NavLink onClick={closeMenu} to="/search">Search</NavLink>
    <NavLink onClick={closeMenu} to="/rooms">Rooms</NavLink>
    <NavLink onClick={closeMenu} to="/rooms/create">Create Room</NavLink>
    <NavLink onClick={closeMenu} to="/wallet">Wallet</NavLink>
    <NavLink onClick={closeMenu} to="/gift-store">Gifts</NavLink>
    <NavLink onClick={closeMenu} to="/friends">Friends</NavLink>
    <NavLink onClick={closeMenu} to="/messages" className="icon-link"><i className="bi bi-chat-dots" /><span>Messages</span>{messageCount > 0 && <span className="badge-dot">{messageCount}</span>}</NavLink>
  </>;

  return <header className="topbar">
    <button className="navbar-toggle" onClick={() => setMenuOpen(!menuOpen)} aria-label="Toggle navigation" aria-expanded={menuOpen}><i className={`bi ${menuOpen ? 'bi-x-lg' : 'bi-list'}`} /></button>
    <Link to="/" className="brand" onClick={closeMenu}><span className="brand-mark">m</span><span>myself</span></Link>
    <nav className="desktop-nav">{links}</nav>
    <div className="top-actions">
      <button className="icon-btn theme-toggle" onClick={toggleTheme} aria-label={isDark ? 'Use light mode' : 'Use dark mode'}><i className={`bi ${isDark ? 'bi-sun' : 'bi-moon-stars'}`} /></button>
      {isLoggedIn && <div className="bell-wrap"><button className="icon-btn" onClick={() => setBellOpen(!bellOpen)} aria-label="Notifications"><i className="bi bi-bell" />{notificationCount > 0 && <span className="badge-dot">{notificationCount}</span>}</button><NotificationBar open={bellOpen} notifications={notifications} onClose={() => setBellOpen(false)} onMarkAll={markAll} onOpenItem={openNotification} /></div>}
      {user ? <Link className="top-profile-link" to="/profile/edit" onClick={closeMenu} aria-label="Edit profile"><UserAvatar user={user} disablePreview /><span>Edit profile</span></Link> : <NavLink className="top-login-link" to="/login">Login</NavLink>}
    </div>
    <nav className={`mobile-menu ${menuOpen ? 'open' : ''}`}>{links}<button type="button" onClick={toggleTheme}><i className={`bi ${isDark ? 'bi-sun' : 'bi-moon-stars'}`} />{isDark ? 'Light mode' : 'Dark mode'}</button>{isLoggedIn ? <button className="btn btn-dark" onClick={() => { closeMenu(); logout(); }}>Logout</button> : <NavLink onClick={closeMenu} to="/login">Login</NavLink>}</nav>
  </header>;
}
