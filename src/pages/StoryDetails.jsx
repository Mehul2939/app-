import { useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import api from '../api/client';
import { useAuth } from '../contexts/AuthContext';
import UserAvatar from '../components/UserAvatar';
import StoryAudioButton from '../components/StoryAudioButton';

const mediaUrl = (path) => path ? `/app/${path}` : '';
const reactionIcons = { love: '♥', hot: '🔥', amazing: '😍', wow: '😮' };

export default function StoryDetails() {
  const { slug } = useParams();
  const { isLoggedIn, user } = useAuth();
  const [data, setData] = useState(null);
  const [comment, setComment] = useState('');
  const [replyTo, setReplyTo] = useState(null);
  const load = () => api.get('/story/detail.php', { params: { slug } }).then(({ data: result }) => setData(result));
  useEffect(() => { load(); }, [slug, isLoggedIn]);
  useEffect(() => {
    if (!data?.story?.id) return;
    let fingerprint = localStorage.getItem('story_fingerprint');
    if (!fingerprint) { fingerprint = `${Date.now()}-${Math.random()}`; localStorage.setItem('story_fingerprint', fingerprint); }
    api.post('/story/view.php', { story_id: data.story.id, fingerprint });
  }, [data?.story?.id]);
  const comments = useMemo(() => data?.comments || [], [data]);
  if (!data) return <main className="article-page"><div className="panel">Loading story...</div></main>;
  const { story } = data;
  const interact = async (endpoint, payload) => { await api.post(`/story/${endpoint}.php`, { story_id: story.id, ...payload }); load(); };
  const submitComment = async (e) => { e.preventDefault(); await interact('comment', { comment_text: comment, parent_comment_id: replyTo?.id || null }); setComment(''); setReplyTo(null); };
  const ownAction = async (item, action) => {
    if (action === 'edit') { const text = prompt('Edit comment', item.comment_text); if (text) await interact('comment', { action, comment_id: item.id, comment_text: text }); }
    else if (confirm('Delete this comment?')) await interact('comment', { action, comment_id: item.id });
  };
  return <main className="article-page story-detail-page">
    <nav className="breadcrumbs"><Link to="/">Home</Link><span>/</span><Link to="/stories">Stories</Link><span>/</span><span>{story.title}</span></nav>
    <article className="article-card story-article">
      <span className="story-category">{story.category}</span><h1>{story.title}</h1>
      <div className="story-byline"><b>Published by {story.author_name}</b><span>{story.admin_code}</span><span>{new Date(story.published_at).toLocaleDateString()}</span><span>Updated {new Date(story.updated_at).toLocaleDateString()}</span><span>{story.reading_time} min read</span><span>{story.views_count} views · {story.unique_views} unique</span></div>
      {story.featured_image && <img className="story-featured" src={mediaUrl(story.featured_image)} alt={story.title} />}
      {story.audio_path && <div className="story-audio"><StoryAudioButton story={story} src={mediaUrl(story.audio_path)} /></div>}
      <div className="story-rich-content" dangerouslySetInnerHTML={{ __html: story.content }} />
      <div className="story-engagement">
        <button disabled={!isLoggedIn} className={Number(story.liked_by_me) ? 'active' : ''} onClick={() => interact('like', {})}><i className="bi bi-heart-fill" /> {story.likes_count} Likes</button>
        {Object.entries(reactionIcons).map(([type, icon]) => <button disabled={!isLoggedIn} className={story.my_reaction === type ? 'active' : ''} onClick={() => interact('reaction', { reaction_type: type })} key={type}>{icon} {data.reactions[type] || 0}</button>)}
      </div>
      {!isLoggedIn && <div className="login-to-interact"><Link to="/login">Login</Link> to interact with stories.</div>}
      <section className="story-comments"><h2>Comments</h2>
        {isLoggedIn && <form onSubmit={submitComment} className="story-comment-form">{replyTo && <span>Replying to @{replyTo.username} <button type="button" onClick={() => setReplyTo(null)}>Cancel</button></span>}<textarea className="form-control" value={comment} onChange={(e) => setComment(e.target.value)} placeholder="Write a thoughtful comment..." required /><button className="btn btn-dark">Post comment</button></form>}
        {comments.filter(c => !c.parent_comment_id).map(c => <div className="story-comment" key={c.id}><UserAvatar user={c} /><div><b>{c.name}</b><small>{new Date(c.created_at).toLocaleString()}</small><p>{c.comment_text}</p><div className="comment-actions"><button onClick={() => setReplyTo(c)}>Reply</button>{Number(c.user_id) === Number(user?.id) && <><button onClick={() => ownAction(c, 'edit')}>Edit</button><button onClick={() => ownAction(c, 'delete')}>Delete</button></>}</div>
          {comments.filter(r => Number(r.parent_comment_id) === Number(c.id)).map(r => <div className="story-comment reply" key={r.id}><UserAvatar user={r} /><div><b>{r.name}</b><small>{new Date(r.created_at).toLocaleString()}</small><p>{r.comment_text}</p>{Number(r.user_id) === Number(user?.id) && <div className="comment-actions"><button onClick={() => ownAction(r, 'edit')}>Edit</button><button onClick={() => ownAction(r, 'delete')}>Delete</button></div>}</div></div>)}
        </div></div>)}
        {data.comments_locked && <div className="locked-comments">Comments are visible after login.</div>}
      </section>
    </article>
    <section className="related-stories"><h2>Related Stories</h2><div className="related-story-grid">{data.related.map(r => <Link to={`/stories/${r.slug}`} key={r.slug}><b>{r.title}</b><span>{r.category}</span></Link>)}</div></section>
  </main>;
}
