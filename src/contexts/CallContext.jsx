import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { io } from 'socket.io-client';
import api from '../api/client';
import { useAuth } from './AuthContext';
import { createCallPeer, getAudioStream, getCameraStream, stopMedia } from '../services/webrtc';
import IncomingCallModal from '../components/calls/IncomingCallModal';
import ActiveCallModal from '../components/calls/ActiveCallModal';

const CallContext = createContext(null);
const SIGNALING_URL = import.meta.env.VITE_CALL_SIGNALING_URL || `${window.location.protocol}//${window.location.hostname}:3001`;

export function CallProvider({ children }) {
  const { token } = useAuth();
  const socketRef = useRef(null);
  const peerRef = useRef(null);
  const streamRef = useRef(null);
  const pendingOfferRef = useRef(null);
  const answerRequestedRef = useRef(false);
  const iceQueueRef = useRef([]);
  const remoteAudioRef = useRef(null);
  const remoteVideoRef = useRef(null);
  const localVideoRef = useRef(null);
  const remoteStreamRef = useRef(null);
  const videoSenderRef = useRef(null);
  const callRef = useRef(null);
  const [call, setCall] = useState(null);
  const [phase, setPhase] = useState('idle');
  const [notice, setNotice] = useState('');
  const [muted, setMuted] = useState(false);
  const [speakerOn, setSpeakerOn] = useState(true);
  const [startedAt, setStartedAt] = useState(null);
  const [videoOn, setVideoOn] = useState(false);
  const [remoteVideoOn, setRemoteVideoOn] = useState(false);
  const [facingMode, setFacingMode] = useState('user');
  const [remoteStream, setRemoteStream] = useState(null);

  const updateCall = useCallback((nextCall) => {
    callRef.current = nextCall;
    setCall(nextCall);
  }, []);

  const cleanup = useCallback((message = '') => {
    peerRef.current?.close();
    peerRef.current = null;
    stopMedia(streamRef.current);
    streamRef.current = null;
    pendingOfferRef.current = null;
    answerRequestedRef.current = false;
    iceQueueRef.current = [];
    if (remoteAudioRef.current) remoteAudioRef.current.srcObject = null;
    if (remoteVideoRef.current) remoteVideoRef.current.srcObject = null;
    if (localVideoRef.current) localVideoRef.current.srcObject = null;
    remoteStreamRef.current = null;
    videoSenderRef.current = null;
    updateCall(null);
    setPhase('idle');
    setMuted(false);
    setSpeakerOn(true);
    setStartedAt(null);
    setVideoOn(false);
    setRemoteVideoOn(false);
    setFacingMode('user');
    setRemoteStream(null);
    if (message) {
      setNotice(message);
      window.setTimeout(() => setNotice(''), 4500);
    }
  }, [updateCall]);

  const addQueuedIce = useCallback(async () => {
    if (!peerRef.current?.remoteDescription) return;
    const queued = iceQueueRef.current.splice(0);
    for (const candidate of queued) await peerRef.current.addIceCandidate(candidate).catch(() => {});
  }, []);

  const ensurePeer = useCallback(async (callId) => {
    if (peerRef.current) return peerRef.current;
    const stream = await getAudioStream();
    streamRef.current = stream;
    const peer = createCallPeer({
      localStream: stream,
      onIceCandidate: (candidate) => socketRef.current?.emit('call:ice-candidate', { callId, candidate }),
      onRemoteStream: (remoteStream) => {
        remoteStreamRef.current = remoteStream;
        setRemoteStream(remoteStream);
        if (remoteAudioRef.current) {
          remoteAudioRef.current.srcObject = remoteStream;
          remoteAudioRef.current.play().catch(() => {});
        }
        if (remoteVideoRef.current) {
          remoteVideoRef.current.srcObject = remoteStream;
          remoteVideoRef.current.play().catch(() => {});
        }
      },
      onConnectionState: (state) => {
        if (state === 'connected') {
          setPhase('active');
          setStartedAt((value) => value || Date.now());
        }
        if (['failed', 'closed'].includes(state) && callRef.current) cleanup('Call ended');
      },
    });
    peerRef.current = peer;
    return peer;
  }, [cleanup]);

  const completeAnswer = useCallback(async (current, offer) => {
    try {
      answerRequestedRef.current = false;
      setPhase('connecting');
      const peer = await ensurePeer(current.callId);
      await peer.setRemoteDescription(offer);
      await addQueuedIce();
      const answer = await peer.createAnswer();
      await peer.setLocalDescription(answer);
      socketRef.current?.emit('call:accept', { callId: current.callId });
      socketRef.current?.emit('call:answer', { callId: current.callId, answer });
      setPhase('active');
      setStartedAt(Date.now());
    } catch (error) {
      socketRef.current?.emit('call:reject', { callId: current.callId });
      cleanup(error.message || 'Microphone permission is required');
    }
  }, [addQueuedIce, cleanup, ensurePeer]);

  useEffect(() => {
    if (!token) {
      socketRef.current?.disconnect();
      cleanup();
      return undefined;
    }
    const socket = io(SIGNALING_URL, { auth: { token }, transports: ['websocket', 'polling'] });
    socketRef.current = socket;
    socket.on('connect_error', (error) => setNotice(error.message === 'xhr poll error' ? 'Call service is unavailable' : error.message));
    socket.on('call:request', ({ callId, caller }) => {
      updateCall({ callId, peer: caller, direction: 'incoming' });
      setPhase('incoming');
    });
    socket.on('call:ringing', async ({ callId }) => {
      const current = callRef.current;
      if (!current || current.callId !== callId) return;
      setPhase('ringing');
      try {
        const peer = await ensurePeer(callId);
        const offer = await peer.createOffer();
        await peer.setLocalDescription(offer);
        socket.emit('call:offer', { callId, offer });
      } catch (error) {
        socket.emit('call:end', { callId });
        cleanup(error.message || 'Microphone permission is required');
      }
    });
    socket.on('call:offer', ({ callId, offer }) => {
      if (callRef.current?.callId !== callId) return;
      if (peerRef.current?.remoteDescription) {
        peerRef.current.setRemoteDescription(offer)
          .then(addQueuedIce)
          .then(() => peerRef.current.createAnswer())
          .then((answer) => peerRef.current.setLocalDescription(answer).then(() => answer))
          .then((answer) => socket.emit('call:answer', { callId, answer }))
          .catch(() => setNotice('Unable to update video call'));
        return;
      }
      pendingOfferRef.current = offer;
      if (answerRequestedRef.current) completeAnswer(callRef.current, offer);
    });
    socket.on('call:accept', ({ callId }) => {
      if (callRef.current?.callId === callId && callRef.current.direction === 'outgoing') setPhase('connecting');
    });
    socket.on('call:answer', async ({ callId, answer }) => {
      if (callRef.current?.callId !== callId || !peerRef.current) return;
      await peerRef.current.setRemoteDescription(answer);
      await addQueuedIce();
      setPhase('active');
      setStartedAt(Date.now());
    });
    socket.on('call:ice-candidate', async ({ callId, candidate }) => {
      if (callRef.current?.callId !== callId || !candidate) return;
      if (peerRef.current?.remoteDescription) await peerRef.current.addIceCandidate(candidate).catch(() => {});
      else iceQueueRef.current.push(candidate);
    });
    socket.on('call:media-state', ({ callId, videoOn: peerVideoOn }) => {
      if (callRef.current?.callId !== callId) return;
      setRemoteVideoOn(Boolean(peerVideoOn));
    });
    socket.on('call:reject', ({ callId, message }) => {
      if (callRef.current?.callId === callId) cleanup(message || 'Call rejected');
    });
    socket.on('call:missed', ({ callId }) => {
      if (callRef.current?.callId === callId) cleanup('Missed voice call');
    });
    socket.on('call:end', ({ callId, message }) => {
      if (callRef.current?.callId === callId) cleanup(message || 'Call ended');
    });
    return () => {
      socket.removeAllListeners();
      socket.disconnect();
      socketRef.current = null;
      cleanup();
    };
  }, [token, addQueuedIce, cleanup, completeAnswer, ensurePeer, updateCall]);

  const startCall = useCallback(async (peer) => {
    if (!peer?.id || phase !== 'idle') return;
    try {
      const { data } = await api.get(`/call/friendship.php?user_id=${peer.id}`);
      if (!data.can_call) throw new Error('Only mutual friends can call each other.');
      setPhase('calling');
      updateCall({ callId: null, peer: data.peer || peer, direction: 'outgoing' });
      socketRef.current?.emit('call:request', { receiverId: Number(peer.id) }, (result) => {
        if (!result?.ok) return cleanup(result?.message || 'Unable to start call');
        updateCall({ callId: result.callId, peer: data.peer || peer, direction: 'outgoing' });
      });
    } catch (error) {
      cleanup(error.response?.data?.message || error.message || 'Unable to start call');
    }
  }, [cleanup, phase, updateCall]);

  const answerCall = useCallback(async () => {
    const current = callRef.current;
    if (!current?.callId) return;
    if (!pendingOfferRef.current) {
      answerRequestedRef.current = true;
      setPhase('connecting');
      setNotice('Connecting to caller...');
      return;
    }
    completeAnswer(current, pendingOfferRef.current);
  }, [completeAnswer]);

  const rejectCall = useCallback(() => {
    const current = callRef.current;
    if (current?.callId) socketRef.current?.emit('call:reject', { callId: current.callId });
    cleanup();
  }, [cleanup]);

  const endCall = useCallback(() => {
    const current = callRef.current;
    if (current?.callId) socketRef.current?.emit('call:end', { callId: current.callId });
    cleanup('Call ended');
  }, [cleanup]);

  const toggleMute = useCallback(() => {
    const track = streamRef.current?.getAudioTracks()[0];
    if (!track) return;
    track.enabled = !track.enabled;
    setMuted(!track.enabled);
  }, []);

  const toggleSpeaker = useCallback(() => {
    if (!remoteAudioRef.current) return;
    remoteAudioRef.current.muted = speakerOn;
    setSpeakerOn(!speakerOn);
  }, [speakerOn]);

  const renegotiate = useCallback(async () => {
    const current = callRef.current;
    const peer = peerRef.current;
    if (!current?.callId || !peer) return;
    const offer = await peer.createOffer();
    await peer.setLocalDescription(offer);
    socketRef.current?.emit('call:offer', { callId: current.callId, offer });
  }, []);

  const toggleVideo = useCallback(async () => {
    const current = callRef.current;
    if (!current?.callId || phase !== 'active') return;
    try {
      if (videoOn) {
        const track = streamRef.current?.getVideoTracks()[0];
        await videoSenderRef.current?.replaceTrack(null);
        track?.stop();
        if (track) streamRef.current?.removeTrack(track);
        if (localVideoRef.current) localVideoRef.current.srcObject = null;
        setVideoOn(false);
        socketRef.current?.emit('call:media-state', { callId: current.callId, videoOn: false, facingMode });
        return;
      }
      const camera = await getCameraStream(facingMode);
      const track = camera.getVideoTracks()[0];
      streamRef.current?.addTrack(track);
      if (videoSenderRef.current) {
        await videoSenderRef.current.replaceTrack(track);
      } else {
        videoSenderRef.current = peerRef.current.addTrack(track, streamRef.current);
        await renegotiate();
      }
      if (localVideoRef.current) {
        localVideoRef.current.srcObject = new MediaStream([track]);
        localVideoRef.current.play().catch(() => {});
      }
      setVideoOn(true);
      socketRef.current?.emit('call:media-state', { callId: current.callId, videoOn: true, facingMode });
    } catch (error) {
      setNotice(error.message || 'Camera permission is required');
    }
  }, [facingMode, phase, renegotiate, videoOn]);

  const switchCamera = useCallback(async () => {
    if (!videoOn || phase !== 'active') return;
    const nextMode = facingMode === 'user' ? 'environment' : 'user';
    try {
      const camera = await getCameraStream(nextMode);
      const nextTrack = camera.getVideoTracks()[0];
      const oldTrack = streamRef.current?.getVideoTracks()[0];
      await videoSenderRef.current?.replaceTrack(nextTrack);
      oldTrack?.stop();
      if (oldTrack) streamRef.current?.removeTrack(oldTrack);
      streamRef.current?.addTrack(nextTrack);
      if (localVideoRef.current) {
        localVideoRef.current.srcObject = new MediaStream([nextTrack]);
        localVideoRef.current.play().catch(() => {});
      }
      setFacingMode(nextMode);
      socketRef.current?.emit('call:media-state', { callId: callRef.current.callId, videoOn: true, facingMode: nextMode });
    } catch (error) {
      setNotice(error.message || 'Unable to switch camera');
    }
  }, [facingMode, phase, videoOn]);

  const value = useMemo(() => ({ startCall, phase, call }), [startCall, phase, call]);
  return (
    <CallContext.Provider value={value}>
      {children}
      <audio ref={remoteAudioRef} autoPlay playsInline />
      {notice && <div className="call-notice" role="status">{notice}</div>}
      {phase === 'incoming' && call && <IncomingCallModal call={call} onAnswer={answerCall} onReject={rejectCall} />}
      {call && ['calling', 'ringing', 'connecting', 'active'].includes(phase) && (
        <ActiveCallModal call={call} phase={phase} muted={muted} speakerOn={speakerOn} startedAt={startedAt}
          videoOn={videoOn} remoteVideoOn={remoteVideoOn} facingMode={facingMode} localVideoRef={localVideoRef} remoteVideoRef={remoteVideoRef}
          localStream={streamRef.current} remoteStream={remoteStream} onMute={toggleMute} onSpeaker={toggleSpeaker} onVideo={toggleVideo} onSwitchCamera={switchCamera} onEnd={endCall} />
      )}
    </CallContext.Provider>
  );
}

export function useCall() {
  const context = useContext(CallContext);
  if (!context) throw new Error('useCall must be used inside CallProvider');
  return context;
}
