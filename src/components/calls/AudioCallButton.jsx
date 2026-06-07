import { useEffect, useState } from 'react';
import api from '../../api/client';
import { useCall } from '../../contexts/CallContext';

export default function AudioCallButton({ peer }) {
  const { startCall, phase } = useCall();
  const [canCall, setCanCall] = useState(false);

  useEffect(() => {
    if (!peer?.id) return;
    api.get(`/call/friendship.php?user_id=${peer.id}`)
      .then(({ data }) => setCanCall(Boolean(data.can_call)))
      .catch(() => setCanCall(false));
  }, [peer?.id]);

  if (!canCall) return null;
  return (
    <button type="button" className="audio-call-button" onClick={() => startCall(peer)} disabled={phase !== 'idle'} aria-label={`Audio call ${peer.name || peer.username}`}>
      <i className="bi bi-telephone-fill" />
    </button>
  );
}
