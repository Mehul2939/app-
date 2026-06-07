import { createContext, useContext, useEffect, useMemo, useRef, useState } from 'react';

const AudioPlayerContext = createContext(null);

export function AudioPlayerProvider({ children }) {
  const audioRef = useRef(null);
  const [currentAudio, setCurrentAudio] = useState(null);
  const [isPlaying, setIsPlaying] = useState(false);
  const [duration, setDuration] = useState(0);
  const [currentTime, setCurrentTime] = useState(0);

  useEffect(() => {
    const audio = new Audio();
    audio.preload = 'metadata';
    audioRef.current = audio;
    const updateTime = () => setCurrentTime(audio.currentTime || 0);
    const updateDuration = () => setDuration(Number.isFinite(audio.duration) ? audio.duration : 0);
    const markPlaying = () => setIsPlaying(true);
    const markPaused = () => setIsPlaying(false);
    const ended = () => { setIsPlaying(false); setCurrentTime(0); };
    audio.addEventListener('timeupdate', updateTime);
    audio.addEventListener('loadedmetadata', updateDuration);
    audio.addEventListener('durationchange', updateDuration);
    audio.addEventListener('play', markPlaying);
    audio.addEventListener('pause', markPaused);
    audio.addEventListener('ended', ended);
    return () => {
      audio.pause();
      audio.removeEventListener('timeupdate', updateTime);
      audio.removeEventListener('loadedmetadata', updateDuration);
      audio.removeEventListener('durationchange', updateDuration);
      audio.removeEventListener('play', markPlaying);
      audio.removeEventListener('pause', markPaused);
      audio.removeEventListener('ended', ended);
    };
  }, []);

  const playAudio = async (audioItem) => {
    const audio = audioRef.current;
    if (!audio || !audioItem?.src) return;
    if (currentAudio?.src !== audioItem.src) {
      audio.src = audioItem.src;
      audio.currentTime = 0;
      setCurrentTime(0);
      setDuration(0);
      setCurrentAudio(audioItem);
    }
    try { await audio.play(); } catch {}
  };

  const togglePlay = async () => {
    const audio = audioRef.current;
    if (!audio || !currentAudio) return;
    if (audio.paused) {
      try { await audio.play(); } catch {}
    } else {
      audio.pause();
    }
  };

  const seek = (seconds) => {
    const audio = audioRef.current;
    if (!audio) return;
    audio.currentTime = Math.max(0, Math.min(seconds, duration || 0));
    setCurrentTime(audio.currentTime);
  };

  const closeAudio = () => {
    const audio = audioRef.current;
    if (audio) { audio.pause(); audio.removeAttribute('src'); audio.load(); }
    setCurrentAudio(null);
    setIsPlaying(false);
    setCurrentTime(0);
    setDuration(0);
  };

  const value = useMemo(() => ({
    currentAudio,
    storyId: currentAudio?.storyId || null,
    isPlaying,
    duration,
    currentTime,
    playAudio,
    togglePlay,
    seek,
    closeAudio
  }), [currentAudio, isPlaying, duration, currentTime]);

  return <AudioPlayerContext.Provider value={value}>{children}</AudioPlayerContext.Provider>;
}

export const useAudioPlayer = () => useContext(AudioPlayerContext);

