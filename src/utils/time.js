export function timeAgo(value) {
  if (!value) return '';
  const seconds = Math.floor((Date.now() - new Date(value.replace(' ', 'T')).getTime()) / 1000);
  if (seconds < 60) return 'now';
  const units = [['y', 31536000], ['mo', 2592000], ['d', 86400], ['h', 3600], ['m', 60]];
  const unit = units.find(([, s]) => seconds >= s);
  return `${Math.floor(seconds / unit[1])}${unit[0]} ago`;
}

