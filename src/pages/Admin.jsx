import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import api from '../api/client';

const emptyStory = { title: '', content: '', category: 'Stories', keywords: '', seo_tags: '', status: 'draft', publish_at: '' };

export default function Admin({ screen = 'dashboard' }) {
  const navigate = useNavigate();
  const [admin, setAdmin] = useState(null);
  const [ready, setReady] = useState(false);
  const [message, setMessage] = useState('');
  const [cards, setCards] = useState({});
  const [stories, setStories] = useState([]);
  const [comments, setComments] = useState([]);
  const [admins, setAdmins] = useState([]);
  const [notifications, setNotifications] = useState([]);
  const [story, setStory] = useState(emptyStory);

  const loadAdmin = () => api.get('/admin/auth.php').then(({ data }) => setAdmin(data.admin)).catch(() => setAdmin(null)).finally(() => setReady(true));
  const loadDashboard = () => {
    api.get('/admin/dashboard.php').then(({ data }) => setCards(data.cards || {}));
    api.get('/admin/stories.php').then(({ data }) => setStories(data.stories || []));
    api.get('/admin/story_comments.php').then(({ data }) => setComments(data.comments || []));
    api.get('/admin/admins.php').then(({ data }) => setAdmins(data.admins || [])).catch(() => {});
    api.get('/admin/notifications.php').then(({ data }) => setNotifications(data.notifications || []));
  };
  useEffect(() => { loadAdmin(); }, []);
  useEffect(() => { if (admin) loadDashboard(); }, [admin]);

  const authSubmit = async (e) => {
    e.preventDefault(); const form = Object.fromEntries(new FormData(e.currentTarget));
    try {
      const { data } = await api.post('/admin/login.php', form);
      localStorage.setItem('myself_admin_token', data.token); setAdmin(data.admin); navigate('/admin');
    } catch (err) { setMessage(err.response?.data?.message || 'Login failed'); }
  };
  const register = async (e) => {
    e.preventDefault(); const form = Object.fromEntries(new FormData(e.currentTarget));
    try { const { data } = await api.post('/admin/register.php', form); setMessage(data.message); e.currentTarget.reset(); loadDashboard(); }
    catch (err) { setMessage(err.response?.data?.message || 'Unable to create admin'); }
  };
  const saveStory = async (e) => {
    e.preventDefault(); const form = new FormData(e.currentTarget); if (story.id) form.set('story_id', story.id);
    try { const { data } = await api.post('/admin/stories.php', form, { headers: { 'Content-Type': 'multipart/form-data' } }); setMessage(data.message); setStory(emptyStory); e.currentTarget.reset(); loadDashboard(); }
    catch (err) { setMessage(err.response?.data?.message || 'Unable to save story'); }
  };
  const removeStory = async (id) => { if (confirm('Permanently delete this story?')) { await api.post('/admin/stories.php', { action: 'delete', story_id: id }); loadDashboard(); } };
  const moderate = async (id, status) => { await api.post('/admin/story_comments.php', { comment_id: id, status }); loadDashboard(); };
  const logout = async () => { await api.post('/admin/logout.php').catch(() => {}); localStorage.removeItem('myself_admin_token'); setAdmin(null); navigate('/admin/login'); };

  if (!ready) return <main className="admin-auth-page"><div className="admin-auth-card">Loading admin...</div></main>;
  if (!admin && screen === 'register') return <main className="admin-auth-page"><section className="admin-auth-card"><div className="admin-lock-mark"><i className="bi bi-shield-lock-fill" /></div><span className="admin-auth-kicker">Separate Admin Portal</span><h1>Super Admin login required</h1><p>Admin registration public user registration se completely separate hai. Naya admin sirf logged-in Super Admin create kar sakta hai.</p><Link className="btn btn-dark" to="/admin/login">Open Admin Login</Link><a className="btn btn-outline-dark" href="/app/public/login">Public User Login</a></section></main>;
  if (!admin) return <main className="admin-auth-page"><section className="admin-auth-intro"><div className="admin-lock-mark"><i className="bi bi-shield-lock-fill" /></div><span>Dedicated secure portal</span><h1>Admin Control Room</h1><p>This login is only for administrators. Public user credentials do not work here.</p></section><form className="admin-auth-card" onSubmit={authSubmit}><span className="admin-auth-kicker">Admin only</span><h2>Admin Login</h2>{message && <div className="alert alert-danger">{message}</div>}<input className="form-control" name="email" type="email" autoComplete="username" placeholder="Admin email" required /><input className="form-control" name="password" type="password" autoComplete="current-password" placeholder="Admin password" required /><button className="btn btn-dark">Secure Admin Login</button><Link to="/admin/register">Admin registration</Link><a className="auth-separate-link" href="/app/public/login">Go to Public User Login</a></form></main>;

  return <main className="admin-page story-admin">
    <header className="admin-heading"><div><span>{admin.admin_code} · {admin.role.replace('_', ' ')}</span><h1>Stories Admin</h1></div><button className="btn btn-outline-dark" onClick={logout}>Logout</button></header>
    <nav className="admin-tabs"><Link to="/admin">Dashboard</Link><a href="#editor">Story Editor</a><a href="#manage">Manage</a><a href="#moderation">Moderation</a>{admin.role === 'super_admin' && <Link to="/admin/register">Admins</Link>}</nav>
    {message && <div className="alert alert-info">{message}</div>}
    <div className="admin-grid">{Object.entries(cards).map(([k, v]) => <div className="metric" key={k}><span>{k.replaceAll('_', ' ')}</span><strong>{v}</strong></div>)}</div>
    <section className="panel"><div className="title-row"><h2>Engagement Notifications</h2><button className="btn btn-sm btn-outline-dark" onClick={async () => { await api.post('/admin/notifications.php', { read_all: true }); loadDashboard(); }}>Mark read</button></div>{notifications.slice(0, 12).map(n => <div className={`notice ${Number(n.is_read) ? '' : 'unread'}`} key={n.id}><b>{n.actor_name || 'User'} · {n.story_title}</b><span>{n.message}</span><small>{n.created_at}</small></div>)}</section>
    <section className="panel" id="editor"><h2>{story.id ? 'Edit Story' : 'Create Story'}</h2><form onSubmit={saveStory}>
      <input className="form-control" name="title" value={story.title} onChange={e => setStory({ ...story, title: e.target.value })} placeholder="Story title" required />
      <textarea className="form-control story-editor" name="content" value={story.content} onChange={e => setStory({ ...story, content: e.target.value })} placeholder="Rich HTML content. Use h2-h6 for content sections." required />
      <div className="admin-form-grid"><input className="form-control" name="category" value={story.category} onChange={e => setStory({ ...story, category: e.target.value })} placeholder="Category" /><input className="form-control" name="keywords" value={story.keywords || ''} onChange={e => setStory({ ...story, keywords: e.target.value })} placeholder="Keywords, comma separated" /><input className="form-control" name="seo_tags" value={story.seo_tags || ''} onChange={e => setStory({ ...story, seo_tags: e.target.value })} placeholder="SEO tags" /></div>
      <div className="admin-form-grid"><label>Featured image<input className="form-control" name="featured_image" type="file" accept="image/jpeg,image/png,image/webp" /></label><label>Optional audio<input className="form-control" name="audio_file" type="file" accept="audio/*" /></label></div>
      <div className="admin-form-grid"><select className="form-select" name="status" value={story.status} onChange={e => setStory({ ...story, status: e.target.value })}><option value="draft">Draft</option><option value="published">Published</option><option value="scheduled">Scheduled</option><option value="unpublished">Unpublished</option></select><input className="form-control" name="publish_at" type="datetime-local" value={story.publish_at?.replace(' ', 'T') || ''} onChange={e => setStory({ ...story, publish_at: e.target.value })} /></div>
      <small>Meta title and description are generated automatically from the title and opening content. Keywords and SEO tags stay hidden from readers.</small>
      <div className="inline-actions"><button className="btn btn-dark">Save story</button>{story.id && <button type="button" className="btn btn-outline-dark" onClick={() => setStory(emptyStory)}>Cancel edit</button>}</div>
    </form></section>
    <section className="panel" id="manage"><h2>Story Management</h2>{stories.map(s => <div className="admin-story-row" key={s.id}><div><b>{s.title}</b><span>{s.status} · {s.category} · {s.views_count} views · {s.comments_count} comments · {s.reactions_count} reactions</span><small>By {s.author_name} ({s.admin_code}) · Updated {s.updated_at}</small></div><div className="inline-actions"><button className="btn btn-sm btn-outline-dark" onClick={() => { setStory(s); location.hash = 'editor'; }}>Edit</button><button className="btn btn-sm btn-outline-danger" onClick={() => removeStory(s.id)}>Delete</button></div></div>)}</section>
    <section className="panel" id="moderation"><h2>Comment Moderation</h2>{comments.map(c => <div className="admin-story-row" key={c.id}><div><b>@{c.username} on {c.story_title}</b><p>{c.comment_text}</p><small>{c.status} · {c.created_at}</small></div><div className="inline-actions"><button className="btn btn-sm btn-outline-dark" onClick={() => moderate(c.id, 'active')}>Approve</button><button className="btn btn-sm btn-outline-warning" onClick={() => moderate(c.id, 'hidden')}>Hide</button><button className="btn btn-sm btn-outline-danger" onClick={() => moderate(c.id, 'deleted')}>Delete</button></div></div>)}</section>
    {admin.role === 'super_admin' && <section className="panel"><h2>Multi Admin Management</h2>{screen === 'register' && <form onSubmit={register} className="admin-form-grid"><input className="form-control" name="name" placeholder="Admin name" required /><input className="form-control" name="email" type="email" placeholder="Email" required /><input className="form-control" name="password" type="password" minLength="8" placeholder="Secure password" required /><select className="form-select" name="role"><option value="admin">Admin</option><option value="super_admin">Super Admin</option></select><button className="btn btn-dark">Create admin</button></form>}{admins.map(a => <div className="admin-story-row" key={a.id}><div><b>{a.name} · {a.admin_code}</b><span>{a.email} · {a.role}</span></div>{Number(a.id) !== Number(admin.id) && <button className="btn btn-sm btn-outline-danger" onClick={async () => { if (confirm('Remove this admin?')) { await api.post('/admin/admins.php', { remove_id: a.id }); loadDashboard(); } }}>Remove</button>}</div>)}</section>}
  </main>;
}
