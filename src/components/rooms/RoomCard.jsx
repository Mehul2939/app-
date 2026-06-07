import { Link } from 'react-router-dom';
import { mediaUrl } from '../../utils/media';
import UserAvatar from '../UserAvatar';

export default function RoomCard({ room, onFollow }) {
  return <article className={`room-card room-theme-${room.theme}`}>
    <Link to={`/rooms/${room.id}`} className="room-cover">
      {room.cover_image ? <img src={mediaUrl(room.cover_image)} alt="" /> : <i className={`bi ${room.room_type === 'video' ? 'bi-play-btn-fill' : 'bi-mic-fill'}`} />}
      <span className="room-live"><i className="bi bi-broadcast" /> {room.active_users} live</span>
    </Link>
    <div className="room-card-body">
      <div className="room-badges"><span>{room.room_type === 'video' ? 'Video Watch Room' : 'Voice Room'}</span><span>{room.category}</span>{Number(room.is_locked) === 1 && <i className="bi bi-lock-fill" />}</div>
      <h2><Link to={`/rooms/${room.id}`}>{room.title}</Link></h2>
      <div className="room-host"><UserAvatar user={{ name: room.owner_name, username: room.owner_username, profile_photo: room.owner_photo }} disablePreview /><div><b>{room.owner_name || room.owner_username}</b><span>Host</span></div><button className="btn btn-sm btn-outline-light" onClick={() => onFollow(room)} disabled={Number(room.following_host) === 1}>{Number(room.following_host) === 1 ? 'Following' : 'Follow'}</button></div>
    </div>
  </article>;
}
