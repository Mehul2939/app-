import { Navigate, Route, Routes, useLocation, useParams } from 'react-router-dom';
import { useAuth } from './contexts/AuthContext';
import Navbar from './components/Navbar';
import BottomNavigation from './components/BottomNavigation';
import Home from './pages/Home';
import Login from './pages/Login';
import Register from './pages/Register';
import CreatePost from './pages/CreatePost';
import Profile from './pages/Profile';
import EditProfile from './pages/EditProfile';
import Search from './pages/Search';
import Wallet from './pages/Wallet';
import GiftStore from './pages/GiftStore';
import Notifications from './pages/Notifications';
import Messages from './pages/Messages';
import Chat from './pages/Chat';
import Admin from './pages/Admin';
import Placeholder from './pages/Placeholder';
import PostDetails from './pages/PostDetails';
import LegalPage from './pages/LegalPage';
import AgeGate from './components/AgeGate';
import Footer from './components/Footer';
import Friends from './pages/Friends';
import ProfilePreviewModal from './components/ProfilePreviewModal';
import Stories from './pages/Stories';
import StoryDetails from './pages/StoryDetails';
import AccessDenied from './pages/AccessDenied';
import GlobalAudioPlayer from './components/GlobalAudioPlayer';
import Rooms from './pages/Rooms';
import CreateRoom from './pages/CreateRoom';
import Room from './pages/Room';

function Private({ children }) {
  const { isLoggedIn } = useAuth();
  return isLoggedIn ? children : <Navigate to="/login" replace />;
}

export default function App() {
  const location = useLocation();
  const isChatScreen = location.pathname.startsWith('/chat/');
  const isAdminScreen = location.pathname.startsWith('/admin');
  const isLiveRoomScreen = /^\/rooms\/\d+/.test(location.pathname);
  return <>
    {!isAdminScreen && <AgeGate />}
    {!isAdminScreen && <ProfilePreviewModal />}
    {!isChatScreen && !isAdminScreen && !isLiveRoomScreen && <Navbar />}
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route path="/register" element={<Register />} />
      <Route path="/" element={<Private><Home /></Private>} />
      <Route path="/create" element={<Private><CreatePost /></Private>} />
      <Route path="/profile" element={<Private><Profile /></Private>} />
      <Route path="/profile/:username" element={<Profile />} />
      <Route path="/user/:username" element={<Profile />} />
      <Route path="/post/:slug" element={<PostDetails />} />
      <Route path="/stories" element={<Stories />} />
      <Route path="/stories/:slug" element={<StoryDetails />} />
      <Route path="/rooms" element={<Private><Rooms /></Private>} />
      <Route path="/rooms/create" element={<Private><CreateRoom /></Private>} />
      <Route path="/rooms/:roomId" element={<Private><Room /></Private>} />
      <Route path="/access-denied" element={<AccessDenied />} />
      <Route path="/edit-profile" element={<Private><EditProfile /></Private>} />
      <Route path="/profile/edit" element={<Private><EditProfile /></Private>} />
      <Route path="/search" element={<Private><Search /></Private>} />
      <Route path="/wallet" element={<Private><Wallet /></Private>} />
      <Route path="/gift-store" element={<Private><GiftStore /></Private>} />
      <Route path="/notifications" element={<Private><Notifications /></Private>} />
      <Route path="/messages" element={<Private><Messages /></Private>} />
      <Route path="/friends" element={<Private><Friends /></Private>} />
      <Route path="/chat/:userId" element={<Private><Chat /></Private>} />
      <Route path="/admin" element={<Admin />} />
      <Route path="/admin/login" element={<Admin screen="login" />} />
      <Route path="/admin/register" element={<Admin screen="register" />} />
      <Route path="/legal/:slug" element={<LegalRoute />} />
      <Route path="/followers" element={<Private><Placeholder title="Followers" /></Private>} />
      <Route path="/following" element={<Private><Placeholder title="Following" /></Private>} />
      <Route path="/saved-posts" element={<Private><Placeholder title="Saved Posts" /></Private>} />
    </Routes>
    {!isChatScreen && !isAdminScreen && !isLiveRoomScreen && <Footer />}
    {!isChatScreen && !isAdminScreen && !isLiveRoomScreen && <BottomNavigation />}
    {!isAdminScreen && <GlobalAudioPlayer />}
  </>;
}

function LegalRoute() {
  const { slug } = useParams();
  return <LegalPage slug={slug} />;
}
