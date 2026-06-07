import { useEffect, useState } from 'react';
import api from '../api/client';

export default function GiftModal({ receiverId }) {
  const [gifts, setGifts] = useState([]);
  const [message, setMessage] = useState('');
  const [status, setStatus] = useState('');
  useEffect(() => { api.get('/gift/list.php').then(({ data }) => setGifts(data.gifts || [])); }, []);
  const send = async (giftId) => {
    const { data } = await api.post('/gift/send.php', { receiver_id: receiverId, gift_id: giftId, message });
    setStatus(data.message);
  };
  return (
    <div className="gift-panel">
      <input className="form-control mb-2" value={message} onChange={(e) => setMessage(e.target.value)} placeholder="Gift message" />
      <div className="gift-grid">{gifts.map(g => (
        <button key={g.id} onClick={() => send(g.id)} className="gift-card">
          <span>{g.gift_icon}</span><strong>{g.gift_name}</strong><small>{g.price_coins} coins</small>
        </button>
      ))}</div>
      {status && <div className="alert alert-info mt-2">{status}</div>}
    </div>
  );
}

