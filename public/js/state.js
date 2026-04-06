/**
 * Estado global de la app — módulo centralizado.
 * Todos los módulos que necesiten leer o escribir estado lo importan desde aquí.
 * Esto evita variables globales sueltas en window.
 */

export const state = {
  /** @type {{ id: number, login: string, perfil: string }|null} */
  currentUser: null,

  /** @type {Array} Lista de órdenes cargadas del servidor */
  ordenes: [],

  /** @type {{ categorias: Array, materiales: Array, medidas: Array, estados: Array }} */
  config: { categorias: [], materiales: [], medidas: [], estados: [] },

  /** @type {Array} Lista de usuarios activos (solo usa el operador) */
  users: [],

  /** @type {number|null} ID de orden con GPS activo */
  gpsOrdenActiva: null,

  /** @type {ReturnType<typeof setInterval>|null} */
  gpsIntervalId: null,

  /** @type {{ ordenId: number, estado: number }|null} */
  evidenciaPendiente: null,

  /** @type {number|null} ID de orden cuyas notas están abiertas */
  notasOrdenActiva: null,
};
