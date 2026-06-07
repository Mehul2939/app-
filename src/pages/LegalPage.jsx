import Seo from '../components/Seo';

const pages = {
  'privacy-policy': ['Privacy Policy', 'We explain what information myself collects, how it is used, how users can control their data, and how account deletion requests are handled.'],
  'terms-of-service': ['Terms of Service', 'By using myself, users agree to follow platform rules, respect other users, post lawful content, and comply with the 18+ age restriction.'],
  'community-guidelines': ['Community Guidelines', 'Users must not harass, exploit, threaten, impersonate, spam, or post illegal content. Reports are reviewed by admins.'],
  'content-policy': ['Content Policy', 'Public content must be lawful, properly owned by the uploader, and marked sensitive when appropriate. Private and deleted content is not indexed.'],
  'cookie-policy': ['Cookie Policy', 'myself uses essential cookies and local storage for login, age confirmation, preferences, and core platform functionality.'],
  'copyright-policy': ['Copyright Policy', 'Users must upload only content they own or have permission to use. Copyright complaints can be submitted through Contact Us.'],
  'dmca-policy': ['DMCA Policy', 'DMCA takedown requests should include the copyrighted work, infringing URL, contact details, good-faith statement, and signature.'],
  'about-us': ['About Us', 'myself is a social and content platform designed for public profiles, posts, gifts, wallet rewards, and community discovery.'],
  'contact-us': ['Contact Us', 'For support, legal requests, privacy questions, or copyright notices, contact the site administrator using the hosting contact email.'],
  'account-deletion-policy': ['Account Deletion Policy', 'Users can request account deletion from settings. The account is disabled and a data removal workflow is recorded.'],
  'refund-policy': ['Refund Policy', 'Coin and gift refunds depend on admin review. If paid coin purchases are enabled, payment provider terms may also apply.'],
  '18-plus-policy': ['18+ Age Restriction Policy', 'myself is available only to users aged 18 years or older. Registration requires date of birth and explicit age confirmation.']
};

export default function LegalPage({ slug }) {
  const [title, body] = pages[slug] || pages['about-us'];
  return <main className="legal-page">
    <Seo title={`${title} - myself`} description={body} canonical={`${location.origin}/app/legal/${slug}`} index />
    <article className="panel legal-card">
      <h1>{title}</h1>
      <p>{body}</p>
      <h2>Platform commitments</h2>
      <p>Public pages use indexable metadata, private areas use noindex rules, uploaded media is validated, and sensitive content controls are available for safer browsing.</p>
      <h3>User responsibility</h3>
      <p>Users are responsible for lawful behavior, accurate account details, respectful participation, and marking adult or sensitive content when needed.</p>
    </article>
  </main>;
}

