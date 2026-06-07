const iceServers = [{ urls: 'stun:stun.l.google.com:19302' }];
if (import.meta.env.VITE_TURN_URL) {
  iceServers.push({
    urls: import.meta.env.VITE_TURN_URL,
    username: import.meta.env.VITE_TURN_USERNAME || '',
    credential: import.meta.env.VITE_TURN_CREDENTIAL || '',
  });
}

export const WEBRTC_CONFIG = { iceServers };

export async function getAudioStream() {
  if (!navigator.mediaDevices?.getUserMedia) {
    throw new Error('Audio/video calls are not supported in this browser.');
  }
  return navigator.mediaDevices.getUserMedia({
    audio: {
      echoCancellation: true,
      noiseSuppression: true,
      autoGainControl: true,
    },
    video: false,
  });
}

export async function getCameraStream(facingMode = 'user') {
  if (!navigator.mediaDevices?.getUserMedia) {
    throw new Error('Camera is not supported in this browser.');
  }
  return navigator.mediaDevices.getUserMedia({
    audio: false,
    video: {
      facingMode: { ideal: facingMode },
      width: { ideal: 1280 },
      height: { ideal: 720 },
    },
  });
}

export function createCallPeer({ localStream, onIceCandidate, onRemoteStream, onConnectionState }) {
  const peer = new RTCPeerConnection(WEBRTC_CONFIG);
  localStream.getTracks().forEach((track) => peer.addTrack(track, localStream));
  peer.onicecandidate = (event) => {
    if (event.candidate) onIceCandidate(event.candidate);
  };
  peer.ontrack = (event) => onRemoteStream(event.streams[0]);
  peer.onconnectionstatechange = () => onConnectionState(peer.connectionState);
  return peer;
}

export function stopMedia(stream) {
  stream?.getTracks().forEach((track) => track.stop());
}
