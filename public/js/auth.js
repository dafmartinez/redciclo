import { api } from './api.js';
import { state } from './state.js';
import { normalizePerfil } from './utils.js';

// ── Verificar sesión activa ───────────────────────────────────────────────────
export async function initAuth() {
  try {
    const user = await api.autorizar();
    state.currentUser = { ...user, perfil: normalizePerfil(user.perfil) };
  } catch {
    state.currentUser = null;
  }
  return state.currentUser;
}

// ── Login ─────────────────────────────────────────────────────────────────────
export async function loginUser(login, password) {
  const user = await api.login(login, password);
  state.currentUser = { ...user, perfil: normalizePerfil(user.perfil) };
  return state.currentUser;
}

// ── Logout ────────────────────────────────────────────────────────────────────
export async function logoutUser() {
  await api.logout();
  state.currentUser = null;
}

// ── Registro ──────────────────────────────────────────────────────────────────
export async function registerUser(login, password, perfil) {
  // El backend rechaza 'operador' con 403; esta validación es UX, no seguridad.
  const perfilesPermitidos = ['cliente', 'transportista', 'aprovechador'];
  if (!perfilesPermitidos.includes(normalizePerfil(perfil))) {
    throw new Error('Perfil no permitido en el registro público.');
  }
  return api.registro(login, password, perfil);
}
