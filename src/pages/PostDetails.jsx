import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import api from '../api/client';
import Loader from '../components/Loader';
import Seo from '../components/Seo';
import { mediaUrl } from '../utils/media';
import { timeAgo } from '../utils/time';
import UserAvatar from '../components/UserAvatar';
import PostAudioButton from '../components/PostAudioButton';

export default function PostDetails() {
  const { slug } = useParams();
  const [data, setData] = useState(null);
  const [comments, setComments] = useState(null);
  const [showLoginWall, setShowLoginWall] = useState(false);
  const [commentText, setCommentText] = useState('');

  const load = async () => {
    const postRes = await api.get(`/post/detail.php?slug=${encodeURIComponent(slug)}`);
    setData(postRes.data);
    const commentRes = await api.get(`/comment/list.php?post_id=${postRes.data.post.id}`);
    setComments(commentRes.data);
  };

  useEffect(() => { load(); }, [slug]);

  const addComment = async (e) => {
    e.preventDefault();
    if (comments?.locked) {
      setShowLoginWall(true);
      return;
    }
    if (!commentText.trim()) return;
    await api.post('/comment/add.php', { post_id: data.post.id, comment_text: commentText });
    setCommentText('');
    load();
  };

  if (!data) return <Loader />;
  const post = data.post;
  const image = post.media?.find(m => m.media_type === 'image');
  const description = post.meta_description || post.post_text?.slice(0, 155) || 'Public post on myself';

  return <main className="article-page">
    <Seo title={`${post.meta_title || 'Post'} - myself`} description={description} canonical={`${location.origin}/app/post/${post.slug}`} index={post.privacy === 'public'} image={image ? mediaUrl(image.media_path) : ''} />
    <nav className="breadcrumbs"><Link to="/">Home</Link><span>/</span><span>Post</span></nav>
    <article className="article-card">
      <h1>{post.meta_title || post.post_text?.slice(0, 80) || 'Public post'}</h1>
      <p className="article-author">By <Link to={`/user/${post.username}`}>{post.name}</Link> · {timeAgo(post.created_at)}</p>
      <h2>Post</h2>
      <p className="post-text">{post.post_text}</p>
      {post.media?.length > 0 && <section><h3>Media</h3><div className="media-grid">{post.media.map(m => m.media_type === 'video'
        ? <video key={m.id} controls preload="metadata" src={mediaUrl(m.media_path)} />
        : m.media_type === 'audio'
          ? <PostAudioButton key={m.id} post={post} src={mediaUrl(m.media_path)} />
          : <img key={m.id} className={post.is_sensitive > 0 ? 'sensitive-media' : ''} loading="lazy" src={mediaUrl(m.media_path)} alt={m.alt_text || post.meta_title || 'Post image'} />
      )}</div>{post.is_sensitive > 0 && <p className="content-warning">Sensitive content is blurred by default. Click an image to reveal in future preference settings.</p>}</section>}
      <section className="comments-section">
        <h2>Comments <span>{comments?.total || post.comments_count || 0}</span></h2>
        {comments?.locked ? <button className="locked-comments" onClick={() => setShowLoginWall(true)}>Comments are locked. Login to view and participate.</button> : comments?.comments?.map(c => <div className="comment-item comment-with-avatar" key={c.id}><UserAvatar user={c} /><div><strong>{c.name}</strong><p>{c.comment_text}</p></div></div>)}
        <form className="comment-row" onSubmit={addComment}><input value={commentText} onChange={e => setCommentText(e.target.value)} placeholder="Write a comment" /><button aria-label="Send comment"><i className="bi bi-send" /></button></form>
      </section>
    </article>
    <section className="panel"><h2>Related posts</h2>{data.related_posts.map(r => <Link className="related-link" key={r.id} to={`/post/${r.slug}`}>{r.post_text?.slice(0, 120) || 'Related post'}</Link>)}</section>
    {showLoginWall && <div className="modal-backdrop-lite" role="dialog" aria-modal="true"><div className="login-wall"><h3>Please login to view and participate in comments.</h3><Link className="btn btn-dark" to="/login">Login</Link><button className="btn btn-outline-dark" onClick={() => setShowLoginWall(false)}>Close</button></div></div>}
  </main>;
}
