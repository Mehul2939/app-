import { useEffect, useState } from 'react';
import UserAvatar from '../UserAvatar';

function formatTimer(seconds) {
  return `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
}

export default function ActiveCallModal({ call, phase, muted, speakerOn, startedAt, videoOn, remoteVideoOn, facingMode, localVideoRef, remoteVideoRef, localStream, remoteStream, onMute, onSpeaker, onVideo, onSwitchCamera, onEnd }) {
  const [elapsed, setElapsed] = useState(0);
  useEffect(() => {
    if (!startedAt) return undefined;
    const tick = () => setElapsed(Math.floor((Date.now() - startedAt) / 1000));
    tick();
    const timer = window.setInterval(tick, 1000);
    return () => window.clearInterval(timer);
  }, [startedAt]);
  useEffect(() => {
    if (!remoteVideoRef.current || !remoteStream) return;
    remoteVideoRef.current.srcObject = remoteStream;
    remoteVideoRef.current.play().catch(() => {});
  }, [remoteStream, remoteVideoOn, remoteVideoRef]);
  useEffect(() => {
    if (!localVideoRef.current || !localStream || !videoOn) return;
    localVideoRef.current.srcObject = localStream;
    localVideoRef.current.play().catch(() => {});
  }, [facingMode, localStream, localVideoRef, videoOn]);

  const label = phase === 'active' ? formatTimer(elapsed) : phase === 'ringing' ? 'Ringing...' : phase === 'calling' ? 'Calling...' : 'Connecting...';
  const hasVideoTheme = videoOn || remoteVideoOn;
  return (
    <div className={`call-overlay active-call-overlay ${hasVideoTheme ? 'video-call-theme' : ''}`} role="dialog" aria-modal="true" aria-label="Voice or video call">
      <section className={`call-card active-call-card ${hasVideoTheme ? 'has-video' : ''}`}>
        {remoteVideoOn && <video ref={remoteVideoRef} className="remote-call-video" autoPlay playsInline muted />}
        {videoOn && <video ref={localVideoRef} className={`local-call-video ${facingMode === 'user' ? 'mirror' : ''}`} autoPlay playsInline muted />}
        <div className="call-person">
        <span className="call-kicker">Voice call</span>
        <UserAvatar user={call.peer} size="call-avatar" disablePreview />
        <h2>{call.peer?.name || call.peer?.username || 'Friend'}</h2>
        <strong className="call-timer">{label}</strong>
        </div>
        <div className="active-call-actions">
          <button className={`call-round ${muted ? 'selected' : ''}`} onClick={onMute} disabled={phase !== 'active'}><i className={`bi ${muted ? 'bi-mic-mute-fill' : 'bi-mic-fill'}`} /><span>{muted ? 'Unmute' : 'Mute'}</span></button>
          <button className={`call-round ${speakerOn ? 'selected' : ''}`} onClick={onSpeaker} disabled={phase !== 'active'}><i className={`bi ${speakerOn ? 'bi-volume-up-fill' : 'bi-volume-mute-fill'}`} /><span>Speaker</span></button>
          <button className={`call-round ${videoOn ? 'selected' : ''}`} onClick={onVideo} disabled={phase !== 'active'}><i className={`bi ${videoOn ? 'bi-camera-video-fill' : 'bi-camera-video-off-fill'}`} /><span>{videoOn ? 'Video off' : 'Video'}</span></button>
          {videoOn && <button className="call-round" onClick={onSwitchCamera} disabled={phase !== 'active'}><i className="bi bi-arrow-repeat" /><span>Flip</span></button>}
          <button className="call-round danger" onClick={onEnd}><i className="bi bi-telephone-x-fill" /><span>End</span></button>
        </div>
      </section>
    </div>
  );
}
