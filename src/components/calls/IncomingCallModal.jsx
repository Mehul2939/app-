import { useEffect } from 'react';
import UserAvatar from '../UserAvatar';

export default function IncomingCallModal({ call, onAnswer, onReject }) {
  useEffect(() => {
    let context;
    let timer;
    const ring = () => {
      context ||= new AudioContext();
      const oscillator = context.createOscillator();
      const gain = context.createGain();
      oscillator.frequency.value = 740;
      gain.gain.setValueAtTime(0.0001, context.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.12, context.currentTime + 0.03);
      gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + 0.65);
      oscillator.connect(gain).connect(context.destination);
      oscillator.start();
      oscillator.stop(context.currentTime + 0.7);
    };
    ring();
    timer = window.setInterval(ring, 1600);
    return () => {
      window.clearInterval(timer);
      context?.close();
    };
  }, []);

  return (
    <div className="call-overlay incoming-call-overlay" role="dialog" aria-modal="true" aria-label="Incoming voice call">
      <section className="call-card incoming-call-card">
        <span className="call-kicker">Incoming voice call</span>
        <UserAvatar user={call.peer} size="call-avatar" disablePreview />
        <h2>{call.peer?.name || call.peer?.username || 'Friend'}</h2>
        <p>is calling you...</p>
        <div className="incoming-call-actions">
          <button className="call-round danger" onClick={onReject}><i className="bi bi-telephone-x-fill" /><span>Reject</span></button>
          <button className="call-round answer" onClick={onAnswer}><i className="bi bi-telephone-fill" /><span>Answer</span></button>
        </div>
      </section>
    </div>
  );
}
