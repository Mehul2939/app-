import { useAudioPlayer } from '../contexts/AudioPlayerContext';

export default function PostAudioButton({ post, src, label = 'Play audio' }) {
  const { currentAudio, isPlaying, playAudio, togglePlay } = useAudioPlayer();
  const active = currentAudio?.src === src;
  return <button type="button" className={`post-global-audio ${active ? 'active' : ''}`} onClick={() => active ? togglePlay() : playAudio({ src, storyId: null, title: post.meta_title || post.post_text?.slice(0, 60) || label, subtitle: `By ${post.name || 'myself user'}` })}>
    <i className={`bi ${active && isPlaying ? 'bi-pause-fill' : 'bi-play-fill'}`} /><span>{active && isPlaying ? 'Pause audio' : label}</span>
  </button>;
}

