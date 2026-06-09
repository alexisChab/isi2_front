<script setup>
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'

const defaultApiUrl = import.meta.env.VITE_API_BASE_URL || '/api'

const apiBaseUrl = ref(localStorage.getItem('isi_api_base_url') || defaultApiUrl)
const token = ref(localStorage.getItem('isi_token') || '')
const connectedUser = ref(localStorage.getItem('isi_user') || '')
const activeView = ref('catalogue')
const authMode = ref('login')
const loading = ref(false)
const status = ref({ type: '', text: '' })

const loginForm = ref({
  Email: '',
  Mot_de_Passe: '',
})

const registerForm = ref({
  name: '',
  surname: '',
  Email: '',
  Mot_de_Passe: '',
})

const catalogue = ref([])
const freeTracks = ref([])
const albums = ref([])
const styles = ref([])
const artistes = ref([])
const playlists = ref([])
const selectedPlaylistId = ref('')
const playlistContent = ref(null)
const newPlaylistName = ref('')
const selectedTrackIds = ref([])
const selectedPlaylistForAdd = ref('')
const search = ref('')
const priceFilter = ref('all')
const explorerResult = ref(null)
const invoices = ref(null)
const userProfile = ref(null)

const isAuthenticated = computed(() => Boolean(token.value))
const selectedTracks = computed(() =>
  catalogue.value.filter((track) => selectedTrackIds.value.includes(track.id)),
)
const payableSelectedTracks = computed(() => selectedTracks.value.filter((track) => Boolean(track.payant)))
const selectedPlaylist = computed(() =>
  playlists.value.find((playlist) => String(playlist.id) === String(selectedPlaylistId.value)),
)

const filteredCatalogue = computed(() => {
  const term = search.value.trim().toLowerCase()

  return catalogue.value.filter((track) => {
    const title = trackTitle(track).toLowerCase()
    const matchesSearch = !term || title.includes(term)
    const matchesPrice =
      priceFilter.value === 'all' ||
      (priceFilter.value === 'free' && !track.payant) ||
      (priceFilter.value === 'paid' && track.payant)

    return matchesSearch && matchesPrice
  })
})

const totalBasket = computed(() =>
  payableSelectedTracks.value.reduce((total, track) => total + Number(track.prix || 0), 0),
)

const navigationItems = computed(() => [
  { id: 'catalogue', label: 'Catalogue', count: catalogue.value.length },
  { id: 'playlists', label: 'Playlists', count: playlists.value.length, locked: !isAuthenticated.value },
  { id: 'achats', label: 'Achats', count: payableSelectedTracks.value.length, locked: !isAuthenticated.value },
  { id: 'premium', label: 'Premium', locked: !isAuthenticated.value },
  { id: 'factures', label: 'Factures', locked: !isAuthenticated.value },
  { id: 'explorer', label: 'Explorer' },
])

const api = computed(() => {
  const instance = axios.create({
    baseURL: apiBaseUrl.value.replace(/\/$/, ''),
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
  })

  instance.interceptors.request.use((config) => {
    if (token.value) {
      config.headers.Authorization = `Bearer ${token.value}`
    }

    return config
  })

  return instance
})

function trackTitle(track) {
  return track?.name || track?.Name || track?.titre || `Musique #${track?.id ?? ''}`.trim()
}

function albumName(album) {
  return album?.Name || album?.name || album?.titre || `Album #${album?.id ?? ''}`.trim()
}

function artistName(artiste) {
  return artiste?.name || artiste?.Name || artiste?.nom || `Artiste #${artiste?.id ?? ''}`.trim()
}

function styleName(style) {
  return style?.libelle || style?.name || style?.Name || String(style)
}

function priceLabel(track) {
  if (!track?.payant) return 'Gratuit'
  if (track?.prix === undefined || track?.prix === null) return 'Payant'
  return `${track.prix} EUR`
}

function setStatus(type, text) {
  status.value = { type, text }
}

function parseError(error) {
  if (error?.response?.data?.erreur) return error.response.data.erreur
  if (error?.response?.data?.message) return error.response.data.message
  if (error?.response?.data?.errors) {
    return Object.values(error.response.data.errors).flat().join(' ')
  }
  return error?.message || 'Une erreur est survenue.'
}

async function safeRequest(callback, fallbackMessage = 'Action impossible pour le moment.') {
  loading.value = true
  setStatus('', '')

  try {
    return await callback()
  } catch (error) {
    setStatus('error', parseError(error) || fallbackMessage)

    if (error?.response?.status === 401) {
      clearSession()
    }

    return null
  } finally {
    loading.value = false
  }
}

function saveApiUrl() {
  localStorage.setItem('isi_api_base_url', apiBaseUrl.value)
  setStatus('success', 'URL API enregistrée.')
  refreshAll()
}

function persistSession(payload) {
  token.value = payload.token
  connectedUser.value = payload.utilisateur || payload.user || 'Compte connecté'
  localStorage.setItem('isi_token', token.value)
  localStorage.setItem('isi_user', connectedUser.value)
}

function clearSession() {
  token.value = ''
  connectedUser.value = ''
  userProfile.value = null
  playlists.value = []
  selectedPlaylistId.value = ''
  selectedPlaylistForAdd.value = ''
  playlistContent.value = null
  invoices.value = null
  selectedTrackIds.value = []
  localStorage.removeItem('isi_token')
  localStorage.removeItem('isi_user')
}

async function login() {
  const data = await safeRequest(async () => {
    const response = await api.value.post('/login', loginForm.value)
    return response.data
  })

  if (!data) return

  persistSession(data)
  setStatus('success', data.message || 'Connexion réussie.')
  loginForm.value.Mot_de_Passe = ''
  await loadPrivateData()
}

async function register() {
  const payload = {
    ...registerForm.value,
    Mot_de_Passe_confirmation: registerForm.value.Mot_de_Passe,
  }

  const data = await safeRequest(async () => {
    const response = await api.value.post('/register', payload)
    return response.data
  })

  if (!data) return

  persistSession(data)
  setStatus('success', data.message || 'Compte créé.')
  registerForm.value.Mot_de_Passe = ''
  await loadPrivateData()
}

async function logout() {
  if (token.value) {
    await safeRequest(async () => api.value.post('/logout'))
  }

  clearSession()
  setStatus('success', 'Déconnexion réussie.')
}

async function loadCatalogue() {
  const data = await safeRequest(async () => {
    const [tracks, free, albumList, styleList] = await Promise.all([
      api.value.get('/musiques'),
      api.value.get('/musiques/gratuites'),
      api.value.get('/albums'),
      api.value.get('/styles'),
    ])

    return {
      tracks: tracks.data,
      free: free.data?.musiques || [],
      albums: albumList.data,
      styles: styleList.data,
    }
  }, 'Impossible de charger le catalogue.')

  if (!data) return

  catalogue.value = Array.isArray(data.tracks) ? data.tracks : []
  freeTracks.value = Array.isArray(data.free) ? data.free : []
  albums.value = Array.isArray(data.albums) ? data.albums : []
  styles.value = Array.isArray(data.styles) ? data.styles : []
}

async function loadPrivateData() {
  if (!token.value) return

  await safeRequest(async () => {
    const [profile, playlistList, artistList] = await Promise.all([
      api.value.get('/user'),
      api.value.get('/playlists'),
      api.value.get('/artistes'),
    ])

    userProfile.value = profile.data
    playlists.value = Array.isArray(playlistList.data) ? playlistList.data : []
    artistes.value = Array.isArray(artistList.data) ? artistList.data : []

    if (!selectedPlaylistForAdd.value && playlists.value.length) {
      selectedPlaylistForAdd.value = String(playlists.value[0].id)
    }

    if (!selectedPlaylistId.value && playlists.value.length) {
      selectedPlaylistId.value = String(playlists.value[0].id)
      await loadPlaylistContent(selectedPlaylistId.value)
    }
  }, 'Impossible de charger vos données privées.')
}

async function refreshAll() {
  await loadCatalogue()
  if (token.value) {
    await loadPrivateData()
  }
}

async function createPlaylist() {
  const name = newPlaylistName.value.trim()
  if (!name) {
    setStatus('error', 'Donnez un nom à votre playlist.')
    return
  }

  const data = await safeRequest(async () => {
    const response = await api.value.post('/playlists', { Name: name })
    return response.data
  })

  if (!data) return

  newPlaylistName.value = ''
  setStatus('success', data.message || 'Playlist créée.')
  await loadPrivateData()
}

async function deletePlaylist(id) {
  const data = await safeRequest(async () => {
    const response = await api.value.delete(`/playlists/${id}`)
    return response.data
  })

  if (!data) return

  if (String(selectedPlaylistId.value) === String(id)) {
    selectedPlaylistId.value = ''
    playlistContent.value = null
  }

  setStatus('success', data.message || 'Playlist supprimée.')
  await loadPrivateData()
}

async function loadPlaylistContent(id = selectedPlaylistId.value) {
  if (!id) return

  selectedPlaylistId.value = String(id)

  const data = await safeRequest(async () => {
    const response = await api.value.get(`/playlists/${id}/musiques`)
    return response.data
  })

  if (!data) return
  playlistContent.value = data
}

async function addTrackToPlaylist(track, playlistId = selectedPlaylistForAdd.value) {
  if (!playlistId) {
    setStatus('error', 'Créez ou choisissez une playlist.')
    return
  }

  const data = await safeRequest(async () => {
    const response = await api.value.post(`/playlists/${playlistId}/musiques`, {
      idMusique: track.id,
    })

    return response.data
  })

  if (!data) return

  setStatus('success', data.message || 'Musique ajoutée.')

  if (String(selectedPlaylistId.value) === String(playlistId)) {
    await loadPlaylistContent(playlistId)
  }
}

function toggleBasket(track) {
  const id = track.id
  if (!track.payant) {
    setStatus('error', 'Cette musique est gratuite : ajoutez-la directement à une playlist.')
    return
  }

  if (selectedTrackIds.value.includes(id)) {
    selectedTrackIds.value = selectedTrackIds.value.filter((trackId) => trackId !== id)
  } else {
    selectedTrackIds.value = [...selectedTrackIds.value, id]
  }
}

async function buyBasket() {
  if (!payableSelectedTracks.value.length) {
    setStatus('error', 'Ajoutez au moins une musique payante au panier.')
    return
  }

  const data = await safeRequest(async () => {
    const response = await api.value.post('/musiques/acheter', {
      musiques: payableSelectedTracks.value.map((track) => track.id),
    })

    return response.data
  })

  if (!data) return

  selectedTrackIds.value = []
  setStatus('success', data.message || 'Achat réussi.')
  await loadInvoices()
}

async function subscribePremium() {
  const data = await safeRequest(async () => {
    const response = await api.value.post('/abonnement')
    return response.data
  })

  if (!data) return
  setStatus('success', data.message || 'Abonnement activé.')
}

async function cancelPremium() {
  const data = await safeRequest(async () => {
    const response = await api.value.delete('/abonnement')
    return response.data
  })

  if (!data) return
  setStatus('success', data.message || 'Abonnement résilié.')
}

async function loadInvoices() {
  const data = await safeRequest(async () => {
    const response = await api.value.get('/factures')
    return response.data
  })

  if (!data) return
  invoices.value = data
}

async function explore(type, value) {
  const endpoints = {
    album: `/albums/${value.id}/musiques`,
    style: `/styles/${encodeURIComponent(styleName(value))}/musiques`,
    artiste: `/artistes/${value.id}/albums`,
  }

  const data = await safeRequest(async () => {
    const response = await api.value.get(endpoints[type])
    return response.data
  })

  if (!data) return

  explorerResult.value = {
    type,
    title: data.album || data.genre_musical || data.artiste || value.Name || value.name || styleName(value),
    count: data.nombre_pistes ?? data.nombre_titres ?? data.nombre_albums ?? 0,
    items: data.musiques || data.albums || [],
  }
}

function openLockedView(view) {
  if (view.locked) {
    activeView.value = 'catalogue'
    setStatus('error', 'Connectez-vous pour accéder à cette section.')
    return
  }

  activeView.value = view.id

  if (view.id === 'factures' && token.value && !invoices.value) {
    loadInvoices()
  }
}

onMounted(refreshAll)
</script>

<template>
  <main class="app-shell">
    <aside class="sidebar">
      <div class="brand">
        <span class="brand-mark">IS</span>
        <div>
          <strong>ISI Music</strong>
          <small>Front API Laravel</small>
        </div>
      </div>

      <nav class="nav-list" aria-label="Navigation principale">
        <button
          v-for="item in navigationItems"
          :key="item.id"
          class="nav-item"
          :class="{ active: activeView === item.id, locked: item.locked }"
          type="button"
          @click="openLockedView(item)"
        >
          <span>{{ item.label }}</span>
          <span v-if="item.count !== undefined" class="pill">{{ item.count }}</span>
        </button>
      </nav>

      <section class="api-panel">
        <label for="api-url">URL API</label>
        <div class="input-row">
          <input id="api-url" v-model="apiBaseUrl" type="text" />
          <button class="icon-button" type="button" title="Enregistrer l'URL API" @click="saveApiUrl">
            OK
          </button>
        </div>
      </section>
    </aside>

    <section class="workspace">
      <header class="topbar">
        <div>
          <h1>{{ activeView === 'catalogue' ? 'Catalogue' : navigationItems.find((item) => item.id === activeView)?.label }}</h1>
          <p>{{ catalogue.length }} titres chargés depuis l'API</p>
        </div>

        <div v-if="isAuthenticated" class="session-card">
          <span>{{ connectedUser }}</span>
          <button type="button" class="ghost-button" @click="logout">Déconnexion</button>
        </div>
      </header>

      <p v-if="status.text" class="status-message" :class="status.type">{{ status.text }}</p>

      <section v-if="!isAuthenticated" class="auth-strip">
        <div>
          <h2>Connexion</h2>
          <p>Connectez-vous pour acheter, créer des playlists et gérer votre compte.</p>
        </div>

        <div class="auth-card">
          <div class="segmented">
            <button type="button" :class="{ active: authMode === 'login' }" @click="authMode = 'login'">
              Login
            </button>
            <button type="button" :class="{ active: authMode === 'register' }" @click="authMode = 'register'">
              Inscription
            </button>
          </div>

          <form v-if="authMode === 'login'" class="form-grid" @submit.prevent="login">
            <input v-model="loginForm.Email" type="email" placeholder="Email" autocomplete="email" required />
            <input
              v-model="loginForm.Mot_de_Passe"
              type="password"
              placeholder="Mot de passe"
              autocomplete="current-password"
              required
            />
            <button type="submit" :disabled="loading">Se connecter</button>
          </form>

          <form v-else class="form-grid" @submit.prevent="register">
            <div class="two-columns">
              <input v-model="registerForm.name" type="text" placeholder="Prénom" autocomplete="given-name" required />
              <input v-model="registerForm.surname" type="text" placeholder="Nom" autocomplete="family-name" required />
            </div>
            <input v-model="registerForm.Email" type="email" placeholder="Email" autocomplete="email" required />
            <input
              v-model="registerForm.Mot_de_Passe"
              type="password"
              placeholder="Mot de passe"
              autocomplete="new-password"
              required
            />
            <button type="submit" :disabled="loading">Créer mon compte</button>
          </form>
        </div>
      </section>

      <section v-if="activeView === 'catalogue'" class="content-grid">
        <div class="toolbar">
          <input v-model="search" type="search" placeholder="Rechercher un titre" />
          <div class="segmented compact">
            <button type="button" :class="{ active: priceFilter === 'all' }" @click="priceFilter = 'all'">Tous</button>
            <button type="button" :class="{ active: priceFilter === 'free' }" @click="priceFilter = 'free'">Gratuits</button>
            <button type="button" :class="{ active: priceFilter === 'paid' }" @click="priceFilter = 'paid'">Payants</button>
          </div>
          <select v-if="isAuthenticated && playlists.length" v-model="selectedPlaylistForAdd">
            <option v-for="playlist in playlists" :key="playlist.id" :value="playlist.id">
              {{ playlist.Name || playlist.name }}
            </option>
          </select>
        </div>

        <div class="track-grid">
          <article v-for="track in filteredCatalogue" :key="track.id" class="track-card">
            <div>
              <span class="eyebrow">{{ priceLabel(track) }}</span>
              <h3>{{ trackTitle(track) }}</h3>
              <p v-if="track.id_album">Album #{{ track.id_album }}</p>
            </div>
            <div class="card-actions">
              <button
                v-if="isAuthenticated && track.payant"
                type="button"
                class="ghost-button"
                @click="toggleBasket(track)"
              >
                {{ selectedTrackIds.includes(track.id) ? 'Retirer' : 'Panier' }}
              </button>
              <button v-if="isAuthenticated" type="button" @click="addTrackToPlaylist(track)">
                Playlist
              </button>
            </div>
          </article>
        </div>
      </section>

      <section v-else-if="activeView === 'playlists'" class="split-layout">
        <div class="panel">
          <h2>Mes playlists</h2>
          <form class="input-row" @submit.prevent="createPlaylist">
            <input v-model="newPlaylistName" type="text" placeholder="Nouvelle playlist" />
            <button type="submit" :disabled="loading">Créer</button>
          </form>

          <div class="list">
            <article
              v-for="playlist in playlists"
              :key="playlist.id"
              class="list-item"
              :class="{ active: String(selectedPlaylistId) === String(playlist.id) }"
            >
              <button type="button" class="text-button" @click="loadPlaylistContent(playlist.id)">
                {{ playlist.Name || playlist.name }}
              </button>
              <button type="button" class="danger-button" @click="deletePlaylist(playlist.id)">Supprimer</button>
            </article>
          </div>
        </div>

        <div class="panel">
          <h2>{{ playlistContent?.playlist_nom || selectedPlaylist?.Name || 'Contenu' }}</h2>
          <p v-if="playlistContent">{{ playlistContent.nombre_pistes }} titre(s)</p>
          <div v-if="playlistContent?.musiques?.length" class="track-list">
            <article v-for="track in playlistContent.musiques" :key="track.id" class="mini-track">
              <strong>{{ trackTitle(track) }}</strong>
              <span>{{ priceLabel(track) }}</span>
            </article>
          </div>
          <p v-else class="empty-state">Aucune musique dans cette playlist.</p>
        </div>
      </section>

      <section v-else-if="activeView === 'achats'" class="panel">
        <div class="section-heading">
          <div>
            <h2>Panier</h2>
            <p>{{ payableSelectedTracks.length }} titre(s), total {{ totalBasket.toFixed(2) }} EUR</p>
          </div>
          <button type="button" :disabled="loading || !payableSelectedTracks.length" @click="buyBasket">
            Acheter
          </button>
        </div>

        <div v-if="payableSelectedTracks.length" class="track-list">
          <article v-for="track in payableSelectedTracks" :key="track.id" class="mini-track">
            <strong>{{ trackTitle(track) }}</strong>
            <span>{{ priceLabel(track) }}</span>
          </article>
        </div>
        <p v-else class="empty-state">Ajoutez des musiques payantes depuis le catalogue.</p>
      </section>

      <section v-else-if="activeView === 'premium'" class="split-layout">
        <div class="panel emphasis">
          <h2>Premium</h2>
          <p>Avec un abonnement actif, l'API autorise l'ajout de toutes les musiques aux playlists.</p>
          <div class="card-actions">
            <button type="button" :disabled="loading" @click="subscribePremium">Souscrire</button>
            <button type="button" class="ghost-button" :disabled="loading" @click="cancelPremium">Résilier</button>
          </div>
        </div>

        <div class="panel">
          <h2>Compte</h2>
          <dl v-if="userProfile" class="details">
            <div v-for="(value, key) in userProfile" :key="key">
              <dt>{{ key }}</dt>
              <dd>{{ value }}</dd>
            </div>
          </dl>
        </div>
      </section>

      <section v-else-if="activeView === 'factures'" class="panel">
        <div class="section-heading">
          <div>
            <h2>Factures</h2>
            <p v-if="invoices?.total_depense_global">Total dépensé : {{ invoices.total_depense_global }}</p>
          </div>
          <button type="button" class="ghost-button" @click="loadInvoices">Actualiser</button>
        </div>

        <p v-if="invoices?.message" class="empty-state">{{ invoices.message }}</p>

        <div v-if="invoices?.historique_factures?.length" class="invoice-grid">
          <article v-for="invoice in invoices.historique_factures" :key="invoice.numero_lot" class="invoice-card">
            <span class="eyebrow">Lot {{ invoice.numero_lot }}</span>
            <h3>{{ invoice.total_lot }}</h3>
            <p>{{ invoice.date_achat }} · {{ invoice.nombre_musiques }} titre(s)</p>
            <ul>
              <li v-for="music in invoice.musiques" :key="`${invoice.numero_lot}-${music.titre}`">
                {{ music.titre }} · {{ music.prix }}
              </li>
            </ul>
          </article>
        </div>
      </section>

      <section v-else-if="activeView === 'explorer'" class="split-layout">
        <div class="panel">
          <h2>Albums</h2>
          <div class="list">
            <button v-for="album in albums" :key="album.id" class="list-item" type="button" @click="explore('album', album)">
              {{ albumName(album) }}
            </button>
          </div>
        </div>

        <div class="panel">
          <h2>Styles</h2>
          <div class="list">
            <button v-for="style in styles" :key="styleName(style)" class="list-item" type="button" @click="explore('style', style)">
              {{ styleName(style) }}
            </button>
          </div>
        </div>

        <div class="panel">
          <h2>Artistes</h2>
          <p v-if="!isAuthenticated" class="empty-state">Connexion requise pour charger les artistes.</p>
          <div v-else class="list">
            <button
              v-for="artiste in artistes"
              :key="artiste.id"
              class="list-item"
              type="button"
              @click="explore('artiste', artiste)"
            >
              {{ artistName(artiste) }}
            </button>
          </div>
        </div>

        <div class="panel result-panel">
          <h2>{{ explorerResult?.title || 'Résultat' }}</h2>
          <p v-if="explorerResult">{{ explorerResult.count }} élément(s)</p>
          <div v-if="explorerResult?.items?.length" class="track-list">
            <article v-for="item in explorerResult.items" :key="item.id || item.Name || item.name" class="mini-track">
              <strong>{{ explorerResult.type === 'artiste' ? albumName(item) : trackTitle(item) }}</strong>
              <span v-if="explorerResult.type !== 'artiste'">{{ priceLabel(item) }}</span>
            </article>
          </div>
          <p v-else class="empty-state">Sélectionnez un album, un style ou un artiste.</p>
        </div>
      </section>
    </section>
  </main>
</template>
