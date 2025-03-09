import { Link } from 'react-router-dom';

function Chat() {
  return (
    <div className="chat-container">
      <h1>Чат</h1>
      <div className="buttons-container">
        <Link to="/button1" className="styled-button">Кнопка 1</Link>
        <Link to="/button2" className="styled-button">Кнопка 2</Link>
        <Link to="/button3" className="styled-button">Кнопка 3</Link>
      </div>
    </div>
  );
}

export default Chat; 