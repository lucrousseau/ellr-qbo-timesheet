/**
 * @file Vite entry point that mounts the timesheet React application.
 */

import { mountApp } from '@ellr/vite-config/shared/mountApp'
import '@ellr/vite-config/shared/app.css'
import App from './App.tsx'

mountApp(App)
