

export function GlobalLoadingBar({ active }: { active: boolean }) {
  return <div className={`global-loading-bar${active ? ' active' : ''}`} role={active ? 'status' : undefined} aria-label={active ? 'Зареждане' : undefined} aria-hidden={!active}><span/></div>
}
