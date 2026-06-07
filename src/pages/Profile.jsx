import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import api from '../api/client';
import GiftModal from '../components/GiftModal';
import Loader from '../components/Loader';
import Seo from '../components/Seo';
import { mediaUrl } from '../utils/media';
import UserAvatar from '../components/UserAvatar';
import PostCard from '../components/PostCard';

export default function Profile() {
  const { username } = useParams();
  const [data, setData] = useState(null);
  const [activeTab, setActiveTab] = useState('posts');
  const [posts, setPosts] = useState([]);
  const [people, setPeople] = useState([]);
  const [postSearch, setPostSearch] = useState('');
  const [showGift, setShowGift] = useState(false);

  const load = async () => {
    const res = await api.get('/user/profile.php' + (username ? `?username=${username}` : ''));
    setData(res.data);
  };

  const loadTab = async (tab = activeTab) => {
    if (!data?.profile) return;
    const p = data.profile;
    if (tab === 'posts') {
      const res = await api.get(`/post/list.php?username=${encodeURIComponent(p.username)}&q=${encodeURIComponent(postSearch)}`);
      setPosts(res.data.posts || []);
      return;
    }
    if (tab === 'friends') {
      const res = await api.get(`/friend/list.php?user_id=${p.id}`);
      setPeople(res.data.friends || []);
      return;
    }
    const res = await api.get(`/user/followers.php?user_id=${p.id}&type=${tab === 'following' ? 'following' : 'followers'}`);
    setPeople(res.data.users || []);
  };

  useEffect(() => { load(); }, [username]);
  useEffect(() => { loadTab(activeTab); }, [data?.profile?.id, activeTab, postSearch]);

  if (!data) return <Loader />;
  const p = data.profile;

  const follow = async () => { await api.post(p.is_following ? '/follow/unfollow.php' : '/follow/follow.php', { user_id: p.id }); load(); };
  const friendAction = async (action) => { await api.post('/friend/action.php', { user_id: p.id, action }); load(); };
  const blockAction = async () => { await api.post('/user/block.php', { user_id: p.id, action: p.blocked_by_me ? 'unblock' : 'block' }); load(); };
  const reportUser = async () => { const reason = prompt('Why are you reporting this profile?'); if (reason) await api.post('/user/report.php', { user_id: p.id, reason }); };

  const friendButtons = () => {
    if (p.is_me) return <button className="btn btn-outline-dark" onClick={() => setActiveTab('friends')}>Friends</button>;
    if (p.friend_status === 'friends') return <><Link className="btn btn-outline-dark" to={`/chat/${p.id}`}>Message</Link><button className="btn btn-outline-danger" onClick={() => friendAction('remove')}>Remove Friend</button></>;
    if (p.friend_status === 'request_sent') return <button className="btn btn-outline-dark" onClick={() => friendAction('cancel')}>Cancel Request</button>;
    if (p.friend_status === 'request_received') return <><button className="btn btn-dark" onClick={() => friendAction('accept')}>Accept Request</button><button className="btn btn-outline-dark" onClick={() => friendAction('reject')}>Reject Request</button></>;
    return <button className="btn btn-dark" onClick={() => friendAction('send')}>Add Friend</button>;
  };

  return <main className="profile-page premium-profile">
    <Seo title={`${p.name} (@${p.username}) on myself`} description={p.bio || `Public profile for ${p.name} on myself`} canonical={`${location.origin}/app/profile/${p.username}`} index={!p.is_me && p.account_type === 'public'} image={mediaUrl(p.profile_photo)} />
    <section className="premium-profile-hero">
      <div className="premium-cover" />
      <div className="premium-profile-body">
        <UserAvatar user={p} size="big" />
        <div className="premium-profile-copy">
          <h1>{p.name} {Number(p.is_demo_user) === 1 && <span className="demo-badge">Demo / AI</span>}</h1>
          <p>@{p.username}</p>
          <small>User ID {p.public_user_id} · {p.city || 'India'}</small>
          <span>{p.bio || 'Living the myself life.'}</span>
        </div>
      </div>
      <div className="premium-stats">
        <button onClick={() => setActiveTab('posts')} className={activeTab === 'posts' ? 'active' : ''}><b>{p.posts_count}</b><span>Posts</span></button>
        <button onClick={() => setActiveTab('followers')} className={activeTab === 'followers' ? 'active' : ''}><b>{p.followers_count}</b><span>Followers</span></button>
        <button onClick={() => setActiveTab('following')} className={activeTab === 'following' ? 'active' : ''}><b>{p.following_count}</b><span>Following</span></button>
        <button onClick={() => setActiveTab('friends')} className={activeTab === 'friends' ? 'active' : ''}><b>{p.friends_count}</b><span>Friends</span></button>
      </div>
      <div className="profile-actions premium-actions">
        {p.is_me ? <Link className="btn btn-dark" to="/profile/edit">Edit Profile</Link> : <button className="btn btn-dark" onClick={follow}>{p.is_following ? 'Unfollow' : 'Follow'}</button>}
        {friendButtons()}
        {!p.is_me && <button className="btn btn-outline-dark" onClick={() => setShowGift(!showGift)}>Gift</button>}
        {!p.is_me && <button className="btn btn-outline-danger" onClick={blockAction}>{p.blocked_by_me ? 'Unblock' : 'Block'}</button>}
        {!p.is_me && <button className="btn btn-outline-danger" onClick={reportUser}>Report</button>}
      </div>
      {p.blocked_me && <div className="alert alert-danger mx-3">You have been blocked.</div>}
      {showGift && <GiftModal receiverId={p.id} />}
    </section>

    {data.wallet && <section className="wallet-strip"><strong>{data.wallet.coins_balance}</strong><span>coins in your wallet</span></section>}

    <section className="profile-tabs-panel">
      <div className="profile-tabbar">
        {['posts', 'followers', 'following', 'friends'].map(tab => <button key={tab} className={activeTab === tab ? 'active' : ''} onClick={() => setActiveTab(tab)}>{tab}</button>)}
      </div>
      {activeTab === 'posts' && <>
        <input className="form-control premium-search" placeholder="Search this user's posts" value={postSearch} onChange={e => setPostSearch(e.target.value)} />
        <div className="profile-post-grid">{posts.map(post => <PostCard key={post.id} post={post} onChanged={() => loadTab('posts')} />)}</div>
        {posts.length === 0 && <div className="empty-state">No public posts yet.</div>}
      </>}
      {activeTab !== 'posts' && <div className="premium-user-list">
        {people.map(user => <PremiumUserCard key={user.id || user.user_id} user={user} />)}
        {people.length === 0 && <div className="empty-state">Nothing to show yet.</div>}
      </div>}
    </section>

    <section className="panel"><h3>Received gifts</h3><div className="gift-row">{data.gifts.map(g => <span key={g.id}>{g.gift_icon} {g.gift_name}</span>)}</div></section>
  </main>;
}

function PremiumUserCard({ user }) {
  return <div className="premium-user-card">
    <UserAvatar user={user} />
    <Link to={`/profile/${user.username}`} className="premium-user-copy"><strong>{user.name}</strong><span>@{user.username}</span><small>{user.bio || 'myself user'}</small></Link>
    <button className="btn btn-sm btn-outline-dark">Follow</button>
  </div>;
}
