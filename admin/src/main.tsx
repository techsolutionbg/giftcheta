import React from 'react'
import ReactDOM from 'react-dom/client'
import { createBrowserRouter, RouterProvider } from 'react-router-dom'
import App from './App'
import './styles.css'
import { UnsavedChangesProvider } from './components/UnsavedChanges'
import { AdminSettingsProvider } from './lib/adminSettings'

const router = createBrowserRouter([{ path: '*', element: <UnsavedChangesProvider><AdminSettingsProvider><App /></AdminSettingsProvider></UnsavedChangesProvider> }])

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode><RouterProvider router={router} /></React.StrictMode>,
)
