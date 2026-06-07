import { useAudioPlayer } from '../contexts/AudioPlayerContext';

const formatTime = (seconds) => {
  if (!Number.isFinite(seconds)) return '0:00';
  const minutes = Math.floor(seconds / 60);
  return `${minutes}:${String(Math.floor(seconds % 60)).padStart(2, '0')}`;
};

export default function GlobalAudioPlayer() {
  const { currentAudio, isPlaying, duration, currentTime, togglePlay, seek, closeAudio } = useAudioPlayer();
  if (!currentAudio) return null;
  const progress = duration > 0 ? Math.min(100, (currentTime / duration) * 100) : 0;
  const radius = 27;
  const circumference = 2 * Math.PI * radius;

  return <aside className="global-audio-player" aria-label="Global audio player">
    <button className="audio-circle" onClick={togglePlay} aria-label={isPlaying ? 'Pause audio' : 'Play audio'}>
      <svg viewBox="0 0 64 64" aria-hidden="true">
        <circle className="audio-track" cx="32" cy="32" r={radius} />
        <circle className="audio-progress" cx="32" cy="32" r={radius} strokeDasharray={circumference} strokeDashoffset={circumference - (progress / 100) * circumference} />
      </svg>
      <i className={`bi ${isPlaying ? 'bi-pause-fill' : 'bi-play-fill'}`} />
    </button>
    <button className="audio-info" onClick={() => seek(currentTime + 10)} title="Skip forward 10 seconds">
      <strong>{currentAudio.title || 'Now playing'}</strong>
      <span>{currentAudio.subtitle || 'Audio'} · {formatTime(currentTime)} / {formatTime(duration)}</span>
    </button>
    <input className="audio-seek" type="range" min="0" max={duration || 0} step="0.1" value={currentTime} onChange={event => seek(Number(event.target.value))} aria-label="Audio timeline" />
    <button className="audio-close" onClick={closeAudio} aria-label="Stop and close audio"><i className="bi bi-x-lg" /></button>
  </aside>;
}

