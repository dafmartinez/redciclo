const API_URL = '/api/public/index.php';

// ── Helper base ───────────────────────────────────────────────────────────────
async function parseResponse(res) {
  const json = await res.json().catch(() => ({
    status: 'error', data: null, message: 'Respuesta invalida del servidor',
  }));

  if (!res.ok || json.status !== 'success') {
    throw new Error(json.message || `Error HTTP ${res.status}`);
  }
  return json.data;
}

export async function apiRequest(payload, options = {}) {
  const res = await fetch(API_URL, {
    method:      'POST',
    credentials: 'include',
    headers:     { 'Content-Type': 'application/json', ...(options.headers ?? {}) },
    body:        JSON.stringify(payload),
  });
  return parseResponse(res);
}

// Upload multipart (evidencias) — NO usa Content-Type: application/json
export async function apiUpload(formData) {
  const res = await fetch(API_URL, {
    method:      'POST',
    credentials: 'include',
    body:        formData,   // FormData → el browser pone el boundary automáticamente
  });
  return parseResponse(res);
}

// ── Métodos de la API ─────────────────────────────────────────────────────────
export const api = {
  // Auth
  autorizar:   ()                    => apiRequest({ metodo: 'autorizar' }),
  login:       (login, password)     => apiRequest({ metodo: 'login', login, password }),
  logout:      ()                    => apiRequest({ metodo: 'logout' }),
  registro:    (login, password, perfil) =>
                                        apiRequest({ metodo: 'registro', login, password, perfil }),

  // Órdenes
  listarOrdenes: (perfil)            => apiRequest({ metodo: 'solicitar', perfil }),
  crearOrden:    (payload)           => apiRequest({ metodo: 'crear', ...payload }),
  cambiarEstado: (orden, estado, extra = {}) =>
                                        apiRequest({ metodo: 'cambiarestado', orden, estado, ...extra }),
  cancelarOrden: (orden)             => apiRequest({ metodo: 'cancelar', orden }),

  // Evidencias (multipart)
  subirEvidencia: (ordenId, estado, fotoFile) => {
    const fd = new FormData();
    fd.append('metodo', 'subirEvidencia');
    fd.append('orden',  String(ordenId));
    fd.append('estado', String(estado));
    fd.append('foto',   fotoFile);
    return apiUpload(fd);
  },

  // Notas
  crearNota:     (orden, nota)       => apiRequest({ metodo: 'crearnota', orden, nota }),
  listarNotas:   (orden)             => apiRequest({ metodo: 'solicitarnotas', orden }),

  // Configuración
  configuracion: (grupo)             => apiRequest({ metodo: 'configuracion', grupo }),

  // Usuarios (operador)
  listarUsuarios: ()                 => apiRequest({ metodo: 'usuarios' }),
};
