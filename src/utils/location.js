export async function requestApproximateLocation() {
  if (!navigator.geolocation) throw new Error('Location is not supported by this browser.');
  const position = await new Promise((resolve, reject) => navigator.geolocation.getCurrentPosition(resolve, reject, {
    enableHighAccuracy: false,
    timeout: 12000,
    maximumAge: 10 * 60 * 1000
  }));
  const latitude = Number(position.coords.latitude.toFixed(6));
  const longitude = Number(position.coords.longitude.toFixed(6));
  let city = '';
  let state = '';
  try {
    const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${latitude}&lon=${longitude}`, { headers: { Accept: 'application/json' } });
    const data = await response.json();
    city = data.address?.city || data.address?.town || data.address?.village || data.address?.county || '';
    state = data.address?.state || '';
  } catch {}
  return { latitude, longitude, city, state };
}

