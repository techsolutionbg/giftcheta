import type * as React from 'react'
import { Breadcrumbs } from '../components/Navigation'

export function Placeholder({title,icon}:{title:string;icon:React.ReactNode}){return <div className="page-content"><div className="page-heading"><div><div className="eyebrow">GIFTCHETA / В РАЗРАБОТКА</div><h1>{title}</h1><Breadcrumbs/><p>Тази секция ще бъде достъпна в следващ етап.</p></div></div><div className="panel placeholder-panel"><div className="placeholder-icon">{icon}</div><h2>Скоро тук ще има повече.</h2><p>Фокусираме се върху продуктовия каталог. Тази страница е подготвена за следващото развитие.</p></div></div>}
