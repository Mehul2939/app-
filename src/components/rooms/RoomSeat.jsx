import UserAvatar from '../UserAvatar';

export default function RoomSeat({ seat, canModerate, onRequest, onAction }) {
  const occupied = Boolean(seat.user_id);
  return <div className={`room-seat ${occupied ? 'occupied' : 'empty'} ${Number(seat.is_locked) ? 'locked' : ''} ${Number(seat.raised_hand) ? 'raised' : ''}`}>
    {occupied ? <>
      <div className={`seat-avatar ${Number(seat.mic_muted) ? '' : 'speaking'}`}><UserAvatar user={seat} disablePreview /><span><i className={`bi ${Number(seat.mic_muted) ? 'bi-mic-mute-fill' : 'bi-mic-fill'}`} /></span></div>
      <b>{seat.name || seat.username}</b>
      {canModerate && <div className="seat-tools"><button onClick={() => onAction('mic', seat.user_id, { muted: !Number(seat.mic_muted) })}><i className="bi bi-mic-mute" /></button><button onClick={() => onAction('remove_seat', seat.user_id)}><i className="bi bi-person-dash" /></button></div>}
    </> : <>
      <button className="empty-seat-button" disabled={Number(seat.is_locked)} onClick={() => onRequest(seat.seat_number)}><i className={`bi ${Number(seat.is_locked) ? 'bi-lock-fill' : 'bi-plus-lg'}`} /></button>
      <b>Seat {seat.seat_number}</b>
      <span>{Number(seat.is_locked) ? 'Locked' : 'Join Seat'}</span>
      {canModerate && <button className="seat-lock-button" onClick={() => onAction('seat_lock', 0, { seat_number: seat.seat_number, locked: !Number(seat.is_locked) })}>{Number(seat.is_locked) ? 'Unlock' : 'Lock'}</button>}
    </>}
  </div>;
}
