import { useEffect, useState } from 'react';
import api from '../api/client';
import PostCard from '../components/PostCard';
import Loader from '../components/Loader';
import DiscoveryCard from '../components/DiscoveryCard';

export default function Home() {
  const [posts,setPosts]=useState([]); const [suggestions,setSuggestions]=useState([]); const [loading,setLoading]=useState(true);
  const load=async()=>{setLoading(true);const [p,u]=await Promise.all([api.get('/post/list.php'),api.get('/nearby-users.php?limit=12')]);setPosts(p.data.posts||[]);setSuggestions(u.data.users||[]);setLoading(false);};
  useEffect(()=>{load();},[]);
  return <main className="feed-page">
    <section className="composer-mini"><div><strong>myself feed</strong><span>Nearby people and latest posts</span></div><a className="btn btn-dark btn-sm" href="/app/public/create">Create</a></section>
    <section className="suggestion-section"><div className="title-row"><div><h2>Suggested chats</h2><span>Nearby and preference-based profiles</span></div><a href="/app/public/search">Explore all</a></div><div className="suggestion-scroll">{suggestions.slice(0,8).map(u=><DiscoveryCard key={u.id} user={u} onChanged={load} showIntro />)}</div></section>
    {loading?<Loader/>:posts.map(p=><PostCard key={p.id} post={p} onChanged={load}/>)}{!loading&&posts.length===0&&<div className="empty-state">No posts yet. Create the first moment.</div>}
  </main>;
}

