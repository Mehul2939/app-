import { Link, useNavigate } from 'react-router-dom';
import { useState } from 'react';
import { useAuth } from '../contexts/AuthContext';

export default function Login() {
  const { login } = useAuth();
  const nav = useNavigate();
  const [form, setForm] = useState({ login: '', password: '' });
  const [msg, setMsg] = useState('');
  const submit = async (e) => {
    e.preventDefault();
    try {
      const data = await login(form);
      if (data.success) nav('/');
      else setMsg(data.message);
    } catch (err) { setMsg(err.response?.data?.message || 'Login failed'); }
  };
  return <AuthShell title="Welcome back" subtitle="Login to myself">
    <form onSubmit={submit} className="auth-card">
      <input className="form-control" placeholder="Username, email or mobile" required autoComplete="username" onChange={e => setForm({ ...form, login: e.target.value })} />
      <input className="form-control" type="password" placeholder="Password" required autoComplete="current-password" onChange={e => setForm({ ...form, password: e.target.value })} />
      {msg && <div className="alert alert-danger">{msg}</div>}
      <button className="btn btn-dark w-100">Login</button>
      <Link to="/register">Create new account</Link>
      <a className="auth-separate-link" href="/app/admin/login">Admin login</a>
    </form>
  </AuthShell>;
}

export function AuthShell({ title, subtitle, children }) {
  return <main className="auth-page"><section><div className="brand-xl">myself</div><h1>{title}</h1><p>{subtitle}</p></section>{children}</main>;
}
