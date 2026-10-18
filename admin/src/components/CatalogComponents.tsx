import * as React from 'react'
import { useId } from 'react'
import { FieldHelp } from './FieldHelp'

export function StatCard({icon,label,value,note,tint}:{icon:React.ReactNode;label:string;value:string;note:string;tint:string}){return <div className="stat-card"><div className={`stat-icon ${tint}`}>{icon}</div><span className="stat-label">{label}</span><strong>{value}</strong><small>{note}</small></div>}

export function StatusBadge({status}:{status:string}){return <span className={`status-badge ${status}`}>{status==='trashed'?'В кошчето':status==='active'?'Активен':status==='archived'?'Архивиран':'Чернова'}</span>}

export function EmptyState({icon,title,description,action,products=false,categories=false}:{icon:React.ReactNode;title:string;description:string;action?:React.ReactNode;products?:boolean;categories?:boolean}) {
  const catalogEmpty = products || categories
  return <div className={`empty-state ${products?'products-empty':''} ${categories?'categories-empty':''}`}>
    <div className={`empty-icon ${catalogEmpty?'catalog-empty-icon':''}`} aria-hidden="true">{icon}</div>
    <h3>{title}</h3>
    <p>{description}</p>
    {action}
  </div>
}

export function FormField({label,required,helpText,children,error}:{label:string;required?:boolean;helpText:string;children:React.ReactNode;error?:string}){
  const controlId=useId()
  const labelId=`${controlId}-label`
  const child=React.Children.only(children)
  let control=child
  if(React.isValidElement(child)){
    control=typeof child.type==='string'
      ?React.cloneElement(child as React.ReactElement<{id?:string;'aria-labelledby'?:string}>,{id:controlId,'aria-labelledby':labelId})
      :React.cloneElement(child as React.ReactElement<{ariaLabelledBy?:string}>,{ariaLabelledBy:labelId})
  }
  return <div className="form-field"><div className="form-field-heading"><label id={labelId} htmlFor={controlId}>{label}{required&&<i>*</i>}</label><FieldHelp content={helpText}/></div><div className="form-field-control">{control}</div>{error&&<div className="form-error" role="alert">{error}</div>}</div>
}

export function FeatureNote({icon,title,text}:{icon:React.ReactNode;title:string;text:string}){return <div className="feature-note">{icon}<div><strong>{title}</strong><p>{text}</p></div></div>}
