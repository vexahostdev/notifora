/** fetch helper: CSRF, JSON, penanganan error umum. */
export async function api(url, { method = 'GET', body } = {}) {
  const token = document.querySelector('meta[name="csrf-token"]')?.content;
  const res = await fetch(url, {
    method,
    headers: {
      Accept: 'application/json',
      ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
      ...(token ? { 'X-CSRF-TOKEN': token } : {}),
    },
    body: body !== undefined ? JSON.stringify(body) : undefined,
    credentials: 'same-origin',
  });

  if (res.status === 401 || res.status === 419) {
    window.location.href = '/login';
    throw new Error('unauthenticated');
  }
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    const err = new Error(data.message || data.error || 'Request gagal');
    err.status = res.status;
    err.data = data;
    throw err;
  }
  return data;
}
