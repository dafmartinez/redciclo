import { esc, fmtDate, normalizePerfil, estadoBadge } from '../utils.js';

// ── Helpers de botones ────────────────────────────────────────────────────────

function actionBtn(id, estado, label, color = 'violet') {
  const cls = {
    violet: 'bg-violet-600 text-white hover:bg-violet-700',
    blue:   'bg-blue-600   text-white hover:bg-blue-700',
    green:  'bg-green-600  text-white hover:bg-green-700',
    amber:  'bg-amber-600  text-white hover:bg-amber-700',
  }[color] ?? 'bg-violet-600 text-white hover:bg-violet-700';

  return `<button data-action="cambiarEstado" data-orden="${id}" data-estado="${estado}"
    class="text-xs px-2.5 py-1 rounded-lg ${cls} transition-colors whitespace-nowrap">
    ${esc(label)}
  </button>`;
}

function notasBtn(id) {
  return `<button data-action="abrirNotas" data-orden="${id}" title="Ver notas"
    class="text-xs px-2 py-1 rounded-lg border border-gray-300 dark:border-gray-600
           hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors flex items-center gap-1">
    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
         stroke-width="1.5" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round"
        d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227
           1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133
           a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379
           c1.584-.233 2.707-1.626 2.707-3.228V6.741
           c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3
           c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z"/>
    </svg>
    Notas
  </button>`;
}

function cancelarBtn(id) {
  return `<button data-action="cancelarOrden" data-orden="${id}"
    class="text-xs px-2 py-1 rounded-lg border border-red-300 dark:border-red-500
           text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
    Cancelar
  </button>`;
}

/** Select + botón para asignar un usuario. Usa data-attributes para evitar IDs duplicados. */
function asignarBtn(ordenId, nuevoEstado, usuarios, placeholder) {
  const opts = usuarios.map(u => `<option value="${u.id}">${esc(u.login)}</option>`).join('');
  return `<div class="flex items-center gap-1">
    <select
      class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800
             px-2 py-1 text-xs min-w-[7rem]">
      <option value="">${esc(placeholder)}</option>${opts}
    </select>
    <button data-action="asignarUsuario" data-orden="${ordenId}" data-estado="${nuevoEstado}"
      class="bg-blue-600 text-white px-2 py-1 rounded text-sm whitespace-nowrap">
      Asignar
    </button>
  </div>`;
}

/** Botones GPS + evidencia para el transportista (estados 6→7 y 7→8). */
function evidenciaBtn(id, nuevoEstado, label, gpsActivo) {
  const gpsCls = gpsActivo
    ? 'bg-green-600 text-white border-green-600'
    : 'border border-green-500 text-green-700 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/20';

  return `<div class="flex items-center gap-2 flex-wrap justify-end">
    <button data-action="toggleGPS" data-orden="${id}"
      class="text-xs px-2.5 py-1 rounded-lg ${gpsCls} transition-colors flex items-center gap-1">
      <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
           stroke-width="2" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
      </svg>
      ${gpsActivo ? 'Detener GPS' : 'GPS'}
    </button>
    <button data-action="abrirEvidencia" data-orden="${id}" data-estado="${nuevoEstado}"
      class="text-xs px-2.5 py-1 rounded-lg bg-violet-600 text-white hover:bg-violet-700 transition-colors">
      ${esc(label)}
    </button>
  </div>`;
}

// ── Lógica de acciones por perfil y estado ────────────────────────────────────

/**
 * Devuelve el HTML de acciones disponibles para una orden según el perfil del usuario.
 *
 * @param {object} orden      - Fila de la orden (del servidor)
 * @param {object} user       - Usuario activo { id, perfil }
 * @param {object[]} users    - Lista de todos los usuarios activos
 * @param {number|null} gpsOrdenActiva - ID de orden con GPS activo
 */
export function renderAcciones(orden, user, users, gpsOrdenActiva) {
  const perfil   = normalizePerfil(user?.perfil);
  const estadoId = Number(orden.estado_id);
  const userId   = Number(user?.id);
  const esAprov  = Number(orden.aprovechador)   === userId;
  const esTrans  = Number(orden.transportista)  === userId;
  const parts    = [];

  // ── OPERADOR ───────────────────────────────────────────────────────────────
  if (perfil === 'operador') {
    if (estadoId === 1) {
      const aprovs = users.filter(u => normalizePerfil(u.perfil) === 'aprovechador');
      parts.push(asignarBtn(orden.id, 2, aprovs, 'Aprovechador…'));
    }
    if (estadoId === 3) {
      const transp = users.filter(u => normalizePerfil(u.perfil) === 'transportista');
      parts.push(asignarBtn(orden.id, 4, transp, 'Transportista…'));
    }
    parts.push(notasBtn(orden.id));
    parts.push(cancelarBtn(orden.id));
    return wrap(parts);
  }

  // ── APROVECHADOR ──────────────────────────────────────────────────────────
  if (perfil === 'aprovechador' && esAprov) {
    if (estadoId === 2) parts.push(actionBtn(orden.id, 3, 'Aceptar orden', 'blue'));
    if (estadoId === 8) parts.push(actionBtn(orden.id, 9, 'Recibir orden', 'green'));
    if (estadoId !== 9) parts.push(notasBtn(orden.id));
    return wrap(parts);
  }

  // ── TRANSPORTISTA ─────────────────────────────────────────────────────────
  if (perfil === 'transportista' && esTrans) {
    if (estadoId === 4) parts.push(actionBtn(orden.id, 5, 'Aceptar orden',   'violet'));
    if (estadoId === 5) parts.push(actionBtn(orden.id, 6, 'Salir a recoger', 'violet'));
    if (estadoId === 6) parts.push(evidenciaBtn(orden.id, 7, 'Recoger material',       gpsOrdenActiva === orden.id));
    if (estadoId === 7) parts.push(evidenciaBtn(orden.id, 8, 'Entregar al aprovechador', gpsOrdenActiva === orden.id));
    if (estadoId !== 9) parts.push(notasBtn(orden.id));
    return wrap(parts);
  }

  // ── CLIENTE ───────────────────────────────────────────────────────────────
  if (perfil === 'cliente') {
    if (estadoId !== 9) parts.push(notasBtn(orden.id));
    return wrap(parts);
  }

  return wrap(parts);
}

function wrap(parts) {
  return `<div class="flex flex-wrap gap-1.5 justify-end items-center">${parts.join('')}</div>`;
}

// ── Vista: Tabla (escritorio) ─────────────────────────────────────────────────

export function tablaOrdenesView(ordenes, user, users, gpsOrdenActiva) {
  if (!ordenes.length) {
    return `<tr><td colspan="9" class="text-center py-12 text-gray-400 text-sm">
      Sin órdenes para mostrar.</td></tr>`;
  }

  return ordenes.map(o => `
    <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
      <td class="px-4 py-3 font-mono text-xs text-gray-400">#${o.id}</td>
      <td class="px-4 py-3 text-xs text-gray-500">${fmtDate(o.forden)}</td>
      <td class="px-4 py-3 text-xs text-gray-500">${fmtDate(o.fprogramada)}</td>
      <td class="px-4 py-3">
        <p class="font-medium text-sm">${esc(o.material ?? '—')}</p>
        <p class="text-xs text-gray-400">${esc(o.categoria ?? '—')}</p>
      </td>
      <td class="px-4 py-3 text-sm">${Number(o.cantidad).toLocaleString('es-CO')} ${esc(o.medida ?? '')}</td>
      <td class="px-4 py-3">
        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${estadoBadge(o.estado)}">
          ${esc(o.estado ?? '—')}
        </span>
      </td>
      <td class="px-4 py-3 text-center">
        ${renderAcciones(o, user, users, gpsOrdenActiva)}
      </td>
    </tr>
  `).join('');
}

// ── Vista: Cards (móvil) ──────────────────────────────────────────────────────

export function cardsOrdenesView(ordenes, user, users, gpsOrdenActiva) {
  if (!ordenes.length) {
    return `<div class="text-center py-12 text-gray-400 text-sm">Sin órdenes para mostrar.</div>`;
  }

  return ordenes.map(o => `
    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow ring-1 ring-black/5 dark:ring-white/10 p-4 space-y-3">
      <div class="flex items-center justify-between">
        <span class="font-mono text-xs text-gray-400">#${o.id} · ${fmtDate(o.forden)}</span>
        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${estadoBadge(o.estado)}">
          ${esc(o.estado ?? '—')}
        </span>
      </div>
      <div>
        <p class="font-medium">${esc(o.material ?? '—')}</p>
        <p class="text-sm text-gray-500 dark:text-gray-400">${esc(o.categoria ?? '—')}</p>
      </div>
      <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-600 dark:text-gray-300">
        <span class="font-medium">${Number(o.cantidad).toLocaleString('es-CO')} ${esc(o.medida ?? '')}</span>
        <span class="text-gray-400">Prog: ${fmtDate(o.fprogramada)}</span>
      </div>
      <div class="border-t border-gray-100 dark:border-gray-800 pt-3 flex justify-end">
        ${renderAcciones(o, user, users, gpsOrdenActiva)}
      </div>
    </div>
  `).join('');
}
