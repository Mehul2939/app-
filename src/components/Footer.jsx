import { Link } from 'react-router-dom';

const links = [
  ['About Us', 'about-us'],
  ['Contact Us', 'contact-us'],
  ['Privacy Policy', 'privacy-policy'],
  ['Terms of Service', 'terms-of-service'],
  ['Community Guidelines', 'community-guidelines'],
  ['Content Policy', 'content-policy'],
  ['Cookie Policy', 'cookie-policy'],
  ['Copyright Policy', 'copyright-policy'],
  ['DMCA Policy', 'dmca-policy'],
  ['Account Deletion Policy', 'account-deletion-policy'],
  ['18+ Policy', '18-plus-policy']
];

export default function Footer() {
  return <footer className="site-footer">{links.map(([label, slug]) => <Link key={slug} to={`/legal/${slug}`}>{label}</Link>)}</footer>;
}

