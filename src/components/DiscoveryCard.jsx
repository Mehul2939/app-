import { Link } from 'react-router-dom';
import UserAvatar from './UserAvatar';
import api from '../api/client';

export default function DiscoveryCard({ user, onChanged, showIntro = false }) {
  const friendAction = async action => { await api.post('/friend/action.php', { user_id: user.id, action }); onChanged?.(); };
  const report = async () => {
    const reason = prompt('Why are you reporting this profile?');
    if (reason) await api.post('/user/report.php', { user_id: user.id, reason });
  };
  const friendButton = () => {
    if (user.friend_status === 'friends') return <Link className="btn btn-sm btn-outline-dark" to={`/chat/${user.id}`}>Message</Link>;
    if (user.friend_status === 'request_sent') return <button className="btn btn-sm btn-outline-dark" onClick={() => friendAction('cancel')}>Cancel request</button>;
    if (user.friend_status === 'request_received') return <button className="btn btn-sm btn-dark" onClick={() => friendAction('accept')}>Accept</button>;
    return <button className="btn btn-sm btn-dark" onClick={() => friendAction('send')}>Add friend</button>;
  };
  return <article className="discovery-card">
    <div className="discovery-head"><UserAvatar user={user} /><div><Link to={`/profile/${user.username}`}><strong>{user.name}</strong></Link><span>@{user.username}</span></div>{Number(user.is_demo_user) === 1 && <b className="demo-badge">Demo / AI</b>}</div>
    <div className="discovery-badges">{user.gender && <span>{user.gender}</span>}{user.age && <span>{user.age} years</span>}{user.city && <span><i className="bi bi-geo-alt" /> {user.city}{user.state ? `, ${user.state}` : ''}</span>}{user.distance_km !== null && user.distance_km !== undefined && <span>~{user.distance_km} km away</span>}{Number(user.is_online) === 1 && <span className="online-label">Online</span>}</div>
    <p>{user.bio || 'Looking for respectful friendship and good conversations.'}</p>
    {showIntro && <div className="demo-intro"><b>{Number(user.is_demo_user) === 1 ? 'Demo intro message' : 'Suggested chat'}</b><span>{user.bio}</span></div>}
    <div className="discovery-actions">{friendButton()}<Link className="btn btn-sm btn-outline-dark" to={`/profile/${user.username}`}>View profile</Link><button className="btn btn-sm btn-outline-danger" onClick={report}>Report</button></div>
  </article>;
}

