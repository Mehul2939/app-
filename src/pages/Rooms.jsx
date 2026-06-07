import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../api/client';
import RoomCard from '../components/rooms/RoomCard';

export default function Rooms() {
  const [rooms,setRooms]=useState([]);const [q,setQ]=useState('');const [category,setCategory]=useState('');const [type,setType]=useState('');const [loading,setLoading]=useState(true);
  const load=async()=>{setLoading(true);const {data}=await api.get(`/room/list.php?q=${encodeURIComponent(q)}&category=${category}&type=${type}`);setRooms(data.rooms||[]);setLoading(false);};
  useEffect(()=>{load();const timer=setInterval(load,10000);return()=>clearInterval(timer);},[q,category,type]);
  const follow=async room=>{await api.post('/room/action.php',{room_id:room.id,action:'follow'});load();};
  return <main className="rooms-page"><section className="rooms-hero"><span className="room-eyebrow">Live communities</span><h1>Voice & Watch Rooms</h1><p>Talk, listen, watch together and meet your community.</p><Link className="btn btn-light" to="/rooms/create"><i className="bi bi-plus-circle" /> Create Room</Link></section>
    <section className="room-filters"><input value={q} onChange={e=>setQ(e.target.value)} placeholder="Search rooms or hosts" /><select value={category} onChange={e=>setCategory(e.target.value)}><option value="">All categories</option>{['music','dating','study','gaming','fun','help'].map(x=><option key={x}>{x}</option>)}</select><select value={type} onChange={e=>setType(e.target.value)}><option value="">Voice & video</option><option value="voice">Voice</option><option value="video">Video</option></select></section>
    {loading?<div className="room-empty">Loading active rooms...</div>:rooms.length?<section className="rooms-grid">{rooms.map(r=><RoomCard key={r.id} room={r} onFollow={follow}/>)}</section>:<div className="room-empty"><i className="bi bi-broadcast-pin"/><h2>No active rooms</h2><p>Create the first room and invite your friends.</p></div>}
  </main>;
}
