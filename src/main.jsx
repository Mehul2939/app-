import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
import './styles.css';
import App from './App';
import { AuthProvider } from './contexts/AuthContext';
import { ThemeProvider } from './contexts/ThemeContext';
import { AudioPlayerProvider } from './contexts/AudioPlayerContext';
import { CallProvider } from './contexts/CallContext';

const routerBase = window.location.pathname.startsWith('/app/admin') ? '/app' : '/app/public';

createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <BrowserRouter basename={routerBase}>
      <ThemeProvider>
        <AuthProvider>
          <AudioPlayerProvider>
            <CallProvider>
              <App />
            </CallProvider>
          </AudioPlayerProvider>
        </AuthProvider>
      </ThemeProvider>
    </BrowserRouter>
  </React.StrictMode>
);
