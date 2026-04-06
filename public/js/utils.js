// ── Escape HTML ───────────────────────────────────────────────────────────────
export function esc(str) {
  const d = document.createElement('div');
  d.textContent = String(str ?? '');
  return d.innerHTML;
}

// ── Formato de fecha ──────────────────────────────────────────────────────────
export function fmtDate(str) {
  if (!str) return '—';
  const d = new Date(str);
  return isNaN(d) ? str : d.toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric' });
}

// ── Normalizar perfil ─────────────────────────────────────────────────────────
export function normalizePerfil(perfil) {
  const val = String(perfil ?? '').trim().toLowerCase();
  if (val === 'cliente'      || val === 'clientes')                                   return 'cliente';
  if (val === 'transportista'|| val === 'transportistas' || val === 'conductor')      return 'transportista';
  if (val === 'aprovechador' || val === 'aprovechadores' || val === 'reciclador')     return 'aprovechador';
  if (val === 'operador'     || val === 'operadores'     || val === 'admin'
                             || val === 'administrador')                              return 'operador';
  return val;
}

// ── Badge de color por estado ─────────────────────────────────────────────────
export function estadoBadge(estado) {
  const map = {
    'en espera':              'bg-gray-100    text-gray-600    dark:bg-gray-700       dark:text-gray-300',
    'asignado aprovechador':  'bg-blue-100    text-blue-700    dark:bg-blue-900/50    dark:text-blue-300',
    'aceptado aprovechador':  'bg-sky-100     text-sky-700     dark:bg-sky-900/50     dark:text-sky-300',
    'asignado transportista': 'bg-violet-100  text-violet-700  dark:bg-violet-900/50  dark:text-violet-300',
    'aceptado transportista': 'bg-purple-100  text-purple-700  dark:bg-purple-900/50  dark:text-purple-300',
    'en camino al cliente':   'bg-amber-100   text-amber-700   dark:bg-amber-900/50   dark:text-amber-300',
    'recogido en cliente':    'bg-orange-100  text-orange-700  dark:bg-orange-900/50  dark:text-orange-300',
    'entregado aprovechador': 'bg-green-100   text-green-700   dark:bg-green-900/50   dark:text-green-300',
    'recibido aprovechador':  'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300',
    'cancelado':              'bg-red-100     text-red-700     dark:bg-red-900/50     dark:text-red-300',
  };
  return map[String(estado ?? '').toLowerCase()] ?? 'bg-gray-100 text-gray-600';
}

// ── Toast ─────────────────────────────────────────────────────────────────────
export function toast(message, type = 'success') {
  const colors = { success: 'bg-green-600', error: 'bg-red-600', info: 'bg-blue-600' };
  const icons  = { success: '✓', error: '✕', info: 'ℹ' };

  const el = document.createElement('div');
  el.className = `pointer-events-auto flex items-center gap-2 px-4 py-2.5 rounded-xl shadow-lg
                  text-sm font-medium text-white ${colors[type] ?? colors.info} animate-fade-in transition-all`;
  el.innerHTML = `<span>${icons[type] ?? ''}</span><span>${esc(message)}</span>`;

  document.getElementById('toast-container')?.appendChild(el);
  setTimeout(() => {
    el.style.opacity = '0';
    el.style.transform = 'translateX(12px)';
    setTimeout(() => el.remove(), 300);
  }, 3500);
}
