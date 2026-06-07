import { useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

export default function AgeGate() {
  const { isLoggedIn } = useAuth();
  const location = useLocation();
  const navigate = useNavigate();
  const [accepted, setAccepted] = useState(() => localStorage.getItem('myself_age_gate') === 'accepted');
  const isStoryDetail = /^\/stories\/[^/]+/.test(location.pathname);
  if (accepted || isLoggedIn || !isStoryDetail) return null;
  const accept = () => {
    localStorage.setItem('myself_age_gate', 'accepted');
    setAccepted(true);
  };
  return (
    <div className="age-gate" role="dialog" aria-modal="true" aria-labelledby="ageGateTitle">
      <div className="age-card">
        <div className="brand-mark">m</div>
        <h2 id="ageGateTitle">18+ age confirmation</h2>
        <p>This platform may contain content intended for adults (18+). By continuing, you confirm that you are at least 18 years old and agree to our Terms of Service and Privacy Policy.</p>
        <button className="btn btn-dark w-100" onClick={accept}>Yes, I am 18+</button>
        <button className="btn btn-outline-danger w-100" onClick={() => navigate('/access-denied', { replace: true })}>No, I am under 18</button>
        <a href="/app/public/legal/18-plus-policy">Read 18+ policy</a>
      </div>
    </div>
  );
}
