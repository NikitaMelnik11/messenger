import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { useState, useEffect } from 'react';
import Login from './components/Login';
import Chat from './components/Chat';
import ButtonPage1 from './components/ButtonPage1';
import ButtonPage2 from './components/ButtonPage2';
import ButtonPage3 from './components/ButtonPage3';

function App() {
  const [isLoggedIn, setIsLoggedIn] = useState(
    localStorage.getItem('isLoggedIn') === 'true'
  );

  useEffect(() => {
    localStorage.setItem('isLoggedIn', isLoggedIn);
  }, [isLoggedIn]);

  const ProtectedRoute = ({ children }) => {
    if (!isLoggedIn) {
      return <Navigate to="/" replace />;
    }
    return children;
  };

  return (
    <Router>
      <Routes>
        <Route path="/" element={
          isLoggedIn ? <Navigate to="/chat" /> : <Login onLogin={() => setIsLoggedIn(true)} />
        } />
        <Route path="/chat" element={
          <ProtectedRoute>
            <Chat />
          </ProtectedRoute>
        } />
        <Route path="/button1" element={
          <ProtectedRoute>
            <ButtonPage1 />
          </ProtectedRoute>
        } />
        <Route path="/button2" element={
          <ProtectedRoute>
            <ButtonPage2 />
          </ProtectedRoute>
        } />
        <Route path="/button3" element={
          <ProtectedRoute>
            <ButtonPage3 />
          </ProtectedRoute>
        } />
      </Routes>
    </Router>
  );
}

export default App; 