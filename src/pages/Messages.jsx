import { Link } from 'react-router-dom';
import ChatUserList from '../components/ChatUserList';

export default function Messages() {
  return (
    <main className="messages-page">
      <section className="chat-list-panel">
        <div className="chat-list-head">
          <h1>Messages</h1>
          <Link className="btn btn-sm btn-dark" to="/search">Find friends</Link>
        </div>
        <ChatUserList />
      </section>
    </main>
  );
}
