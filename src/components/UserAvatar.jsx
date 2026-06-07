import { mediaUrl } from '../utils/media';

export default function UserAvatar({ user, size = '', label, disablePreview = false }) {
  const photo = user?.profile_photo || user?.sender_photo;
  const name = label || user?.name || user?.sender_name || user?.username || 'User';
  const openPreview = (event) => {
    if (disablePreview) return;
    const username = user?.username || user?.sender_username;
    if (!username) return;
    event.preventDefault();
    event.stopPropagation();
    window.dispatchEvent(new CustomEvent('myself:profile-preview', { detail: { ...user, username } }));
  };
  if (photo) {
    return <span role={disablePreview ? undefined : 'button'} tabIndex={disablePreview ? undefined : 0} className={`avatar avatar-button ${size}`} onClick={openPreview} aria-label={`${disablePreview ? '' : 'Preview '}${name} profile`}><img className="avatar-img-inner" src={mediaUrl(photo)} alt={`${name} profile photo`} loading="lazy" /></span>;
  }
  return <span role={disablePreview ? undefined : 'button'} tabIndex={disablePreview ? undefined : 0} className={`avatar avatar-button ${size}`} onClick={openPreview} aria-label={`${disablePreview ? '' : 'Preview '}${name} profile`}>{name.slice(0, 1)}</span>;
}
