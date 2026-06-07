import { Link, useNavigate } from 'react-router-dom';
import { useState } from 'react';
import api from '../api/client';
import { AuthShell } from './Login';
import { requestApproximateLocation } from '../utils/location';

export default function Register() {
  const nav = useNavigate();
  const [form, setForm] = useState({ name:'',username:'',email:'',mobile:'',password:'',gender:'',state:'',city:'',latitude:'',longitude:'',date_of_birth:'',interests:'',preferred_gender_filter:'Any',confirm_adult:false });
  const [photo, setPhoto] = useState(null);
  const [msg, setMsg] = useState('');
  const [locating, setLocating] = useState(false);
  const useLocation = async () => {
    setLocating(true); setMsg('');
    try { const location = await requestApproximateLocation(); setForm(current => ({ ...current, ...location })); setMsg('Approximate location added. You can edit state/city before registering.'); }
    catch { setMsg('Location permission was not available. Please enter state and city manually.'); }
    finally { setLocating(false); }
  };
  const submit = async e => {
    e.preventDefault(); setMsg('');
    if (!form.email && !form.mobile) { setMsg('Email or mobile me se kam se kam ek required hai.'); return; }
    const payload = new FormData();
    Object.entries(form).forEach(([key,value]) => payload.append(key, value === true ? '1' : value));
    if (photo) payload.append('profile_photo', photo);
    try { const { data } = await api.post('/register.php', payload, { headers: { 'Content-Type':'multipart/form-data' } }); if (data.success) nav('/login'); else setMsg(data.message); }
    catch (err) { setMsg(err.response?.data?.message || 'Registration failed'); }
  };
  const field = (name, props={}) => <input className="form-control" required value={form[name]} onChange={e => setForm({ ...form, [name]:e.target.value })} {...props} />;
  return <AuthShell title="Join myself" subtitle="Create your friend-finder profile">
    <form onSubmit={submit} className="auth-card discovery-register">
      {field('name',{placeholder:'Name'})}{field('username',{placeholder:'Username'})}<div className="location-fields"><input className="form-control" value={form.email} placeholder="Email (optional if mobile given)" type="email" onChange={e=>setForm({...form,email:e.target.value})}/><input className="form-control" value={form.mobile} placeholder="Mobile (optional if email given)" type="tel" onChange={e=>setForm({...form,mobile:e.target.value})}/></div>{field('password',{placeholder:'Password',type:'password'})}
      <select className="form-select" required value={form.gender} onChange={e => setForm({...form,gender:e.target.value})}><option value="">Select gender</option><option>Male</option><option>Female</option><option>Other</option></select>
      <button type="button" className="btn btn-outline-dark location-button" onClick={useLocation} disabled={locating}><i className="bi bi-crosshair" /> {locating ? 'Finding approximate location...' : 'Use my location to auto-fill'}</button>
      <small className="privacy-note"><i className="bi bi-shield-check" /> We use your location only to show nearby people. Exact location is never shown.</small>
      <div className="location-fields">{field('state',{placeholder:'State'})}{field('city',{placeholder:'City'})}</div>
      <label>Optional profile photo<input className="form-control" type="file" accept="image/jpeg,image/png,image/webp" onChange={e => setPhoto(e.target.files?.[0] || null)} /></label>
      <input className="form-control" placeholder="Interests: music, travel, books..." value={form.interests} onChange={e => setForm({...form,interests:e.target.value})} />
      <label>Date of birth<input className="form-control" required type="date" value={form.date_of_birth} onChange={e => setForm({...form,date_of_birth:e.target.value})} /></label>
      <label className="check-row"><input required type="checkbox" checked={form.confirm_adult} onChange={e => setForm({...form,confirm_adult:e.target.checked})} /> I confirm that I am 18 years of age or older.</label>
      {msg && <div className="alert alert-info">{msg}</div>}<button className="btn btn-dark w-100">Create account</button><Link to="/login">Already have account</Link>
    </form>
  </AuthShell>;
}
