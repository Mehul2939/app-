import { useMemo, useState } from 'react';
import api from '../api/client';
import { timeAgo } from '../utils/time';
import { mediaUrl } from '../utils/media';
import UserAvatar from './UserAvatar';
import Comments from './Comments';
import PostAudioButton from './PostAudioButton';

export default function PostCard({ post, onChanged }) {
  const [commentCount, setCommentCount] = useState(Number(post.comments_count || 0));
  const reactions = [
    ['like', '♥'],
    ['love', '😍'],
    ['laugh', '😂'],
    ['sad', '😢'],
    ['angry', '😡']
  ];

  const media = post.media || [];
  const images = useMemo(() => media.filter((m) => m.media_type === 'image'), [media]);
  const audios = useMemo(() => {
    const postAudio = post.audio_url ? [{ id: 'audio-url', media_path: post.audio_url, media_type: 'audio' }] : [];
    return [...postAudio, ...media.filter((m) => m.media_type === 'audio')];
  }, [media, post.audio_url]);
  const videos = useMemo(() => media.filter((m) => m.media_type === 'video'), [media]);

  const toggleLike = async () => {
    await api.post('/like/toggle.php', { post_id: post.id });
    onChanged?.();
  };

  const copyLink = () => navigator.clipboard?.writeText(`${location.origin}/app/post/${post.slug || post.id}`);

  const react = async (reaction_type) => {
    await api.post('/reaction/toggle.php', { post_id: post.id, reaction_type });
    onChanged?.();
  };

  return (
    <article className="post-card">
      {images.length > 0 && <div className="post-image-stack">
        {images.map((m) => <img key={m.id} className={post.is_sensitive > 0 ? 'sensitive-media' : ''} loading="lazy" src={mediaUrl(m.media_path)} alt={m.alt_text || post.meta_title || post.post_text || 'Post image'} />)}
      </div>}

      {audios.length > 0 && <div className="post-audio-stack">
        {audios.map((m) => <PostAudioButton key={m.id} post={post} src={mediaUrl(m.media_path)} />)}
      </div>}

      {videos.length > 0 && <div className="media-grid">
        {videos.map((m) => <video key={m.id} controls preload="metadata" src={mediaUrl(m.media_path)} />)}
      </div>}

      {(post.post_text || post.name) && <div className="post-caption-block">
        <div className="post-head compact">
          <UserAvatar user={post} />
          <div><strong>{post.name}</strong><small>@{post.username} · {timeAgo(post.created_at)}</small></div>
        </div>
        {post.post_text && <p className="post-text">{post.post_text}</p>}
      </div>}

      <div className="post-actions">
        <button onClick={toggleLike} className={post.liked_by_me > 0 ? 'active' : ''}><i className="bi bi-heart-fill" /> {post.likes_count || 0}</button>
        <button type="button"><i className="bi bi-chat" /> {commentCount}</button>
        <button type="button" onClick={copyLink}><i className="bi bi-share" /></button>
        <button type="button"><i className="bi bi-bookmark" /></button>
      </div>

      <div className="reaction-row">
        {reactions.map(([type, icon]) => <button type="button" key={type} className={post.my_reaction === type ? 'active' : ''} onClick={() => react(type)}>{icon}</button>)}
        <span>{post.reactions_count || 0} reactions</span>
      </div>

      <Comments postId={post.id} initialCount={commentCount} onCountChange={setCommentCount} />
    </article>
  );
}
