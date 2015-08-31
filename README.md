# xServer
WebSockets based notifcations handler

From the command line: ```php server.php``` to start the server listening
From the client: connect over your chosen WebSockets client!

# Remote Commands
* "list" to view the stack
* "push:xxxxxx" to add to stack
* "pop:xxx" to view message index number xxx
* "memo:xxxxx" to broadcast message to all clients
* "exit" to disconnect for the server

# Server Commands ( enable xServer->root but its buggy and crashes everything )
* "stop" to pause server
* "start" to resume server
* "list" to view the stack
* "push:xxxxx" to add to the stack
* "pop:xxxxx" to view message index number xxx
* "memo:xxxxx" to broadcast message to all clients
* "exit" to close down the server