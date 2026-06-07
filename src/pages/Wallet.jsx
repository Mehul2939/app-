import { useEffect, useState } from 'react';
import api from '../api/client';

export default function Wallet() {
  const [wallet, setWallet] = useState(null);
  const [history, setHistory] = useState([]);
  const [withdrawals, setWithdrawals] = useState([]);
  const [form, setForm] = useState({ coins: '', upi_id: '', contact_number: '', account_holder_name: '' });
  const [msg, setMsg] = useState('');

  const load = async () => {
    const b = await api.get('/wallet/balance.php');
    const h = await api.get('/wallet/history.php');
    const w = await api.get('/wallet/withdrawal_history.php');
    setWallet(b.data);
    setHistory(h.data.transactions || []);
    setWithdrawals(w.data.withdrawals || []);
  };

  useEffect(() => { load(); }, []);
  const claim = async () => { await api.post('/wallet/claim_reward.php'); load(); };
  const submitWithdrawal = async (event) => {
    event.preventDefault();
    try {
      const { data } = await api.post('/wallet/withdrawal_request.php', form);
      setMsg(data.message);
      setForm({ coins: '', upi_id: '', contact_number: '', account_holder_name: '' });
      load();
    } catch (err) {
      setMsg(err.response?.data?.message || 'Withdrawal failed');
    }
  };
  if (!wallet) return null;

  return <main className="wallet-page">
    <section className="wallet-card">
      <span>myself wallet</span><strong>{wallet.wallet.coins_balance}</strong><small>coins available · 100 coins = INR 49</small>
      <button className="btn btn-light" disabled={wallet.claimed_today} onClick={claim}>{wallet.claimed_today ? 'Claimed today' : `Claim ${wallet.next_reward} coins`}</button>
    </section>
    <section className="earnings-grid">
      <div><span>Pending Coins</span><b>{wallet.pending_coins}</b></div>
      <div><span>Withdrawable</span><b>{wallet.withdrawable_coins}</b></div>
      <div><span>Total Earnings</span><b>INR {wallet.total_earnings_inr}</b></div>
    </section>
    <section className="panel">
      <h3>Withdraw Coins</h3>
      <form onSubmit={submitWithdrawal} className="withdraw-form">
        <input className="form-control" placeholder="Coins" value={form.coins} onChange={e => setForm({ ...form, coins: e.target.value })} />
        <input className="form-control" placeholder="UPI ID" value={form.upi_id} onChange={e => setForm({ ...form, upi_id: e.target.value })} />
        <input className="form-control" placeholder="Contact Number" value={form.contact_number} onChange={e => setForm({ ...form, contact_number: e.target.value })} />
        <input className="form-control" placeholder="Account Holder Name" value={form.account_holder_name} onChange={e => setForm({ ...form, account_holder_name: e.target.value })} />
        <button className="btn btn-dark">Submit Withdrawal</button>
      </form>
      {msg && <div className="alert alert-info">{msg}</div>}
    </section>
    <section className="panel"><h3>Withdrawal History</h3>{withdrawals.map(w => <div className="history-row" key={w.id}><span>{w.status} · INR {w.amount_inr}</span><b>{w.coins}</b><small>ETA {w.estimated_payout_date || '-'} · {w.created_at}</small></div>)}</section>
    <section className="panel"><h3>Coin History</h3>{history.map(t => <div className="history-row" key={t.id}><span>{t.type}</span><b>{t.coins}</b><small>{t.created_at}</small></div>)}</section>
  </main>;
}
