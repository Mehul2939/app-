import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../api/client';
import UserAvatar from '../components/UserAvatar';

export default function Friends() {
  const [friends, setFriends] = useState([]);
  const [requests, setRequests] = useState([]);

  const load = async () => {
    const f = await api.get('/friend/list.php');
    const r = await api.get('/friend/list.php?type=requests');
    setFriends(f.data.friends || []);
    setRequests(r.data.requests || []);
  };

  useEffect(() => { load(); }, []);

  const action = async (userId, actionName) => {
    await api.post('/friend/action.php', { user_id: userId, action: actionName });
    load();
  };

  return <main className="narrow-page">
    <section className="panel">
      <h2>Friend Requests</h2>
      {requests.length === 0 && <p className="text-secondary">No pending requests.</p>}
      <div className="user-list">{requests.map(r => <div className="user-card user-card-actions" key={r.id}><Link to={`/user/${r.username}`} className="user-main"><UserAvatar user={r} /><div><b>{r.name}</b><span>@{r.username}</span></div></Link><div className="inline-actions"><button className="btn btn-sm btn-dark" onClick={() => action(r.user_id, 'accept')}>Accept</button><button className="btn btn-sm btn-outline-dark" onClick={() => action(r.user_id, 'reject')}>Reject</button></div></div>)}</div>
    </section>
    <section className="panel">
      <h2>Friends</h2>
      {friends.length === 0 && <p className="text-secondary">No friends yet. Search users and send a request.</p>}
      <div className="user-list">{friends.map(f => <div className="user-card user-card-actions" key={f.id}><Link to={`/user/${f.username}`} className="user-main"><UserAvatar user={f} /><div><b>{f.name}</b><span>@{f.username}</span></div></Link><div className="inline-actions"><Link className="btn btn-sm btn-dark" to={`/chat/${f.id}`}>Message</Link><button className="btn btn-sm btn-outline-danger" onClick={() => action(f.id, 'remove')}>Remove</button></div></div>)}</div>
    </section>
  </main>;
}
