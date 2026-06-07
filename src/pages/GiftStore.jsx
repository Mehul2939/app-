import { useEffect, useState } from 'react';
import api from '../api/client';

export default function GiftStore() {
  const [gifts, setGifts] = useState([]);
  useEffect(() => { api.get('/gift/list.php').then(({ data }) => setGifts(data.gifts || [])); }, []);
  return <main className="narrow-page"><section className="panel"><h2>Gift Store</h2><div className="gift-grid">{gifts.map(g => <div className="gift-card" key={g.id}><span>{g.gift_icon}</span><strong>{g.gift_name}</strong><small>{g.price_coins} coins</small></div>)}</div></section></main>;
}

