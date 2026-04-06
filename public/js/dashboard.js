/**
 * dashboard.js — Controlador principal del frontend.
 *
 * Responsabilidades:
 *  - Orquestar carga de datos (órdenes, config, usuarios)
 *  - Renderizar vistas (tabla y cards) y re-renderizar al cambiar estado
 *  - Delegar eventos usando event delegation (un solo listener por contenedor)
 *  - Gestionar modales (nueva orden, notas, evidencia)
 *  - GPS tracking
 */

import { api }                              from './api.js';
import { state }                            from './state.js';
import { initAuth, loginUser, logoutUser, registerUser } from './auth.js';
import { toast, normalizePerfil, esc, fmtDate } from './utils.js';
import { tablaOrdenesView, cardsOrdenesView }    from './views/tablaOrdenes.js';

const GPS_INTERVALO_MS = 3 * 60 * 1000;

// ── Inicialización ────────────────────────────────────────────────────────────

export async function initDashboard() {
  await initAuth();

  if (!state.currentUser) {
    renderLoginView();
    return;
  }

  renderDashboardView();
  syncUiForRole();

  // Carga paralela inicial
  await Promise.all([loadConfig(), loadUsers(), loadOrdenes()]);
}

// ── Vistas raíz ──────────────────────────────────────────────────────────────

function renderLoginView() {
  // El HTML estático ya tiene el formulario; solo asegurar visibilidad.
  document.getElementById('view-login')?.classList.remove('hidden');
  document.getElementById('view-dashboard')?.classList.add('hidden');
}

function renderDashboardView() {
  document.getElementById('view-login')?.classList.add('hidden');
  document.getElementById('view-dashboard')?.classList.remove('hidden');
  document.getElementById('nav-user-name').textContent    = state.currentUser?.login ?? '';
  document.getElementById('nav-perfil-badge').textContent = state.currentUser?.perfil ?? '';
}

function syncUiForRole() {
  const perfil   = normalizePerfil(state.currentUser?.perfil);
  const canCreate = perfil === 'cliente';
  document.getElementById('btn-nueva-orden')?.classList.toggle('hidden', !canCreate);
}

// ── Carga de datos ────────────────────────────────────────────────────────────

async function loadConfig() {
  try {
    const [categorias, materiales, medidas, estados] = await Promise.all([
      api.configuracion('categorias'),
      api.configuracion('materiales'),
      api.configuracion('medidas'),
      api.configuracion('estados'),
    ]);
    state.config = { categorias, materiales, medidas, estados };
    poblarSelectCategoria();
    poblarFiltroEstados();
  } catch (e) {
    console.error('[Config]', e.message);
  }
}

async function loadUsers() {
  try {
    state.users = await api.listarUsuarios();
  } catch {
    state.users = [];
  }
}

export async function loadOrdenes() {
  const tbody   = document.getElementById('ordenes-tbody');
  const cardsEl = document.getElementById('ordenes-cards');
  if (tbody)   tbody.innerHTML   = rowLoading();
  if (cardsEl) cardsEl.innerHTML = '';

  try {
    state.ordenes = await api.listarOrdenes(state.currentUser?.perfil);
    renderOrdenes();
  } catch (e) {
    if (tbody)   tbody.innerHTML   = rowError(e.message);
    if (cardsEl) cardsEl.innerHTML = `<div class="text-center py-12 text-red-400 text-sm">Error: ${esc(e.message)}</div>`;
  }
}

// ── Renderizado ───────────────────────────────────────────────────────────────

export function renderOrdenes() {
  const filtroId = document.getElementById('filtro-estado')?.value ?? '';
  const data = filtroId
    ? state.ordenes.filter(o => String(o.estado_id) === filtroId)
    : state.ordenes;

  const tbody   = document.getElementById('ordenes-tbody');
  const cardsEl = document.getElementById('ordenes-cards');

  if (tbody) {
    tbody.innerHTML = tablaOrdenesView(data, state.currentUser, state.users, state.gpsOrdenActiva);
  }
  if (cardsEl) {
    cardsEl.innerHTML = cardsOrdenesView(data, state.currentUser, state.users, state.gpsOrdenActiva);
  }
}

// ── Event delegation sobre la tabla y las cards ───────────────────────────────
// En lugar de onclick="fn()" en cada botón usamos data-action para evitar
// globals en window y para que el mismo handler cubra tabla + cards.

document.addEventListener('click', async (e) => {
  const btn = e.target.closest('[data-action]');
  if (!btn) return;

  const action  = btn.dataset.action;
  const ordenId = parseInt(btn.dataset.orden, 10);
  const estado  = parseInt(btn.dataset.estado, 10);

  switch (action) {
    case 'cambiarEstado':
      await doCambiarEstado(ordenId, estado);
      break;

    case 'asignarUsuario': {
      // Navegación relativa: leer el <select> dentro del mismo contenedor flex
      const sel = btn.parentElement?.querySelector('select');
      const idAsignado = sel ? parseInt(sel.value, 10) : 0;
      if (!idAsignado) { toast('Selecciona un usuario de la lista', 'error'); return; }
      await doCambiarEstado(ordenId, estado, { id_asignado: idAsignado });
      break;
    }

    case 'cancelarOrden':
      if (!confirm(`¿Cancelar la orden #${ordenId}? Esta acción no se puede deshacer.`)) return;
      try {
        await api.cancelarOrden(ordenId);
        toast(`Orden #${ordenId} cancelada`, 'info');
        await loadOrdenes();
      } catch (err) {
        toast(err.message, 'error');
      }
      break;

    case 'abrirNotas':
      await abrirNotas(ordenId);
      break;

    case 'abrirEvidencia':
      abrirEvidencia(ordenId, estado);
      break;

    case 'toggleGPS':
      toggleGPS(ordenId);
      break;
  }
});

async function doCambiarEstado(ordenId, estado, extra = {}) {
  try {
    await api.cambiarEstado(ordenId, estado, extra);
    toast(`Estado de la orden #${ordenId} actualizado`, 'success');
    await loadOrdenes();
  } catch (err) {
    toast(err.message, 'error');
  }
}

// ── Filtro y recarga ──────────────────────────────────────────────────────────

document.getElementById('filtro-estado')?.addEventListener('change', renderOrdenes);
document.getElementById('btn-reload')?.addEventListener('click', loadOrdenes);

// ── Logout ────────────────────────────────────────────────────────────────────

document.getElementById('btn-logout')?.addEventListener('click', async () => {
  await logoutUser();
  renderLoginView();
  syncUiForRole();
  state.ordenes = [];
  state.users   = [];
  state.config  = { categorias: [], materiales: [], medidas: [], estados: [] };
});

// ── Login form ────────────────────────────────────────────────────────────────

document.getElementById('form-login')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const login    = document.getElementById('inp-login').value.trim();
  const password = document.getElementById('inp-pass').value;
  const errEl    = document.getElementById('login-error');
  const errText  = document.getElementById('login-error-text');
  const btnText  = document.getElementById('btn-login-text');
  const spinner  = document.getElementById('btn-login-spinner');
  const btn      = document.getElementById('btn-login');

  errEl.classList.add('hidden');
  btnText.textContent = 'Verificando…';
  spinner.classList.remove('hidden');
  btn.disabled = true;

  try {
    await loginUser(login, password);
    document.getElementById('nav-user-name').textContent    = state.currentUser.login;
    document.getElementById('nav-perfil-badge').textContent = state.currentUser.perfil;
    renderDashboardView();
    syncUiForRole();
    await Promise.all([loadConfig(), loadUsers(), loadOrdenes()]);
  } catch (err) {
    errText.textContent = err.message;
    errEl.classList.remove('hidden');
    document.getElementById('inp-pass').value = '';
  } finally {
    btnText.textContent = 'Ingresar';
    spinner.classList.add('hidden');
    btn.disabled = false;
  }
});

// ── Registro form ─────────────────────────────────────────────────────────────

document.getElementById('form-register')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const login    = document.getElementById('inp-register-login').value.trim();
  const password = document.getElementById('inp-register-pass').value;
  const perfil   = document.getElementById('inp-register-perfil').value;
  const errEl    = document.getElementById('register-error');
  const errText  = document.getElementById('register-error-text');
  const btn      = document.getElementById('btn-register');

  errEl.classList.add('hidden');
  btn.disabled = true;

  try {
    await registerUser(login, password, perfil);
    toast('Usuario registrado. Ahora puedes iniciar sesión.', 'success');
    // Volver al form de login
    document.getElementById('form-register').classList.add('hidden');
    document.getElementById('form-login').classList.remove('hidden');
  } catch (err) {
    errText.textContent = err.message;
    errEl.classList.remove('hidden');
  } finally {
    btn.disabled = false;
  }
});

// ── Modal: Nueva Orden ────────────────────────────────────────────────────────

document.getElementById('btn-nueva-orden')?.addEventListener('click', abrirModalOrden);
document.getElementById('btn-cancelar-orden')?.addEventListener('click', cerrarModalOrden);
document.getElementById('btn-cerrar-modal')?.addEventListener('click', cerrarModalOrden);
document.getElementById('modal-overlay')?.addEventListener('click', cerrarModalOrden);

function abrirModalOrden() {
  if (normalizePerfil(state.currentUser?.perfil) !== 'cliente') {
    toast('Solo el perfil cliente puede crear solicitudes.', 'error');
    return;
  }
  document.getElementById('orden-error')?.classList.add('hidden');
  document.getElementById('form-orden')?.reset();
  document.getElementById('modal-orden')?.classList.remove('hidden');
}

function cerrarModalOrden() {
  document.getElementById('modal-orden')?.classList.add('hidden');
}

document.getElementById('form-orden')?.addEventListener('submit', async (e) => {
  e.preventDefault();

  const payload = {
    fprogramada: document.getElementById('ord-fecha').value,
    categoria:   document.getElementById('ord-categoria').value,
    material:    document.getElementById('ord-material').value,
    cantidad:    document.getElementById('ord-cantidad').value,
    medida:      document.getElementById('ord-medida').value,
  };

  const errEl   = document.getElementById('orden-error');
  const errText = document.getElementById('orden-error-text');
  const spinner = document.getElementById('spinner-orden');
  const btn     = document.getElementById('btn-submit-orden');

  errEl.classList.add('hidden');
  spinner.classList.remove('hidden');
  btn.disabled = true;

  try {
    await api.crearOrden(payload);
    cerrarModalOrden();
    toast('Solicitud creada correctamente', 'success');
    await loadOrdenes();
  } catch (err) {
    errText.textContent = err.message;
    errEl.classList.remove('hidden');
  } finally {
    spinner.classList.add('hidden');
    btn.disabled = false;
  }
});

// ── Modal: Notas ──────────────────────────────────────────────────────────────

document.getElementById('btn-notas-cerrar')?.addEventListener('click',  cerrarNotas);
document.getElementById('notas-overlay')?.addEventListener('click',     cerrarNotas);
document.getElementById('btn-nota-enviar')?.addEventListener('click',   enviarNota);

async function abrirNotas(ordenId) {
  state.notasOrdenActiva = ordenId;
  document.getElementById('notas-orden-id').textContent = `Orden #${ordenId}`;
  document.getElementById('nota-texto').value = '';
  syncNotasComposer();
  document.getElementById('modal-notas')?.classList.remove('hidden');
  await cargarNotas();
}

function cerrarNotas() {
  document.getElementById('modal-notas')?.classList.add('hidden');
  state.notasOrdenActiva = null;
}

function syncNotasComposer() {
  const orden = state.ordenes.find(o => Number(o.id) === Number(state.notasOrdenActiva));
  const perfil    = normalizePerfil(state.currentUser?.perfil);
  const estadoId  = Number(orden?.estado_id);
  const puedeCrear = perfil === 'operador' || estadoId !== 9;

  const textarea = document.getElementById('nota-texto');
  const btn      = document.getElementById('btn-nota-enviar');
  if (textarea) {
    textarea.disabled    = !puedeCrear;
    textarea.placeholder = puedeCrear ? 'Escribe una nota…' : 'No puedes agregar notas en este estado';
  }
  if (btn) btn.disabled = !puedeCrear;
  const btnText = document.getElementById('btn-nota-text');
  if (btnText) btnText.textContent = puedeCrear ? 'Agregar nota' : 'Solo lectura';
}

async function cargarNotas() {
  const lista = document.getElementById('notas-lista');
  if (!lista) return;
  lista.innerHTML = '<p class="text-sm text-gray-400 text-center py-6">Cargando…</p>';
  try {
    const notas = await api.listarNotas(state.notasOrdenActiva);
    if (!notas.length) {
      lista.innerHTML = '<p class="text-sm text-gray-400 text-center py-6">No hay notas aún.</p>';
      return;
    }
    lista.innerHTML = notas.map(n => `
      <div class="bg-gray-50 dark:bg-gray-800 rounded-xl px-3 py-2 space-y-1">
        <div class="flex items-center justify-between gap-2">
          <span class="text-xs font-semibold text-gray-600 dark:text-gray-300 truncate">
            ${esc(n.login)} <span class="font-normal opacity-60">· ${esc(n.perfil)}</span>
          </span>
          <span class="text-xs text-gray-400 shrink-0">${fmtDate(n.fnota)}</span>
        </div>
        <p class="text-sm text-gray-700 dark:text-gray-200 whitespace-pre-wrap">${esc(n.nota)}</p>
      </div>`).join('');
  } catch (e) {
    lista.innerHTML = `<p class="text-sm text-red-400 text-center py-6">Error: ${esc(e.message)}</p>`;
  }
}

async function enviarNota() {
  const orden = state.ordenes.find(o => Number(o.id) === Number(state.notasOrdenActiva));
  const perfil   = normalizePerfil(state.currentUser?.perfil);
  const estadoId = Number(orden?.estado_id);
  if (perfil !== 'operador' && estadoId === 9) {
    toast('No puedes agregar notas en el estado actual', 'error');
    return;
  }

  const texto   = document.getElementById('nota-texto').value.trim();
  if (!texto) { toast('Escribe una nota antes de enviar', 'error'); return; }

  const btn     = document.getElementById('btn-nota-enviar');
  const btnText = document.getElementById('btn-nota-text');
  const spinner = document.getElementById('spinner-nota');

  spinner.classList.remove('hidden');
  if (btnText) btnText.textContent = 'Enviando…';
  btn.disabled = true;

  try {
    await api.crearNota(state.notasOrdenActiva, texto);
    document.getElementById('nota-texto').value = '';
    toast('Nota agregada', 'success');
    await cargarNotas();
  } catch (e) {
    toast(e.message, 'error');
  } finally {
    spinner.classList.add('hidden');
    btn.disabled = false;
    syncNotasComposer();
  }
}

// ── Modal: Evidencia ──────────────────────────────────────────────────────────

document.getElementById('btn-evidencia-cerrar')?.addEventListener('click',  cerrarEvidencia);
document.getElementById('btn-evidencia-cancelar')?.addEventListener('click', cerrarEvidencia);
document.getElementById('evidencia-overlay')?.addEventListener('click',      cerrarEvidencia);
document.getElementById('btn-evidencia-subir')?.addEventListener('click',    subirEvidencia);
document.getElementById('evidencia-input')?.addEventListener('change', function () {
  const file = this.files[0];
  const wrap = document.getElementById('evidencia-preview-wrap');
  const img  = document.getElementById('evidencia-preview');
  if (!file) { wrap?.classList.add('hidden'); return; }
  if (img.src) URL.revokeObjectURL(img.src);
  img.src = URL.createObjectURL(file);
  wrap?.classList.remove('hidden');
});

function abrirEvidencia(ordenId, estadoDestino) {
  state.evidenciaPendiente = { ordenId, estado: estadoDestino };
  const input = document.getElementById('evidencia-input');
  if (input) input.value = '';
  document.getElementById('evidencia-preview-wrap')?.classList.add('hidden');
  document.getElementById('evidencia-error')?.classList.add('hidden');
  document.getElementById('modal-evidencia')?.classList.remove('hidden');
}

function cerrarEvidencia() {
  document.getElementById('modal-evidencia')?.classList.add('hidden');
  state.evidenciaPendiente = null;
}

async function subirEvidencia() {
  if (!state.evidenciaPendiente) return;

  const { ordenId, estado } = state.evidenciaPendiente;
  const file   = document.getElementById('evidencia-input')?.files[0];
  const errEl  = document.getElementById('evidencia-error');
  if (errEl) errEl.classList.add('hidden');

  if (!file) {
    if (errEl) { errEl.textContent = 'Selecciona o toma una foto antes de continuar.'; errEl.classList.remove('hidden'); }
    return;
  }

  const spinner = document.getElementById('spinner-evidencia');
  const btnText = document.getElementById('btn-evidencia-text');
  const btn     = document.getElementById('btn-evidencia-subir');

  spinner.classList.remove('hidden');
  if (btnText) btnText.textContent = 'Subiendo…';
  btn.disabled = true;

  try {
    await api.subirEvidencia(ordenId, estado, file);
    cerrarEvidencia();
    toast('Evidencia subida correctamente', 'success');
    await loadOrdenes();
  } catch (e) {
    if (errEl) { errEl.textContent = e.message; errEl.classList.remove('hidden'); }
  } finally {
    spinner.classList.add('hidden');
    if (btnText) btnText.textContent = 'Confirmar y Subir';
    btn.disabled = false;
  }
}

// ── GPS Tracking ──────────────────────────────────────────────────────────────

function toggleGPS(ordenId) {
  if (state.gpsOrdenActiva === ordenId) {
    detenerGPS();
    return;
  }
  if (!navigator.geolocation) {
    toast('Tu navegador no soporta geolocalización', 'error');
    return;
  }
  if (state.gpsOrdenActiva !== null) detenerGPS();

  navigator.geolocation.getCurrentPosition(
    async (pos) => {
      state.gpsOrdenActiva = ordenId;
      renderOrdenes();
      await enviarUbicacion(ordenId, pos.coords.latitude, pos.coords.longitude);
      toast('GPS activo — ubicación enviada', 'success');

      state.gpsIntervalId = setInterval(() => {
        navigator.geolocation.getCurrentPosition(
          async (p) => enviarUbicacion(ordenId, p.coords.latitude, p.coords.longitude),
          (err) => console.warn('[GPS]', err.message),
          { enableHighAccuracy: true, timeout: 10000 }
        );
      }, GPS_INTERVALO_MS);
    },
    (geoErr) => {
      const msgs = {
        1: 'Permiso de ubicación denegado.',
        2: 'No se pudo determinar tu posición.',
        3: 'Tiempo de espera agotado al obtener la ubicación.',
      };
      toast(msgs[geoErr.code] ?? 'Error de geolocalización', 'error');
    },
    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
  );
}

function detenerGPS() {
  if (state.gpsIntervalId !== null) {
    clearInterval(state.gpsIntervalId);
    state.gpsIntervalId = null;
  }
  const anterior = state.gpsOrdenActiva;
  state.gpsOrdenActiva = null;
  if (anterior !== null) {
    renderOrdenes();
    toast('Seguimiento GPS detenido', 'info');
  }
}

async function enviarUbicacion(ordenId, lat, lng) {
  try {
    await api.apiRequest?.({ metodo: 'track', orden: ordenId, latitud: lat, longitud: lng });
  } catch (e) {
    console.warn('[Track]', e.message);
  }
}

// ── Dark mode ─────────────────────────────────────────────────────────────────

function applyDark(on) {
  document.documentElement.classList.toggle('dark', on);
  document.getElementById('icon-moon')?.classList.toggle('hidden', on);
  document.getElementById('icon-sun')?.classList.toggle('hidden', !on);
}

applyDark(localStorage.getItem('dark') === '1');
document.getElementById('btn-dark')?.addEventListener('click', () => {
  const on = !document.documentElement.classList.contains('dark');
  applyDark(on);
  localStorage.setItem('dark', on ? '1' : '0');
});

// ── Helpers de UI ─────────────────────────────────────────────────────────────

function poblarSelectCategoria() {
  const sel = document.getElementById('ord-categoria');
  if (!sel) return;
  sel.innerHTML = '<option value="">— Elige una categoría —</option>'
    + state.config.categorias.map(c => `<option value="${c.id}">${esc(c.categoria)}</option>`).join('');
}

function poblarFiltroEstados() {
  const sel = document.getElementById('filtro-estado');
  if (!sel) return;
  // Mantener la opción "Todos"
  const current = sel.value;
  sel.innerHTML = '<option value="">Todos</option>'
    + state.config.estados.map(e => `<option value="${e.id}">${esc(e.estado)}</option>`).join('');
  sel.value = current;
}

function rowLoading() {
  return `<tr><td colspan="7" class="text-center py-12">
    <div class="flex flex-col items-center gap-2 text-gray-400">
      <svg class="animate-spin w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"/>
      </svg>
      <span class="text-sm">Cargando…</span>
    </div>
  </td></tr>`;
}

function rowError(msg) {
  return `<tr><td colspan="7" class="text-center py-12 text-red-400 text-sm">Error: ${esc(msg)}</td></tr>`;
}

// ── ESC para cerrar modales ───────────────────────────────────────────────────
document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  cerrarModalOrden();
  cerrarNotas();
  cerrarEvidencia();
});

// ── Global unhandled rejections ───────────────────────────────────────────────
window.addEventListener('unhandledrejection', (e) => {
  const msg = e.reason?.message ?? String(e.reason ?? 'Error desconocido');
  console.error('[Redciclo]', msg);
  toast(`Error inesperado: ${msg}`, 'error');
});
