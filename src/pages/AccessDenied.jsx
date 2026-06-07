import { Link } from 'react-router-dom';
export default function AccessDenied() {
  return <main className="auth-page"><div><div className="brand-xl">18+</div><h1>Access denied</h1><p>This section is only available to adults aged 18 or older.</p><Link className="btn btn-dark" to="/">Return home</Link></div></main>;
}

