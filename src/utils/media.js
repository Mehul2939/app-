export function mediaUrl(path) {
  if (!path) return '';
  if (/^https?:\/\//i.test(path)) return path;
  return `/app/${String(path).replace(/^\/+/, '')}`;
}

