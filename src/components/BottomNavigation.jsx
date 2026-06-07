import { NavLink } from 'react-router-dom';

export default function BottomNavigation() {
  return (
    <nav className="bottom-nav">
      <NavLink to="/"><i className="bi bi-house-door" /><span>Home</span></NavLink>
      <NavLink to="/search"><i className="bi bi-search" /><span>Search</span></NavLink>
      <NavLink to="/rooms/create"><i className="bi bi-plus-square" /><span>Create Room</span></NavLink>
      <NavLink to="/rooms"><i className="bi bi-broadcast-pin" /><span>Rooms</span></NavLink>
      <NavLink to="/messages"><i className="bi bi-chat-dots" /><span>Messages</span></NavLink>
    </nav>
  );
}
