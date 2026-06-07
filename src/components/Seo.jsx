import { useEffect } from 'react';

function setMeta(name, content, attr = 'name') {
  let tag = document.head.querySelector(`meta[${attr}="${name}"]`);
  if (!tag) {
    tag = document.createElement('meta');
    tag.setAttribute(attr, name);
    document.head.appendChild(tag);
  }
  tag.setAttribute('content', content);
}

export default function Seo({ title = 'myself', description = 'myself social content platform', canonical, index = false, image }) {
  useEffect(() => {
    document.title = title;
    setMeta('description', description);
    setMeta('robots', index ? 'index, follow' : 'noindex, nofollow');
    setMeta('og:title', title, 'property');
    setMeta('og:description', description, 'property');
    setMeta('og:type', 'website', 'property');
    setMeta('twitter:card', image ? 'summary_large_image' : 'summary');
    setMeta('twitter:title', title);
    setMeta('twitter:description', description);
    if (image) {
      setMeta('og:image', image, 'property');
      setMeta('twitter:image', image);
    }
    if (canonical) {
      let link = document.head.querySelector('link[rel="canonical"]');
      if (!link) {
        link = document.createElement('link');
        link.setAttribute('rel', 'canonical');
        document.head.appendChild(link);
      }
      link.setAttribute('href', canonical);
    }
  }, [title, description, canonical, index, image]);
  return null;
}

