import { useNavigate } from 'react-router-dom';
import { useState } from 'react';

function Login({ onLogin }) {
  const navigate = useNavigate();
  const [error, setError] = useState('');

  const handleLogin = async () => {
    try {
      // Здесь должна быть логика авторизации
      // Например, запрос к API
      onLogin();
      navigate('/chat');
    } catch (err) {
      setError('Ошибка при входе в систему');
      console.error('Login error:', err);
    }
  };

  return (
    <div className="login-container">
      <h1>Вход в систему</h1>
      {error && <div className="error-message">{error}</div>}
      <button onClick={handleLogin}>Войти</button>
    </div>
  );
}

export default Login; 