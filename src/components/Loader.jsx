export default function Loader({ label = 'Loading' }) {
  return <div className="py-4 text-center text-secondary"><span className="spinner-border spinner-border-sm me-2" />{label}</div>;
}

