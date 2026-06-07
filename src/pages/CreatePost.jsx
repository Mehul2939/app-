import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../api/client';

export default function CreatePost() {
  const nav = useNavigate();
  const [text, setText] = useState('');
  const [privacy, setPrivacy] = useState('public');
  const [isSensitive, setIsSensitive] = useState(false);
  const [altText, setAltText] = useState('');
  const [media, setMedia] = useState([]);
  const [msg, setMsg] = useState('');
  const submit = async (e) => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('post_text', text);
    fd.append('privacy', privacy);
    if (isSensitive) fd.append('is_sensitive', '1');
    fd.append('alt_text', altText);
    [...media].forEach(file => fd.append('media[]', file));
    try {
      await api.post('/post/create.php', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
      nav('/');
    } catch (err) { setMsg(err.response?.data?.message || 'Post failed'); }
  };
  return <main className="narrow-page"><form className="panel" onSubmit={submit}>
    <h2>Create Post</h2>
    <textarea className="form-control" rows="6" value={text} onChange={e => setText(e.target.value)} placeholder="What is happening?" />
    <select className="form-select" value={privacy} onChange={e => setPrivacy(e.target.value)}><option value="public">Public</option><option value="followers">Followers</option><option value="private">Private</option></select>
    <input className="form-control" placeholder="Image alt text for accessibility and SEO" value={altText} onChange={e => setAltText(e.target.value)} />
    <label className="check-row"><input type="checkbox" checked={isSensitive} onChange={e => setIsSensitive(e.target.checked)} /> Mark media as sensitive/adult content</label>
    <input className="form-control" type="file" multiple accept="image/jpeg,image/png,image/webp,video/mp4" onChange={e => setMedia(e.target.files)} />
    {msg && <div className="alert alert-danger">{msg}</div>}
    <button className="btn btn-dark">Post</button>
  </form></main>;
}
