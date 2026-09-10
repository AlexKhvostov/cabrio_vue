// Инициализация Telegram WebApp: разворот на весь экран, тема, safe-area.
// Повторяет поведение старого фронтенда (frontend/partials/meta.php + assets/js/app.js).

function tg(): any {
  return (window as any).Telegram?.WebApp
}

function askFullscreen() {
  try {
    const w = tg()
    if (typeof w?.requestFullscreen === 'function' && !w.isFullscreen) {
      w.requestFullscreen()
    }
  } catch {
    /* noop */
  }
}

export function initTelegram() {
  const w = tg()
  if (!w) return
  try {
    w.ready()
    w.expand()
    w.setHeaderColor?.('#070b12')
    w.setBackgroundColor?.('#070b12')
    w.setBottomBarColor?.('#070b12')
    w.disableVerticalSwipes?.()
  } catch {
    /* noop */
  }

  // Не в первый кадр: иначе Telegram на секунду гасит экран серым.
  setTimeout(askFullscreen, 1400)
  document.addEventListener('touchend', askFullscreen, { once: true, passive: true })
  document.addEventListener('click', askFullscreen, { once: true })
}
