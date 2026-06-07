import { useEffect, useRef, useState } from 'react';
import { addComment, deleteComment, editComment, getComments, likeComment } from '../api/api';
import { timeAgo } from '../utils/time';
import UserAvatar from './UserAvatar';

function mergeComments(oldItems, newItems) {
  const map = new Map();
  [...oldItems, ...newItems].forEach((item) => map.set(Number(item.id), item));
  return [...map.values()].sort((a, b) => Number(a.id) - Number(b.id));
}

export default function Comments({ postId, initialCount = 0, onCountChange }) {
  const [comments, setComments] = useState([]);
  const [locked, setLocked] = useState(false);
  const [text, setText] = useState('');
  const [replyTo, setReplyTo] = useState(null);
  const [editing, setEditing] = useState(null);
  const pollRef = useRef(null);

  const fetchComments = async () => {
    try {
      const { data } = await getComments(postId);
      setLocked(Boolean(data.locked));
      setComments((old) => mergeComments(old, data.comments || []));
      onCountChange?.(Number(data.total || data.comments?.length || 0));
    } catch {
      setComments([]);
    }
  };

  useEffect(() => {
    fetchComments();
    pollRef.current = setInterval(fetchComments, 4000);
    return () => clearInterval(pollRef.current);
  }, [postId]);

  const submit = async (event) => {
    event.preventDefault();
    if (!text.trim()) return;
    if (editing) {
      await editComment({ comment_id: editing.id, comment_text: text });
      setEditing(null);
    } else {
      await addComment({ post_id: postId, comment_text: text, parent_comment_id: replyTo?.id || 0 });
    }
    setText('');
    setReplyTo(null);
    await fetchComments();
  };

  const startEdit = (comment) => {
    setEditing(comment);
    setReplyTo(null);
    setText(comment.comment_text);
  };

  const remove = async (commentId) => {
    await deleteComment(commentId);
    setComments((items) => items.filter((item) => Number(item.id) !== Number(commentId)));
    onCountChange?.(Math.max(0, comments.length - 1));
    fetchComments();
  };

  const toggleLike = async (commentId) => {
    await likeComment(commentId);
    fetchComments();
  };

  return (
    <section className="inline-comments">
      {locked && <button type="button" className="locked-comments">Login to view and participate in comments.</button>}
      {!locked && comments.slice(0, 8).map((c) => (
        <div className={`inline-comment ${c.parent_comment_id ? 'is-reply' : ''}`} key={c.id}>
          <UserAvatar user={c} />
          <div>
            <div className="comment-meta">
              <strong>@{c.username}</strong>
              {c.parent_username && <span>replied to @{c.parent_username}</span>}
              <small>{timeAgo(c.created_at)}</small>
            </div>
            <p>{c.comment_text}</p>
            <div className="comment-actions">
              <button type="button" className={Number(c.liked_by_me) > 0 ? 'active' : ''} onClick={() => toggleLike(c.id)}>♥ {c.likes_count || 0}</button>
              <button type="button" onClick={() => setReplyTo(c)}>Reply</button>
              <button type="button" onClick={() => startEdit(c)}>Edit</button>
              <button type="button" onClick={() => remove(c.id)}>Delete</button>
            </div>
          </div>
        </div>
      ))}
      {!locked && comments.length === 0 && <div className="comment-empty">No comments yet.</div>}
      {(replyTo || editing) && <div className="reply-strip comment-reply-strip"><span>{editing ? 'Editing comment' : `Replying to @${replyTo.username}`}</span><button type="button" onClick={() => { setReplyTo(null); setEditing(null); setText(''); }}>Cancel</button></div>}
      {!locked && <form className="comment-row" onSubmit={submit}>
        <input value={text} onChange={(e) => setText(e.target.value)} placeholder={editing ? 'Edit comment' : replyTo ? `Reply to @${replyTo.username}` : 'Write a comment'} />
        <button className="comment-send-btn" aria-label="Send comment"><i className="bi bi-send" /></button>
      </form>}
      {!locked && initialCount > comments.length && <small className="comment-count-hint">{initialCount} total comments. Latest comments update live.</small>}
    </section>
  );
}

