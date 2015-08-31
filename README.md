# xServer
WebSockets based notifcations handler

From the command line: ```php xserver``` to start the server listening
From the client: connect over your chosen WebSockets client!

# Remote Commands
* "list" to view the stack
* "push:xxxxxx" to add to stack
* "pop:xxx" to view message index number xxx
* "memo:xxxxx" to broadcast message to all clients
* "exit" to disconnect for the server
* "elevate:xxxxx" to elevate client to root and allow server commands below

# Server Commands
* "stop" to pause server from accepting new connections
* "start" to resume server allowing new connections
* "shutdown" to close down the server