import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import api from '../api/client';
import UserAvatar from './UserAvatar';

export default function ProfilePreviewModal() {
  const [open, setOpen] = useState(false);
  const [profile, setProfile] = useState(null);
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const loadProfile = async (detail) => {
    const username = detail?.username || detail?.sender_username;
    if (!username) return;
    setOpen(true);
    setLoading(true);
    try {
      const { data } = await api.get(`/user/profile.php?username=${encodeURIComponent(username)}`);
      setProfile(data.profile || null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const handler = (event) => loadProfile(event.detail || {});
    window.addEventListener('myself:profile-preview', handler);
    return () => window.removeEventListener('myself:profile-preview', handler);
  }, []);

  if (!open) return null;

  const close = () => {
    setOpen(false);
    setProfile(null);
  };

  const friendAction = async (action) => {
    if (!profile) return;
    await api.post('/friend/action.php', { user_id: profile.id, action });
    const { data } = await api.get(`/user/profile.php?username=${encodeURIComponent(profile.username)}`);
    setProfile(data.profile || null);
  };

  const follow = async () => {
    if (!profile) return;
    await api.post(profile.is_following ? '/follow/unfollow.php' : '/follow/follow.php', { user_id: profile.id });
    const { data } = await api.get(`/user/profile.php?username=${encodeURIComponent(profile.username)}`);
    setProfile(data.profile || null);
  };

  const friendButton = () => {
    if (!profile || profile.is_me) return null;
    if (profile.friend_status === 'friends') return <button onClick={() => friendAction('remove')} className="btn btn-outline-dark">Friends</button>;
    if (profile.friend_status === 'request_sent') return <button onClick={() => friendAction('cancel')} className="btn btn-outline-dark">Cancel</button>;
    if (profile.friend_status === 'request_received') return <button onClick={() => friendAction('accept')} className="btn btn-dark">Accept</button>;
    return <button onClick={() => friendAction('send')} className="btn btn-dark">Add Friend</button>;
  };

  const viewProfile = () => {
    if (!profile) return;
    close();
    navigate(`/profile/${profile.username}`);
  };

  return (
    <div className="profile-preview-backdrop" onClick={close}>
      <section className="profile-preview-sheet" onClick={(event) => event.stopPropagation()} role="dialog" aria-modal="true">
        <button className="sheet-close" onClick={close} aria-label="Close"><i className="bi bi-x-lg" /></button>
        {loading && <div className="profile-skeleton"><span /><b /><p /><p /></div>}
        {!loading && profile && (
          <>
            <div className="preview-cover" />
            <div className="preview-avatar-wrap"><UserAvatar user={profile} size="preview" /></div>
            <div className="preview-info">
              <h2>{profile.name} {Number(profile.is_demo_user) === 1 ? <span className="demo-badge">Demo / AI</span> : <i className="bi bi-patch-check-fill verified-badge" title="Verified" />}</h2>
              <p>@{profile.username}</p>
              <small>User ID {profile.public_user_id}</small>
              <span>{profile.bio || 'No bio yet.'}</span>
              <em>Joined {new Date(profile.created_at?.replace(' ', 'T')).toLocaleDateString()}</em>
            </div>
            <div className="preview-stats">
              <button><b>{profile.posts_count}</b><span>Posts</span></button>
              <button><b>{profile.followers_count}</b><span>Followers</span></button>
              <button><b>{profile.following_count}</b><span>Following</span></button>
              <button><b>{profile.friends_count}</b><span>Friends</span></button>
            </div>
            <div className="preview-actions">
              {!profile.is_me && <button onClick={follow} className="btn btn-outline-dark">{profile.is_following ? 'Unfollow' : 'Follow'}</button>}
              {friendButton()}
              {!profile.is_me && profile.friend_status === 'friends' && <Link onClick={close} to={`/chat/${profile.id}`} className="btn btn-outline-dark">Message</Link>}
              <button onClick={viewProfile} className="btn btn-dark">View Full Profile</button>
            </div>
          </>
        )}
      </section>
    </div>
  );
}
