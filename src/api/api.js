import api, { API_BASE } from './client';

export { API_BASE };

export const addComment = (payload) => api.post('/add_comment.php', payload);
export const getComments = (postId) => api.get(`/get_comments.php?post_id=${postId}`);
export const editComment = (payload) => api.post('/edit_comment.php', payload);
export const deleteComment = (commentId) => api.post('/delete_comment.php', { comment_id: commentId });
export const likeComment = (commentId) => api.post('/comment/like.php', { comment_id: commentId });
export const heartbeat = () => api.post('/heartbeat.php', {});
export const getUserStatus = (userId) => api.get(`/get_user_status.php?user_id=${userId}`);
export const getChatUsers = () => api.get('/get_chat_users.php');

export default api;

