import { useAudioPlayer } from '../contexts/AudioPlayerContext';

export default function StoryAudioButton({ story, src }) {
  const { currentAudio, isPlaying, playAudio, togglePlay } = useAudioPlayer();
  const active = currentAudio?.src === src;
  const handleClick = () => active ? togglePlay() : playAudio({ src, storyId: story.id, title: story.title, subtitle: `By ${story.author_name}` });
  return <button type="button" className={`story-audio-trigger ${active ? 'active' : ''}`} onClick={handleClick}>
    <span><i className={`bi ${active && isPlaying ? 'bi-pause-circle-fill' : 'bi-play-circle-fill'}`} /></span>
    <div><b>{active && isPlaying ? 'Playing story audio' : 'Listen to this story'}</b><small>Playback continues while you browse</small></div>
  </button>;
}

