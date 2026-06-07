import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../api/client';

const mediaUrl = (path) => path ? `/app/${path}` : '';

export default function Stories() {
  const [stories, setStories] = useState([]);
  const [categories, setCategories] = useState([]);
  const [q, setQ] = useState('');
  const [category, setCategory] = useState('');
  const load = () => api.get('/story/list.php', { params: { q, category } }).then(({ data }) => {
    setStories(data.stories || []);
    setCategories(data.categories || []);
  });
  useEffect(() => { load(); }, [category]);
  return <main className="stories-page">
    <section className="stories-hero">
      <span>Adults only · Curated by our editorial team</span>
      <h1>18+ Stories</h1>
      <p>Long-form stories, thoughtful writing, and immersive audio experiences.</p>
      <form onSubmit={(e) => { e.preventDefault(); load(); }} className="story-search">
        <input className="form-control" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search stories..." />
        <select className="form-select" value={category} onChange={(e) => setCategory(e.target.value)}>
          <option value="">All categories</option>{categories.map(c => <option key={c}>{c}</option>)}
        </select>
        <button className="btn btn-dark">Search</button>
      </form>
    </section>
    <section className="story-grid">
      {stories.map(story => <Link className="story-card" to={`/stories/${story.slug}`} key={story.id}>
        <div className="story-cover">{story.featured_image ? <img loading="lazy" src={mediaUrl(story.featured_image)} alt={story.title} /> : <i className="bi bi-book-half" />}</div>
        <div className="story-card-copy"><span className="story-category">{story.category}</span><h2>{story.title}</h2><p>{story.excerpt}</p>
          <div className="story-meta"><span>By {story.author_name}</span><span>{new Date(story.published_at).toLocaleDateString()}</span><span><i className="bi bi-eye" /> {story.views_count}</span><span><i className="bi bi-heart" /> {story.likes_count}</span><span>{story.reading_time} min read</span></div>
        </div>
      </Link>)}
      {!stories.length && <div className="empty-state">No published stories found.</div>}
    </section>
  </main>;
}

