const style = document.createElement('style');
        style.innerHTML = `
        @keyframes starBlink {
            0% { opacity:0.3; transform: scale(0.5);}
            50% { opacity:1; transform: scale(1);}
            100% { opacity:0.3; transform: scale(0.5);}
        }
        @keyframes imgGlow {
            0% { box-shadow:0 0 20px #fff,0 0 35px #f9f,0 0 45px #ff0;}
            50% { box-shadow:0 0 35px #fff,0 0 45px #f9f,0 0 60px #ff0;}
            100% { box-shadow:0 0 20px #fff,0 0 35px #f9f,0 0 45px #ff0;}
        }`;
        document.head.appendChild(style);
