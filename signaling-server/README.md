# Audio call signaling server

Run from the project root:

```powershell
npm.cmd run call-server
```

The server authenticates Socket.io connections with the existing `myself_token`,
checks mutual friendship and block state in MySQL, and relays WebRTC signaling.
Set `VITE_CALL_SIGNALING_URL` at frontend build time when the server is not at
`http://<current-host>:3001`.

The WebRTC client uses `stun:stun.l.google.com:19302` by default. TURN remains
optional and is enabled only when these frontend build variables are present:

```text
VITE_TURN_URL=turn:turn.example.com:3478
VITE_TURN_USERNAME=optional-username
VITE_TURN_CREDENTIAL=optional-password
```

Production deployments must serve the app and signaling endpoint over HTTPS/WSS
because browsers require a secure context for microphone access outside localhost.
